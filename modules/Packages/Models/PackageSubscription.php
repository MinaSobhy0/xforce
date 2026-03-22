<?php

namespace Modules\Packages\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Patients\Models\Patient;
use Modules\Billing\Models\Invoice;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\JournalEntry;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;
use Carbon\Carbon;

class PackageSubscription extends BaseModel
{
    use HasTenancy, HasActivity, SoftDeletes;

    // SECURITY: Only allow non-sensitive fields for mass assignment
    // Financial and status fields must be modified via controlled service methods
    protected $fillable = [
        'tenant_id',
        'patient_id',
        'package_id',
        'invoice_id',
        'package_price_minor', // Set once at creation from package
        'activation_rule',
        'unearned_revenue_account_id',
        'branch_id',
        'created_by_user_id',
        'purchased_at',
        'expires_at',
        'frozen_at',
        'frozen_until',
        'cancellation_reason',
        'notes',
    ];

    // SECURITY: Guard financial and status fields to prevent payment/revenue fraud
    // These track money flow - manipulation enables fraudulent revenue recognition or fake payments
    protected $guarded = [
        'id',
        // Payment tracking - manipulation fakes payment records
        'deposit_paid_minor',
        'balance_remaining_minor',
        // Revenue recognition - manipulation inflates/manipulates recognized revenue
        'recognized_revenue_minor',
        'unrecognized_revenue_minor',
        // Status workflow - manipulation bypasses proper state transitions
        'status',
        // Completion tracking - manipulation falsifies completion/cancellation dates
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'package_price_minor' => 'integer',
        'deposit_paid_minor' => 'integer',
        'balance_remaining_minor' => 'integer',
        'recognized_revenue_minor' => 'integer',
        'unrecognized_revenue_minor' => 'integer',
        'purchased_at' => 'datetime',
        'expires_at' => 'datetime',
        'frozen_at' => 'datetime',
        'frozen_until' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Activation rules
    public const ACTIVATION_IMMEDIATE = 'immediate';
    public const ACTIVATION_PAID_IN_FULL = 'paid_in_full';

    public const ACTIVATION_RULES = [
        self::ACTIVATION_IMMEDIATE => 'Activate Immediately',
        self::ACTIVATION_PAID_IN_FULL => 'Activate After Full Payment',
    ];

    // Status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FROZEN = 'frozen';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_EXPIRED => 'Expired',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_FROZEN => 'Frozen',
    ];

