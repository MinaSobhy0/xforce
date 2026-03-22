<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * TenantSubscription model - lives in public schema, not subject to tenant scoping.
 * Uses base Laravel Model and 'central' connection to avoid tenant schema switching.
 */
class TenantSubscription extends Model
{
    use SoftDeletes;

    protected $connection = 'central';
    protected $table = 'public.tenant_subscriptions';

    // SECURITY: Only allow non-sensitive fields for mass assignment
    // Status, pricing, and feature fields must be set via controlled service methods
    protected $fillable = [
        'tenant_id',
        'plan_name',
        'plan_code',
        'billing_cycle',
        'auto_renew',
        'payment_method',
        'payment_provider',
        'payment_provider_id',
        'meta',
    ];

    // SECURITY: Guard sensitive subscription fields to prevent subscription fraud
    // These control access and billing - manipulation could enable unauthorized features
    protected $guarded = [
        'id',
        // Status - manipulation allows reactivating expired/canceled subscriptions
        'status',
        // Pricing - manipulation allows changing subscription cost
        'price',
        'currency',
        // Dates - manipulation extends/modifies subscription period
        'started_at',
        'expires_at',
        'last_payment_at',
        'next_payment_at',
        'grace_period_ends_at',
        'canceled_at',
        // Features and limits - manipulation enables premium features without paying
        'features',
        'limits',
        // Payment tracking - resetting this bypasses payment failure handling
        'failed_payments_count',
        'cancellation_reason',
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