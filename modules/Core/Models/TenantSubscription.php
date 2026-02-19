<?php

namespace Modules\Core\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantSubscription extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'plan_name',
        'plan_code',
        'status',
        'price',
        'currency',
        'billing_cycle',
        'started_at',
        'expires_at',
        'auto_renew',
        'features',
        'limits',
        'payment_method',
        'payment_provider',
        'payment_provider_id',
        'last_payment_at',
        'next_payment_at',
        'failed_payments_count',
        'grace_period_ends_at',
        'canceled_at',
        'cancellation_reason',
        'meta',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'limits' => 'array',
        'meta' => 'array',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_payment_at' => 'datetime',
        'next_payment_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'canceled_at' => 'datetime',
        'auto_renew' => 'boolean',
        'failed_payments_count' => 'integer',
        'status' => SubscriptionStatus::class,
        'billing_cycle' => BillingCycle::class,
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', SubscriptionStatus::ACTIVE);
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::ACTIVE &&
               (!$this->expires_at || $this->expires_at->isFuture());
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isInGracePeriod(): bool
    {
        return $this->grace_period_ends_at &&
               $this->grace_period_ends_at->isFuture() &&
               $this->isExpired();
    }

    public function isCanceled(): bool
    {
        return $this->status === SubscriptionStatus::CANCELED;
    }

    public function getRemainingDays(): int
    {
        if (!$this->expires_at) {
            return -1; // Unlimited
        }

        return max(0, now()->diffInDays($this->expires_at, false));
    }

    public function getUsageLimit(string $feature): ?int
    {
        return data_get($this->limits, $feature);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    public function getNextBillingDate(): ?\Carbon\Carbon
    {
        return $this->next_payment_at;
    }

    public function calculateNextBillingDate(): \Carbon\Carbon
    {
        $baseDate = $this->expires_at ?: now();

        return match ($this->billing_cycle) {
            BillingCycle::MONTHLY => $baseDate->addMonth(),
            BillingCycle::QUARTERLY => $baseDate->addMonths(3),
            BillingCycle::YEARLY => $baseDate->addYear(),
            default => $baseDate->addMonth(),
        };
    }
}

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case CANCELED = 'canceled';
    case SUSPENDED = 'suspended';
    case PENDING = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => __('Active'),
            self::EXPIRED => __('Expired'),
            self::CANCELED => __('Canceled'),
            self::SUSPENDED => __('Suspended'),
            self::PENDING => __('Pending'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::EXPIRED => 'danger',
            self::CANCELED => 'gray',
            self::SUSPENDED => 'warning',
            self::PENDING => 'info',
        };
    }
}

enum BillingCycle: string
{
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::MONTHLY => __('Monthly'),
            self::QUARTERLY => __('Quarterly'),
            self::YEARLY => __('Yearly'),
        };
    }

    public function months(): int
    {
        return match ($this) {
            self::MONTHLY => 1,
            self::QUARTERLY => 3,
            self::YEARLY => 12,
        };
    }
}