    public const STATUS_COLORS = [
        self::STATUS_ACTIVE => 'success',
        self::STATUS_COMPLETED => 'info',
        self::STATUS_EXPIRED => 'danger',
        self::STATUS_CANCELLED => 'gray',
        self::STATUS_FROZEN => 'warning',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (PackageSubscription $subscription) {
            if (empty($subscription->status)) {
                $subscription->status = self::STATUS_ACTIVE;
            }
            if (empty($subscription->purchased_at)) {
                $subscription->purchased_at = now();
            }
            // Calculate expiry based on package validity
            if (empty($subscription->expires_at) && $subscription->package) {
                $subscription->expires_at = now()->addDays($subscription->package->validity_days);
            }
        });
    }

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get payments for this subscription's invoice.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(\Modules\Billing\Models\Payment::class, 'invoice_id', 'invoice_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PackageSessionUsage::class, 'subscription_id');
    }

    public function unearnedRevenueAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'unearned_revenue_account_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    /**
     * Get appointments linked to this package subscription.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(\Modules\Booking\Models\Appointment::class, 'package_subscription_id');
    }

    // Session tracking
    public function getSessionsUsedAttribute(): int
    {
        // For pulse-based packages, use the sum of pulse quantities
        if ($this->package?->isPulseBased()) {
            return $this->pulses_used;
        }
        // For session-based packages, count records
        return $this->usages()->count();
    }

    public function getSessionsRemainingAttribute(): int
    {
        // For pulse-based packages, use total pulses
        if ($this->package?->isPulseBased()) {
            return $this->pulses_remaining;
        }
        $totalSessions = $this->package?->total_sessions ?? 0;
        return max(0, $totalSessions - $this->sessions_used);
    }

    public function getSessionsUsedByService(string $serviceId): int
    {
        // Check if the package item for this service is pulse-based
        $packageItem = $this->package?->items()
            ->where('service_id', $serviceId)
            ->first();

        if ($packageItem?->isPulseBased()) {
            // Sum the quantity_used for pulse-based services
            return $this->usages()
                ->where('service_id', $serviceId)
                ->where('unit_type', 'pulse')
                ->sum('quantity_used');
        }

        // For session-based services, count records
        return $this->usages()->where('service_id', $serviceId)->count();
    }

    public function getSessionsRemainingByService(string $serviceId): int
    {
        // Check if the package item for this service is pulse-based
        $packageItem = $this->package?->items()
            ->where('service_id', $serviceId)
            ->first();

        if ($packageItem?->isPulseBased()) {
            // For pulse-based, use total_units which includes pulses_per_session calculation
            $totalForService = $packageItem->total_units;
            return max(0, $totalForService - $this->getSessionsUsedByService($serviceId));
        }

        $totalForService = $this->package?->getServiceQuantity($serviceId) ?? 0;
        return max(0, $totalForService - $this->getSessionsUsedByService($serviceId));
    }

    public function hasRemainingSessionsForService(string $serviceId): bool
    {
        return $this->getSessionsRemainingByService($serviceId) > 0;
    }

    // Booked sessions tracking (scheduled but not completed)
    public function getSessionsBookedAttribute(): int
    {
        return $this->appointments()
            ->where('is_package_session', true)
            ->whereIn('status', [
                \Modules\Booking\Models\Appointment::STATUS_SCHEDULED,
                \Modules\Booking\Models\Appointment::STATUS_CONFIRMED,
                \Modules\Booking\Models\Appointment::STATUS_CHECKED_IN,
                \Modules\Booking\Models\Appointment::STATUS_IN_PROGRESS,
            ])
            ->count();
    }

    public function getSessionsBookedByService(string $serviceId): int
    {
        return $this->appointments()
            ->where('is_package_session', true)
            ->where('service_id', $serviceId)
            ->whereIn('status', [
                \Modules\Booking\Models\Appointment::STATUS_SCHEDULED,
                \Modules\Booking\Models\Appointment::STATUS_CONFIRMED,
                \Modules\Booking\Models\Appointment::STATUS_CHECKED_IN,
                \Modules\Booking\Models\Appointment::STATUS_IN_PROGRESS,
            ])
            ->count();
    }

    /**
     * Get truly available sessions (total - used - booked).
     * For pulse-based packages, booked appointments don't reserve specific pulse counts.
     */
    public function getSessionsAvailableAttribute(): int
    {
        // For pulse-based packages, available = remaining pulses
        // (booked appointments don't reserve specific pulse counts until completed)
        if ($this->package?->isPulseBased()) {
            return $this->pulses_remaining;
        }

        $totalSessions = $this->package?->total_sessions ?? 0;
        return max(0, $totalSessions - $this->sessions_used - $this->sessions_booked);
    }

    public function getSessionsAvailableByService(string $serviceId): int
    {
        // Check if the package item for this service is pulse-based
        $packageItem = $this->package?->items()
            ->where('service_id', $serviceId)
            ->first();

        if ($packageItem?->isPulseBased()) {
            // For pulse-based, available = remaining (booked appointments don't reserve pulses)
            return $this->getSessionsRemainingByService($serviceId);
        }

        $totalForService = $this->package?->getServiceQuantity($serviceId) ?? 0;
        return max(0, $totalForService - $this->getSessionsUsedByService($serviceId) - $this->getSessionsBookedByService($serviceId));
    }

    public function getUsageProgressAttribute(): float
    {
        $package = $this->package;

        if ($package?->isPulseBased()) {
            $total = $package->total_pulses ?? 0;
            if ($total <= 0) {
                return 100;
            }
            return round(($this->pulses_used / $total) * 100, 1);
        }

        $total = $package?->total_sessions ?? 0;
        if ($total <= 0) {
            return 100;
        }
        return round(($this->sessions_used / $total) * 100, 1);
    }

    // Status checks
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isFrozen(): bool
    {
        return $this->status === self::STATUS_FROZEN;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canUseSession(): bool
    {
        return $this->isActive() && !$this->isExpiringSoon() && $this->sessions_remaining > 0;
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        return $this->expires_at->lte(now()->addDays($days));
    }

    // State transitions
    public function canTransitionTo(string $status): bool
    {
        $transitions = [
            self::STATUS_ACTIVE => [self::STATUS_COMPLETED, self::STATUS_EXPIRED, self::STATUS_CANCELLED, self::STATUS_FROZEN],
            self::STATUS_FROZEN => [self::STATUS_ACTIVE, self::STATUS_EXPIRED, self::STATUS_CANCELLED],
            self::STATUS_COMPLETED => [],
            self::STATUS_EXPIRED => [],
            self::STATUS_CANCELLED => [],
        ];

        return in_array($status, $transitions[$this->status] ?? []);
    }

    public function transitionTo(string $status): bool
    {
        if (!$this->canTransitionTo($status)) {
            return false;
        }

        $this->status = $status;

        switch ($status) {
            case self::STATUS_COMPLETED:
                $this->completed_at = now();
                break;
            case self::STATUS_CANCELLED:
                $this->cancelled_at = now();
                break;
        }

        return $this->save();
    }

    public function freeze(?Carbon $until = null): bool
    {
        if (!$this->canTransitionTo(self::STATUS_FROZEN)) {
            return false;
        }

        $maxFreezeDays = config('packages.max_freeze_days', 90);
        $until = $until ?? now()->addDays($maxFreezeDays);

        $this->status = self::STATUS_FROZEN;
        $this->frozen_at = now();
        $this->frozen_until = $until;

        return $this->save();
    }

    public function unfreeze(): bool
    {
        if (!$this->isFrozen()) {
            return false;
        }

        // Extend expiry by frozen duration
        if ($this->frozen_at && $this->expires_at) {
            $frozenDays = $this->frozen_at->diffInDays(now());
            $this->expires_at = $this->expires_at->addDays($frozenDays);
        }

        $this->status = self::STATUS_ACTIVE;
        $this->frozen_at = null;
        $this->frozen_until = null;

        return $this->save();
    }

    public function cancel(?string $reason = null): bool
    {
        if (!$this->canTransitionTo(self::STATUS_CANCELLED)) {
            return false;
        }

        $this->cancellation_reason = $reason;
        return $this->transitionTo(self::STATUS_CANCELLED);
    }

    public function checkAndMarkComplete(): bool
    {
        if ($this->sessions_remaining <= 0 && $this->isActive()) {
            return $this->transitionTo(self::STATUS_COMPLETED);
        }
        return false;
    }

    public function checkAndMarkExpired(): bool
    {
        if ($this->expires_at?->isPast() && in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_FROZEN])) {
            return $this->transitionTo(self::STATUS_EXPIRED);
        }
        return false;
    }

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }
        return max(0, now()->diffInDays($this->expires_at, false));
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeWithRemainingSessions($query)
    {
        return $query->active();
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->active()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days));
    }

    public function scopeForPatient($query, string $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeForService($query, string $serviceId)
    {
        return $query->whereHas('package.items', function ($q) use ($serviceId) {
            $q->where('service_id', $serviceId);
        });
    }

    // Payment tracking helpers
    public function isFullyPaid(): bool
    {
        return $this->balance_remaining_minor <= 0;
    }

    public function hasBalance(): bool
    {
        return $this->balance_remaining_minor > 0;
    }

    public function getPaymentProgressAttribute(): float
    {
        if ($this->package_price_minor <= 0) {
            return 100;
        }
        $paid = $this->package_price_minor - $this->balance_remaining_minor;
        return round(($paid / $this->package_price_minor) * 100, 1);
    }

    public function getTotalPaidAttribute(): int
    {
        return $this->package_price_minor - $this->balance_remaining_minor;
    }

    public function getFormattedBalanceAttribute(): string
    {
        return format_money($this->balance_remaining_minor);
    }

    public function getFormattedPriceAttribute(): string
    {
        return format_money($this->package_price_minor);
    }

    /**
     * Get package items with usage data for this subscription.
     */
    public function getItemsWithUsageAttribute(): \Illuminate\Support\Collection
    {
        if (!$this->package) {
            return collect();
        }

        return $this->package->items->map(function ($item) {
            return (object) [
                'service_name' => $item->service?->translated_name ?? '-',
                'service_id' => $item->service_id,
                'quantity' => $item->quantity,
                'consumption_type' => $item->consumption_type,
                'unit_price' => $item->formatted_unit_price,
                'total_units' => $item->total_units,
                'used' => $this->getSessionsUsedByService($item->service_id),
                'booked' => $this->getSessionsBookedByService($item->service_id),
                'remaining' => $this->getSessionsRemainingByService($item->service_id),
                'is_pulse_based' => $item->isPulseBased(),
            ];
        });
    }

    public function recordPayment(int $amountMinor): void
    {
        $this->balance_remaining_minor = max(0, $this->balance_remaining_minor - $amountMinor);
        $this->deposit_paid_minor += $amountMinor;
        $this->save();
    }

    // Activation rule helpers
    public function canBeActivated(): bool
    {
        if ($this->activation_rule === self::ACTIVATION_IMMEDIATE) {
            return true;
        }
        return $this->isFullyPaid();
    }

    public function requiresFullPaymentForActivation(): bool
    {
        return $this->activation_rule === self::ACTIVATION_PAID_IN_FULL;
    }

    // Revenue recognition helpers
    public function getPerSessionValueAttribute(): int
    {
        $totalSessions = $this->package?->total_sessions ?? 0;
        if ($totalSessions <= 0) {
            return 0;
        }
        return (int) floor($this->package_price_minor / $totalSessions);
    }

    public function recordRevenueRecognition(int $amountMinor): void
    {
        $this->recognized_revenue_minor += $amountMinor;
        $this->unrecognized_revenue_minor = max(0, $this->unrecognized_revenue_minor - $amountMinor);
        $this->save();
    }

    // Session usage with consumption tracking
    public function recordUsage(
        int $serviceId,
        ?int $appointmentId = null,
        int $quantityUsed = 1,
        string $unitType = 'session',
        ?string $notes = null
    ): PackageSessionUsage {
        $usage = $this->usages()->create([
            'tenant_id' => $this->tenant_id,
            'service_id' => $serviceId,
            'appointment_id' => $appointmentId,
            'used_at' => now(),
            'quantity_used' => $quantityUsed,
            'unit_type' => $unitType,
            'notes' => $notes,
        ]);

        // Check if all sessions used, mark as completed
        $this->checkAndMarkComplete();

        // Load the appointment if provided
        $appointment = $appointmentId
            ? \Modules\Booking\Models\Appointment::find($appointmentId)
            : null;

        // Fire event for revenue recognition
        \Modules\Packages\Events\PackageSessionUsed::dispatch($this, $usage, $appointment);

        return $usage;
    }

    // Pulse-based consumption tracking
    public function getPulsesUsedAttribute(): int
    {
        return $this->usages()
            ->where('unit_type', 'pulse')
            ->sum('quantity_used');
    }

    public function getPulsesRemainingAttribute(): int
    {
        $totalPulses = $this->package?->total_pulses ?? 0;
        return max(0, $totalPulses - $this->pulses_used);
    }

    public function getConsumptionUsedAttribute(): int
    {
        if ($this->package?->isPulseBased()) {
            return $this->pulses_used;
        }
        return $this->sessions_used;
    }

    public function getConsumptionRemainingAttribute(): int
    {
        if ($this->package?->isPulseBased()) {
            return $this->pulses_remaining;
        }
        return $this->sessions_remaining;
    }

    public function getConsumptionTotalAttribute(): int
    {
        if ($this->package?->isPulseBased()) {
            return $this->package->total_pulses;
        }
        return $this->package?->total_sessions ?? 0;
    }
}
