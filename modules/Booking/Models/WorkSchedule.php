<?php

namespace Modules\Booking\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;

class WorkSchedule extends BaseModel
{
    use HasTenancy, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'code',
        'description',
        'weekly_hours',
        'slot_duration',
        'buffer_time',
        'max_daily_appointments',
        'color',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'weekly_hours' => 'array',
        'slot_duration' => 'integer',
        'buffer_time' => 'integer',
        'max_daily_appointments' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Days of week (0 = Sunday, 6 = Saturday)
    public const DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public const DAYS_SHORT = [
        0 => 'Sun',
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
    ];

    /**
     * Default weekly hours structure.
     */
    public static function getDefaultWeeklyHours(): array
    {
        $default = [];
        for ($i = 0; $i < 7; $i++) {
            $default[$i] = [
                'is_working' => $i !== 5, // Friday off by default
                'start_time' => '09:00',
                'end_time' => '17:00',
                'break_start' => null,
                'break_end' => null,
            ];
        }
        return $default;
    }

    // Relationships

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PractitionerScheduleAssignment::class);
    }

    public function practitioners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'practitioner_schedule_assignments', 'work_schedule_id', 'user_id')
            ->withPivot(['effective_from', 'effective_until', 'day_overrides', 'is_primary', 'is_active', 'branch_id', 'notes'])
            ->withTimestamps();
    }

    public function activePractitioners(): BelongsToMany
    {
        return $this->practitioners()
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query->whereNull('practitioner_schedule_assignments.effective_from')
                    ->orWhere('practitioner_schedule_assignments.effective_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('practitioner_schedule_assignments.effective_until')
                    ->orWhere('practitioner_schedule_assignments.effective_until', '>=', now());
            });
    }

    // Accessors

    /**
     * Get formatted schedule summary.
     */
    public function getScheduleSummaryAttribute(): string
    {
        $hours = $this->weekly_hours ?? [];
        $workingDays = [];

        foreach ($hours as $day => $config) {
            if (!empty($config['is_working'])) {
                $workingDays[] = self::DAYS_SHORT[$day] ?? $day;
            }
        }

        if (empty($workingDays)) {
            return 'No working days';
        }

        // Get first day's hours as example
        $firstWorking = collect($hours)->first(fn($c) => !empty($c['is_working']));
        $timeRange = $firstWorking
            ? "{$firstWorking['start_time']} - {$firstWorking['end_time']}"
            : '';

        return implode(', ', $workingDays) . ($timeRange ? " ({$timeRange})" : '');
    }

    /**
     * Get total working hours per week.
     */
    public function getTotalWeeklyHoursAttribute(): float
    {
        $hours = $this->weekly_hours ?? [];
        $total = 0;

        foreach ($hours as $config) {
            if (!empty($config['is_working']) && !empty($config['start_time']) && !empty($config['end_time'])) {
                $start = strtotime($config['start_time']);
                $end = strtotime($config['end_time']);
                $dayHours = ($end - $start) / 3600;

                // Subtract break
                if (!empty($config['break_start']) && !empty($config['break_end'])) {
                    $breakStart = strtotime($config['break_start']);
                    $breakEnd = strtotime($config['break_end']);
                    $dayHours -= ($breakEnd - $breakStart) / 3600;
                }

                $total += max(0, $dayHours);
            }
        }

        return round($total, 1);
    }

    /**
     * Get working days count.
     */
    public function getWorkingDaysCountAttribute(): int
    {
        $hours = $this->weekly_hours ?? [];
        return collect($hours)->filter(fn($c) => !empty($c['is_working']))->count();
    }

    // Methods

    /**
     * Check if a specific day is a working day.
     */
    public function isWorkingDay(int $dayOfWeek): bool
    {
        $hours = $this->weekly_hours ?? [];
        return !empty($hours[$dayOfWeek]['is_working']);
    }

    /**
     * Get schedule for a specific day.
     */
    public function getDaySchedule(int $dayOfWeek): ?array
    {
        $hours = $this->weekly_hours ?? [];
        return $hours[$dayOfWeek] ?? null;
    }

    /**
     * Check if a time is within working hours for a specific day.
     */
    public function isAvailableAt(int $dayOfWeek, string $time): bool
    {
        $daySchedule = $this->getDaySchedule($dayOfWeek);

        if (!$daySchedule || empty($daySchedule['is_working'])) {
            return false;
        }

        // Check if within working hours
        if ($time < $daySchedule['start_time'] || $time >= $daySchedule['end_time']) {
            return false;
        }

        // Check if during break
        if (!empty($daySchedule['break_start']) && !empty($daySchedule['break_end'])) {
            if ($time >= $daySchedule['break_start'] && $time < $daySchedule['break_end']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate time slots for a specific day.
     */
    public function generateSlots(int $dayOfWeek): array
    {
        $daySchedule = $this->getDaySchedule($dayOfWeek);

        if (!$daySchedule || empty($daySchedule['is_working'])) {
            return [];
        }

        $slots = [];
        $slotDuration = $this->slot_duration ?? 30;
        $bufferTime = $this->buffer_time ?? 0;
        $interval = $slotDuration + $bufferTime;

        $start = strtotime($daySchedule['start_time']);
        $end = strtotime($daySchedule['end_time']);
        $breakStart = !empty($daySchedule['break_start']) ? strtotime($daySchedule['break_start']) : null;
        $breakEnd = !empty($daySchedule['break_end']) ? strtotime($daySchedule['break_end']) : null;

        $current = $start;

        while ($current + ($slotDuration * 60) <= $end) {
            $slotEnd = $current + ($slotDuration * 60);

            // Skip if slot overlaps with break
            $skipSlot = false;
            if ($breakStart && $breakEnd) {
                if (($current >= $breakStart && $current < $breakEnd) ||
                    ($slotEnd > $breakStart && $slotEnd <= $breakEnd) ||
                    ($current < $breakStart && $slotEnd > $breakEnd)) {
                    $skipSlot = true;
                }
            }

            if (!$skipSlot) {
                $slots[] = [
                    'start' => date('H:i', $current),
                    'end' => date('H:i', $slotEnd),
                ];
            }

            $current += ($interval * 60);
        }

        return $slots;
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForBranch($query, string $branchId)
    {
        return $query->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)
                ->orWhereNull('branch_id');
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
