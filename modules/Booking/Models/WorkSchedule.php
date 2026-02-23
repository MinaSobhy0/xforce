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
use Modules\Staff\Models\StaffProfile;

class WorkSchedule extends BaseModel
{
    use HasTenancy, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'code',
        'description',
        'schedule_type',
        'required_hours_per_day',
        'required_hours_per_week',
        'working_days',
        'flexible_start_time',
        'flexible_end_time',
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
        'working_days' => 'array',
        'required_hours_per_day' => 'decimal:2',
        'required_hours_per_week' => 'decimal:2',
        'slot_duration' => 'integer',
        'buffer_time' => 'integer',
        'max_daily_appointments' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Schedule Types
    public const TYPE_FIXED = 'fixed';
    public const TYPE_FLEXIBLE = 'flexible';

    public const SCHEDULE_TYPES = [
        self::TYPE_FIXED => 'Fixed Hours',
        self::TYPE_FLEXIBLE => 'Flexible Hours',
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

    public function staffProfiles(): BelongsToMany
    {
        return $this->belongsToMany(StaffProfile::class, 'practitioner_schedule_assignments', 'work_schedule_id', 'staff_profile_id')
            ->withPivot(['effective_from', 'effective_until', 'day_overrides', 'is_primary', 'is_active', 'notes'])
            ->withTimestamps();
    }

    public function activeStaffProfiles(): BelongsToMany
    {
        return $this->staffProfiles()
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
        // For flexible schedules
        if ($this->schedule_type === self::TYPE_FLEXIBLE) {
            $workingDays = $this->working_days ?? [];
            $dayNames = collect($workingDays)->map(fn($day) => self::DAYS_SHORT[$day] ?? $day)->implode(', ');

            $hoursInfo = '';
            if ($this->required_hours_per_day) {
                $hoursInfo = "{$this->required_hours_per_day}h/day";
            } elseif ($this->required_hours_per_week) {
                $hoursInfo = "{$this->required_hours_per_week}h/week";
            }

            $timeWindow = '';
            if ($this->flexible_start_time && $this->flexible_end_time) {
                $start = date('g:i A', strtotime($this->flexible_start_time));
                $end = date('g:i A', strtotime($this->flexible_end_time));
                $timeWindow = " between {$start}-{$end}";
            }

            if ($hoursInfo) {
                return $dayNames . " ({$hoursInfo}{$timeWindow})";
            }
            return $dayNames . ' (Flexible)';
        }

        // For fixed schedules
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
        // For flexible schedules
        if ($this->schedule_type === self::TYPE_FLEXIBLE) {
            if ($this->required_hours_per_week) {
                return round($this->required_hours_per_week, 1);
            }
            if ($this->required_hours_per_day) {
                $workingDaysCount = count($this->working_days ?? []);
                return round($this->required_hours_per_day * $workingDaysCount, 1);
            }
            return 0;
        }

        // For fixed schedules
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
        // For flexible schedules
        if ($this->schedule_type === self::TYPE_FLEXIBLE) {
            $workingDays = $this->working_days ?? [];
            return in_array($dayOfWeek, $workingDays);
        }

        // For fixed schedules
        $hours = $this->weekly_hours ?? [];
        return !empty($hours[$dayOfWeek]['is_working']);
    }

    /**
     * Check if this is a flexible schedule.
     */
    public function isFlexible(): bool
    {
        return $this->schedule_type === self::TYPE_FLEXIBLE;
    }

    /**
     * Check if a time is within the flexible time window.
     */
    public function isWithinFlexibleWindow(string $time): bool
    {
        if (!$this->isFlexible()) {
            return false;
        }

        // If no time window set, any time is valid
        if (!$this->flexible_start_time || !$this->flexible_end_time) {
            return true;
        }

        $checkTime = strtotime($time);
        $startTime = strtotime($this->flexible_start_time);
        $endTime = strtotime($this->flexible_end_time);

        return $checkTime >= $startTime && $checkTime <= $endTime;
    }

    /**
     * Get flexible time window as array.
     */
    public function getFlexibleTimeWindow(): ?array
    {
        if (!$this->isFlexible() || !$this->flexible_start_time || !$this->flexible_end_time) {
            return null;
        }

        return [
            'start' => $this->flexible_start_time,
            'end' => $this->flexible_end_time,
        ];
    }

    /**
     * Get required hours for a specific day (for flexible schedules).
     */
    public function getRequiredHoursForDay(int $dayOfWeek): ?float
    {
        if (!$this->isWorkingDay($dayOfWeek)) {
            return null;
        }

        if ($this->schedule_type === self::TYPE_FLEXIBLE) {
            return $this->required_hours_per_day;
        }

        // For fixed schedules, calculate from the day config
        $daySchedule = $this->getDaySchedule($dayOfWeek);
        if (!$daySchedule || empty($daySchedule['start_time']) || empty($daySchedule['end_time'])) {
            return null;
        }

        $start = strtotime($daySchedule['start_time']);
        $end = strtotime($daySchedule['end_time']);
        $hours = ($end - $start) / 3600;

        // Subtract break
        if (!empty($daySchedule['break_start']) && !empty($daySchedule['break_end'])) {
            $breakStart = strtotime($daySchedule['break_start']);
            $breakEnd = strtotime($daySchedule['break_end']);
            $hours -= ($breakEnd - $breakStart) / 3600;
        }

        return max(0, round($hours, 2));
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
            $q->where('work_schedules.branch_id', $branchId)
                ->orWhereNull('work_schedules.branch_id');
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
