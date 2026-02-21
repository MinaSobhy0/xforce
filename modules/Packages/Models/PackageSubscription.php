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
use Carbon\Carbon;

class PackageSubscription extends BaseModel
{
    use HasTenancy, HasActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'package_id',
        'invoice_id',
        'status',
        'purchased_at',
        'expires_at',
        'frozen_at',
        'frozen_until',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
        'expires_at' => 'datetime',
        'frozen_at' => 'datetime',
        'frozen_until' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
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

    public function usages(): HasMany
    {
        return $this->hasMany(PackageSessionUsage::class, 'subscription_id');
    }

    // Session tracking
    public function getSessionsUsedAttribute(): int
    {
        return $this->usages()->count();
    }

    public function getSessionsRemainingAttribute(): int
    {
        $totalSessions = $this->package?->total_sessions ?? 0;
        return max(0, $totalSessions - $this->sessions_used);
    }

    public function getSessionsUsedByService(string $serviceId): int
    {
        return $this->usages()->where('service_id', $serviceId)->count();
    }

    public function getSessionsRemainingByService(string $serviceId): int
    {
        $totalForService = $this->package?->getServiceQuantity($serviceId) ?? 0;
        return max(0, $totalForService - $this->getSessionsUsedByService($serviceId));
    }

    public function hasRemainingSessionsForService(string $serviceId): bool
    {
        return $this->getSessionsRemainingByService($serviceId) > 0;
    }

    public function getUsageProgressAttribute(): float
    {
        $total = $this->package?->total_sessions ?? 0;
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
}
