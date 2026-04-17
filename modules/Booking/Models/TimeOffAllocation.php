<?php

namespace Modules\Booking\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;
use XLinic\Framework\Core\Model\BaseModel;

class TimeOffAllocation extends BaseModel
{
    protected $table = 'time_off_allocations';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'staff_profile_id',
        'time_off_type_id',
        'year',
        'month',
        'date_from',
        'date_to',
        'allocated_days',
        'used_days',
        'carried_over_days',
        'notes',
        'odoo_id',
        'odoo_synced_at',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'date_from' => 'date',
        'date_to' => 'date',
        'allocated_days' => 'decimal:2',
        'used_days' => 'decimal:2',
        'carried_over_days' => 'decimal:2',
    ];

    protected $attributes = [
        'allocated_days' => 0,
        'used_days' => 0,
        'carried_over_days' => 0,
    ];

    /**
     * Get the practitioner (user).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the time off type.
     */
    public function timeOffType(): BelongsTo
    {
        return $this->belongsTo(TimeOffType::class);
    }

    /**
     * Get total available days (allocated + carried over - used).
     */
    public function getRemainingDaysAttribute(): float
    {
        return $this->allocated_days + $this->carried_over_days - $this->used_days;
    }

    /**
     * Get remaining value (hours or days depending on type).
     */
    public function getRemainingAttribute(): float
    {
        return $this->allocated_days + $this->carried_over_days - $this->used_days;
    }

    /**
     * Get total days (allocated + carried over).
     */
    public function getTotalDaysAttribute(): float
    {
        return $this->allocated_days + $this->carried_over_days;
    }

    /**
     * Get total value (hours or days depending on type).
     */
    public function getTotalAttribute(): float
    {
        return $this->allocated_days + $this->carried_over_days;
    }

    /**
     * Get formatted display value with unit label.
     */
    public function getDisplayValueAttribute(): string
    {
        $type = $this->timeOffType;
        if (!$type) {
            return number_format($this->remaining_days, 1) . ' ' . __('booking::time_off.request_units.day');
        }

        return $type->formatValue($this->remaining_days);
    }

    /**
     * Get formatted total display value with unit label.
     */
    public function getTotalDisplayValueAttribute(): string
    {
        $type = $this->timeOffType;
        if (!$type) {
            return number_format($this->total_days, 1) . ' ' . __('booking::time_off.request_units.day');
        }

        return $type->formatValue($this->total_days);
    }

    /**
     * Get the period label (year or month/year).
     */
    public function getPeriodLabelAttribute(): string
    {
        if ($this->month) {
            $monthName = \Carbon\Carbon::create()->month($this->month)->translatedFormat('F');
            return "{$monthName} {$this->year}";
        }

        return (string) $this->year;
    }

    /**
     * Check if user has enough days/hours available.
     */
    public function hasAvailableDays(float $days): bool
    {
        return $this->remaining_days >= $days;
    }

    /**
     * Check if user has enough of the allocation unit available.
     */
    public function hasAvailable(float $amount): bool
    {
        return $this->remaining >= $amount;
    }

    /**
     * Use days/hours from allocation.
     */
    public function useDays(float $days): bool
    {
        if (!$this->hasAvailableDays($days)) {
            return false;
        }

        $this->used_days += $days;
        return $this->save();
    }

    /**
     * Use allocation (works with hours or days).
     */
    public function use(float $amount): bool
    {
        return $this->useDays($amount);
    }

    /**
     * Return days/hours to allocation (for cancelled time off).
     */
    public function returnDays(float $days): bool
    {
        $this->used_days = max(0, $this->used_days - $days);
        return $this->save();
    }

    /**
     * Return allocation (works with hours or days).
     */
    public function returnAmount(float $amount): bool
    {
        return $this->returnDays($amount);
    }

    /**
     * Get or create allocation for a user and type for a year (and optionally month).
     */
    public static function getOrCreate(string $userId, string $typeId, int $year, ?int $month = null): self
    {
        $type = TimeOffType::find($typeId);

        // Determine if we need monthly allocation
        $useMonth = $type?->isMonthly() ? $month : null;

        $criteria = [
            'tenant_id' => current_tenant_id(),
            'user_id' => $userId,
            'time_off_type_id' => $typeId,
            'year' => $year,
            'month' => $useMonth,
        ];

        // Get the default allocation value
        $defaultAllocation = $type?->getEffectiveDefaultAllocation() ?? 0;

        return static::firstOrCreate(
            $criteria,
            [
                'allocated_days' => $defaultAllocation,
                'used_days' => 0,
                'carried_over_days' => 0,
            ]
        );
    }

    /**
     * Get allocation for a specific date (handles both yearly and monthly).
     */
    public static function getForDate(string $userId, string $typeId, \Carbon\Carbon $date): ?self
    {
        $type = TimeOffType::find($typeId);

        if (!$type) {
            return null;
        }

        $query = static::where('user_id', $userId)
            ->where('time_off_type_id', $typeId)
            ->where('year', $date->year);

        if ($type->isMonthly()) {
            $query->where('month', $date->month);
        } else {
            $query->whereNull('month');
        }

        return $query->first();
    }

    /**
     * Get or create allocation for a specific date (handles both yearly and monthly).
     */
    public static function getOrCreateForDate(string $userId, string $typeId, \Carbon\Carbon $date): self
    {
        $type = TimeOffType::find($typeId);

        $month = $type?->isMonthly() ? $date->month : null;

        return static::getOrCreate($userId, $typeId, $date->year, $month);
    }

    /**
     * Scope to specific year.
     */
    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope to specific user.
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to specific type.
     */
    public function scopeForType($query, string $typeId)
    {
        return $query->where('time_off_type_id', $typeId);
    }

    /**
     * Scope to specific month (for monthly allocations).
     */
    public function scopeForMonth($query, ?int $month)
    {
        if ($month === null) {
            return $query->whereNull('month');
        }

        return $query->where('month', $month);
    }

    /**
     * Scope to yearly allocations only.
     */
    public function scopeYearly($query)
    {
        return $query->whereNull('month');
    }

    /**
     * Scope to monthly allocations only.
     */
    public function scopeMonthly($query)
    {
        return $query->whereNotNull('month');
    }
}
