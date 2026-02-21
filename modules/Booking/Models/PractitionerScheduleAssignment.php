<?php

namespace Modules\Booking\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;

class PractitionerScheduleAssignment extends BaseModel
{
    use HasTenancy, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'work_schedule_id',
        'branch_id',
        'effective_from',
        'effective_until',
        'day_overrides',
        'is_primary',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
        'day_overrides' => 'array',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // Accessors

    /**
     * Get effective branch (assignment branch or schedule branch).
     */
    public function getEffectiveBranchIdAttribute(): ?string
    {
        return $this->branch_id ?? $this->workSchedule?->branch_id;
    }

    /**
     * Get effective schedule for a day (with overrides applied).
     */
    public function getEffectiveDaySchedule(int $dayOfWeek): ?array
    {
        $baseSchedule = $this->workSchedule?->getDaySchedule($dayOfWeek);

        if (!$baseSchedule) {
            return null;
        }

        // Apply overrides if any
        $overrides = $this->day_overrides ?? [];
        if (isset($overrides[$dayOfWeek])) {
            return array_merge($baseSchedule, $overrides[$dayOfWeek]);
        }

        return $baseSchedule;
    }

    /**
     * Check if assignment is currently active.
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $today = now()->toDateString();

        if ($this->effective_from && $this->effective_from->toDateString() > $today) {
            return false;
        }

        if ($this->effective_until && $this->effective_until->toDateString() < $today) {
            return false;
        }

        return true;
    }

    /**
     * Check if practitioner is available at a specific day and time.
     */
    public function isAvailableAt(int $dayOfWeek, string $time): bool
    {
        if (!$this->isCurrentlyActive()) {
            return false;
        }

        $daySchedule = $this->getEffectiveDaySchedule($dayOfWeek);

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
     * Generate available slots for a specific date.
     */
    public function generateSlotsForDate(\DateTimeInterface $date): array
    {
        if (!$this->isCurrentlyActive()) {
            return [];
        }

        $dayOfWeek = (int) $date->format('w');
        $daySchedule = $this->getEffectiveDaySchedule($dayOfWeek);

        if (!$daySchedule || empty($daySchedule['is_working'])) {
            return [];
        }

        $workSchedule = $this->workSchedule;
        $slotDuration = $workSchedule->slot_duration ?? 30;
        $bufferTime = $workSchedule->buffer_time ?? 0;
        $interval = $slotDuration + $bufferTime;

        $slots = [];
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
                    'datetime_start' => $date->format('Y-m-d') . ' ' . date('H:i:s', $current),
                    'datetime_end' => $date->format('Y-m-d') . ' ' . date('H:i:s', $slotEnd),
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

    public function scopeCurrentlyEffective($query)
    {
        $today = now()->toDateString();

        return $query->where(function ($q) use ($today) {
            $q->whereNull('effective_from')
                ->orWhere('effective_from', '<=', $today);
        })->where(function ($q) use ($today) {
            $q->whereNull('effective_until')
                ->orWhere('effective_until', '>=', $today);
        });
    }

    public function scopeForPractitioner($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForBranch($query, string $branchId)
    {
        return $query->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)
                ->orWhereHas('workSchedule', function ($ws) use ($branchId) {
                    $ws->where('branch_id', $branchId)->orWhereNull('branch_id');
                });
        });
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}
