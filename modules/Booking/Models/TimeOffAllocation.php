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
        'time_off_type_id',
        'year',
        'allocated_days',
        'used_days',
        'carried_over_days',
        'notes',
    ];

    protected $casts = [
        'id' => 'string',
        'year' => 'integer',
        'allocated_days' => 'decimal:1',
        'used_days' => 'decimal:1',
        'carried_over_days' => 'decimal:1',
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
     * Get total days (allocated + carried over).
     */
    public function getTotalDaysAttribute(): float
    {
        return $this->allocated_days + $this->carried_over_days;
    }

    /**
     * Check if user has enough days available.
     */
    public function hasAvailableDays(float $days): bool
    {
        return $this->remaining_days >= $days;
    }

    /**
     * Use days from allocation.
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
     * Return days to allocation (for cancelled time off).
     */
    public function returnDays(float $days): bool
    {
        $this->used_days = max(0, $this->used_days - $days);
        return $this->save();
    }

    /**
     * Get or create allocation for a user and type for a year.
     */
    public static function getOrCreate(string $userId, string $typeId, int $year): self
    {
        $type = TimeOffType::find($typeId);

        return static::firstOrCreate(
            [
                'tenant_id' => current_tenant_id(),
                'user_id' => $userId,
                'time_off_type_id' => $typeId,
                'year' => $year,
            ],
            [
                'allocated_days' => $type?->default_days_per_year ?? 0,
                'used_days' => 0,
                'carried_over_days' => 0,
            ]
        );
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
}
