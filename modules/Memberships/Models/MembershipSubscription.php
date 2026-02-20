<?php

namespace Modules\Memberships\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Patients\Models\Patient;
use Modules\Billing\Models\Invoice;
use Carbon\Carbon;

class MembershipSubscription extends BaseModel
{
    use HasTenancy, HasActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'membership_id',
        'billing_cycle',
        'status',
        'started_at',
        'expires_at',
        'auto_renew',
        'renewal_invoice_id',
        'frozen_at',
        'frozen_until',
        'cancelled_at',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'frozen_at' => 'datetime',
        'frozen_until' => 'datetime',
        'cancelled_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    // Status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FROZEN = 'frozen';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_EXPIRED => 'Expired',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_FROZEN => 'Frozen',
    ];

    public const STATUS_COLORS = [
        self::STATUS_ACTIVE => 'success',
        self::STATUS_EXPIRED => 'danger',
        self::STATUS_CANCELLED => 'gray',
        self::STATUS_FROZEN => 'warning',
    ];

    // Billing cycle constants
    public const BILLING_MONTHLY = 'monthly';
    public const BILLING_YEARLY = 'yearly';

    public const BILLING_CYCLES = [
        self::BILLING_MONTHLY => 'Monthly',
        self::BILLING_YEARLY => 'Yearly',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (MembershipSubscription $subscription) {
            if (empty($subscription->status)) {
                $subscription->status = self::STATUS_ACTIVE;
            }
            if (empty($subscription->started_at)) {
                $subscription->started_at = now();
            }
            // Calculate expiry based on billing cycle
            if (empty($subscription->expires_at) && $subscription->billing_cycle) {
                $subscription->expires_at = $subscription->billing_cycle === self::BILLING_YEARLY
                    ? now()->addYear()
                    : now()->addMonth();
            }
            if (!isset($subscription->auto_renew)) {
                $subscription->auto_renew = config('memberships.auto_renewal_enabled', true);
            }
        });
    }

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function renewalInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'renewal_invoice_id');
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

    public function getBillingCycleLabelAttribute(): string
    {
        return self::BILLING_CYCLES[$this->billing_cycle] ?? $this->billing_cycle;
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }
        return max(0, now()->diffInDays($this->expires_at, false));
    }

    public function getDiscountPercentageAttribute(): int
    {
        return $this->membership?->discount_percentage ?? 0;
    }

    public function getLoyaltyMultiplierAttribute(): float
    {
        return $this->membership?->loyalty_multiplier ?? 1.0;
    }

    // Status checks
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isFrozen(): bool
    {
        return $this->status === self::STATUS_FROZEN;
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (!$this->expires_at || !$this->isActive()) {
            return false;
        }
        return $this->expires_at->lte(now()->addDays($days));
    }

    public function canApplyDiscount(): bool
    {
        return $this->isActive() && $this->discount_percentage > 0;
    }

    // State transitions
    public function canTransitionTo(string $status): bool
    {
        $transitions = [
            self::STATUS_ACTIVE => [self::STATUS_EXPIRED, self::STATUS_CANCELLED, self::STATUS_FROZEN],
            self::STATUS_FROZEN => [self::STATUS_ACTIVE, self::STATUS_EXPIRED, self::STATUS_CANCELLED],
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

        $this->status = self::STATUS_FROZEN;
        $this->frozen_at = now();
        $this->frozen_until = $until ?? now()->addMonth();

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

    public function renew(?Invoice $invoice = null): bool
    {
        if (!in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_EXPIRED])) {
            return false;
        }

        $this->status = self::STATUS_ACTIVE;
        $this->started_at = now();
        $this->expires_at = $this->billing_cycle === self::BILLING_YEARLY
            ? now()->addYear()
            : now()->addMonth();

        if ($invoice) {
            $this->renewal_invoice_id = $invoice->id;
        }

        return $this->save();
    }

    public function checkAndMarkExpired(): bool
    {
        if ($this->expires_at?->isPast() && in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_FROZEN])) {
            return $this->transitionTo(self::STATUS_EXPIRED);
        }
        return false;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->active()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days));
    }

    public function scopeAutoRenewable($query)
    {
        return $query->where('auto_renew', true);
    }

    public function scopeForPatient($query, string $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeByTier($query, string $tier)
    {
        return $query->whereHas('membership', fn ($q) => $q->where('tier', $tier));
    }
}
