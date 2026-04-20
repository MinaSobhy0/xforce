<?php

namespace Modules\Booking\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Staff\Models\StaffProfile;
use XLinic\Framework\Core\Model\BaseModel;

class TimeOffAllocation extends BaseModel
{
    protected $table = 'time_off_allocations';

    protected $fillable = [
        'tenant_id',
        'staff_profile_id',
        'time_off_type_id',
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
        'date_from' => 'date',
        'date_to' => 'date',
        'allocated_days' => 'decimal:2',
        'used_days' => 'decimal:2',
        'carried_over_days' => 'decimal:2',
        'odoo_synced_at' => 'datetime',
    ];

    protected $attributes = [
        'allocated_days' => 0,
        'used_days' => 0,
        'carried_over_days' => 0,
    ];

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function timeOffType(): BelongsTo
    {
        return $this->belongsTo(TimeOffType::class);
    }

    public function getRemainingDaysAttribute(): float
    {
        return (float) $this->allocated_days + (float) $this->carried_over_days - (float) $this->used_days;
    }

    public function getRemainingAttribute(): float
    {
        return $this->remaining_days;
    }

    public function getTotalDaysAttribute(): float
    {
        return (float) $this->allocated_days + (float) $this->carried_over_days;
    }

    public function getTotalAttribute(): float
    {
        return $this->total_days;
    }

    public function getDisplayValueAttribute(): string
    {
        $type = $this->timeOffType;
        if (! $type) {
            return number_format($this->remaining_days, 1).' '.__('booking::time_off.request_units.day');
        }

        return $type->formatValue($this->remaining_days);
    }

    public function getTotalDisplayValueAttribute(): string
    {
        $type = $this->timeOffType;
        if (! $type) {
            return number_format($this->total_days, 1).' '.__('booking::time_off.request_units.day');
        }

        return $type->formatValue($this->total_days);
    }

    /**
     * Human label for the allocation period, derived from the date range.
     * - Full calendar year  → "2026"
     * - Full calendar month → "April 2026"
     * - Anything else       → "Apr 01 – Apr 15, 2026"
     */
    public function getPeriodLabelAttribute(): string
    {
        $from = $this->date_from instanceof Carbon ? $this->date_from : Carbon::parse($this->date_from);

        if ($this->date_to === null) {
            return $from->translatedFormat('M d, Y').' – '.__('booking::time_off.allocations.fields.no_end');
        }

        $to = $this->date_to instanceof Carbon ? $this->date_to : Carbon::parse($this->date_to);

        if ($from->isSameDay($from->copy()->startOfYear()) && $to->isSameDay($from->copy()->endOfYear())) {
            return (string) $from->year;
        }

        if (
            $from->year === $to->year
            && $from->month === $to->month
            && $from->isSameDay($from->copy()->startOfMonth())
            && $to->isSameDay($from->copy()->endOfMonth())
        ) {
            return $from->translatedFormat('F Y');
        }

        if ($from->year === $to->year) {
            return $from->translatedFormat('M d').' – '.$to->translatedFormat('M d, Y');
        }

        return $from->translatedFormat('M d, Y').' – '.$to->translatedFormat('M d, Y');
    }

    public function hasAvailableDays(float $days): bool
    {
        return $this->remaining_days >= $days;
    }

    public function hasAvailable(float $amount): bool
    {
        return $this->remaining >= $amount;
    }

    public function useDays(float $days): bool
    {
        if (! $this->hasAvailableDays($days)) {
            return false;
        }

        $this->used_days = (float) $this->used_days + $days;

        return $this->save();
    }

    public function use(float $amount): bool
    {
        return $this->useDays($amount);
    }

    public function returnDays(float $days): bool
    {
        $this->used_days = max(0, (float) $this->used_days - $days);

        return $this->save();
    }

    public function returnAmount(float $amount): bool
    {
        return $this->returnDays($amount);
    }

    /**
     * Build the [date_from, date_to] that should cover $date for a given type.
     * Mirrors Odoo's allocation validity: monthly types use a calendar month,
     * yearly types use a calendar year.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function deriveDateRange(?TimeOffType $type, Carbon $date): array
    {
        if ($type?->isMonthly()) {
            return [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()];
        }

        return [$date->copy()->startOfYear(), $date->copy()->endOfYear()];
    }

    /**
     * Find the allocation that covers $date for this staff profile and type.
     */
    public static function getForDate(int $staffProfileId, int $typeId, Carbon $date): ?self
    {
        return static::query()
            ->where('staff_profile_id', $staffProfileId)
            ->where('time_off_type_id', $typeId)
            ->where('date_from', '<=', $date->toDateString())
            ->where(function ($q) use ($date) {
                $q->whereNull('date_to')
                    ->orWhere('date_to', '>=', $date->toDateString());
            })
            ->orderByDesc('date_from')
            ->first();
    }

    /**
     * Find or create the allocation covering $date (using the type's natural period).
     */
    public static function getOrCreateForDate(int $staffProfileId, int $typeId, Carbon $date): self
    {
        $existing = static::getForDate($staffProfileId, $typeId, $date);
        if ($existing) {
            return $existing;
        }

        $type = TimeOffType::find($typeId);
        [$from, $to] = static::deriveDateRange($type, $date);

        return static::firstOrCreate(
            [
                'tenant_id' => current_tenant_id(),
                'staff_profile_id' => $staffProfileId,
                'time_off_type_id' => $typeId,
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ],
            [
                'allocated_days' => $type?->getEffectiveDefaultAllocation() ?? 0,
                'used_days' => 0,
                'carried_over_days' => 0,
            ]
        );
    }

    public function scopeForStaffProfile($query, int $staffProfileId)
    {
        return $query->where('staff_profile_id', $staffProfileId);
    }

    public function scopeForType($query, int $typeId)
    {
        return $query->where('time_off_type_id', $typeId);
    }

    public function scopeCoveringDate($query, Carbon $date)
    {
        return $query->where('date_from', '<=', $date->toDateString())
            ->where(function ($q) use ($date) {
                $q->whereNull('date_to')
                    ->orWhere('date_to', '>=', $date->toDateString());
            });
    }

    public function scopeOverlappingRange($query, Carbon $from, Carbon $to)
    {
        return $query->where('date_from', '<=', $to->toDateString())
            ->where(function ($q) use ($from) {
                $q->whereNull('date_to')
                    ->orWhere('date_to', '>=', $from->toDateString());
            });
    }
}
