<?php

namespace Modules\Booking\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;
use Carbon\Carbon;

class BookingBlackoutDate extends BaseModel
{
    use HasTenancy, HasActivity, SoftDeletes;

    protected $table = 'booking_blackout_dates';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'start_date',
        'end_date',
        'is_recurring',
        'recurrence_type',
        'affects_online_booking',
        'affects_staff_booking',
        'reason',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_recurring' => 'boolean',
        'affects_online_booking' => 'boolean',
        'affects_staff_booking' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Recurrence types
    public const RECURRENCE_YEARLY = 'yearly';
    public const RECURRENCE_MONTHLY = 'monthly';

    public const RECURRENCE_TYPES = [
        self::RECURRENCE_YEARLY => 'Yearly',
        self::RECURRENCE_MONTHLY => 'Monthly',
    ];

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Accessors
    public function getRecurrenceTypeLabelAttribute(): ?string
    {
        if (!$this->recurrence_type) {
            return null;
        }
        return self::RECURRENCE_TYPES[$this->recurrence_type] ?? $this->recurrence_type;
    }

    public function getScopeDescriptionAttribute(): string
    {
        return $this->branch ? $this->branch->name : __('All Branches');
    }

    public function getDateRangeDisplayAttribute(): string
    {
        if ($this->start_date->eq($this->end_date)) {
            return $this->start_date->format('M d, Y');
        }
        return $this->start_date->format('M d') . ' - ' . $this->end_date->format('M d, Y');
    }

    public function getDurationDaysAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function getIsUpcomingAttribute(): bool
    {
        return $this->start_date->isFuture() || $this->end_date->isFuture();
    }

    public function getIsCurrentAttribute(): bool
    {
        $now = now()->startOfDay();
        return $now->between($this->start_date, $this->end_date);
    }

    public function getIsPastAttribute(): bool
    {
        return $this->end_date->isPast();
    }

    // Check if date is blocked
    public function blocksDate(Carbon $date, ?string $branchId = null, bool $isOnlineBooking = false, bool $isStaffBooking = false): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Check branch scope
        if ($this->branch_id && $branchId && $this->branch_id !== $branchId) {
            return false;
        }

        // Check booking type scope
        if ($isOnlineBooking && !$this->affects_online_booking) {
            return false;
        }
        if ($isStaffBooking && !$this->affects_staff_booking) {
            return false;
        }

        // Check date match (handle recurring)
        return $this->dateMatchesBlackout($date);
    }

    protected function dateMatchesBlackout(Carbon $date): bool
    {
        if (!$this->is_recurring) {
            // One-time blackout: simple date range check
            return $date->between($this->start_date, $this->end_date);
        }

        // Recurring blackout
        return match ($this->recurrence_type) {
            self::RECURRENCE_YEARLY => $this->matchesYearlyRecurrence($date),
            self::RECURRENCE_MONTHLY => $this->matchesMonthlyRecurrence($date),
            default => false,
        };
    }

    protected function matchesYearlyRecurrence(Carbon $date): bool
    {
        // Get the blackout range for the date's year
        $startInYear = $this->start_date->copy()->year($date->year);
        $endInYear = $this->end_date->copy()->year($date->year);

        // Handle year boundary (e.g., Dec 28 - Jan 2)
        if ($this->end_date->month < $this->start_date->month) {
            // Blackout spans year boundary
            if ($date->month >= $this->start_date->month) {
                // Check in current year's range
                return $date->between($startInYear, $startInYear->copy()->endOfYear());
            } else {
                // Check in next year's range (start from previous year)
                $startFromPrevYear = $this->start_date->copy()->year($date->year - 1);
                return $date->between($startFromPrevYear, $endInYear);
            }
        }

        return $date->between($startInYear, $endInYear);
    }

    protected function matchesMonthlyRecurrence(Carbon $date): bool
    {
        // Get the start and end days
        $startDay = $this->start_date->day;
        $endDay = $this->end_date->day;

        // Handle same month blackout
        if ($this->start_date->month === $this->end_date->month) {
            return $date->day >= $startDay && $date->day <= $endDay;
        }

        // Handle month boundary blackout (e.g., 28th to 3rd)
        if ($date->day >= $startDay || $date->day <= $endDay) {
            return true;
        }

        return false;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    public function scopeOneTime($query)
    {
        return $query->where('is_recurring', false);
    }

    public function scopeForBranch($query, ?string $branchId)
    {
        return $query->where(function ($q) use ($branchId) {
            $q->whereNull('branch_id'); // Global blackouts
            if ($branchId) {
                $q->orWhere('branch_id', $branchId);
            }
        });
    }

    public function scopeAffectingOnlineBooking($query)
    {
        return $query->where('affects_online_booking', true);
    }

    public function scopeAffectingStaffBooking($query)
    {
        return $query->where('affects_staff_booking', true);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('end_date', '>=', now()->startOfDay());
    }

    public function scopeCurrent($query)
    {
        $now = now()->startOfDay();
        return $query->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now);
    }

    public function scopeInDateRange($query, Carbon $start, Carbon $end)
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('start_date', [$start, $end])
                ->orWhereBetween('end_date', [$start, $end])
                ->orWhere(function ($inner) use ($start, $end) {
                    $inner->where('start_date', '<=', $start)
                        ->where('end_date', '>=', $end);
                });
        });
    }

    public function scopeOrderByDate($query)
    {
        return $query->orderBy('start_date');
    }

    // Static helpers
    public static function isDateBlocked(
        Carbon $date,
        ?string $branchId = null,
        bool $isOnlineBooking = false,
        bool $isStaffBooking = false
    ): bool {
        return static::active()
            ->forBranch($branchId)
            ->get()
            ->some(fn ($blackout) => $blackout->blocksDate($date, $branchId, $isOnlineBooking, $isStaffBooking));
    }

    public static function getBlockingBlackout(
        Carbon $date,
        ?string $branchId = null,
        bool $isOnlineBooking = false,
        bool $isStaffBooking = false
    ): ?self {
        return static::active()
            ->forBranch($branchId)
            ->get()
            ->first(fn ($blackout) => $blackout->blocksDate($date, $branchId, $isOnlineBooking, $isStaffBooking));
    }

    public static function getUpcomingBlackouts(?string $branchId = null, int $limit = 10): \Illuminate\Support\Collection
    {
        return static::active()
            ->forBranch($branchId)
            ->upcoming()
            ->orderByDate()
            ->limit($limit)
            ->get();
    }
}
