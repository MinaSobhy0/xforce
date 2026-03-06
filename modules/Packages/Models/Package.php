<?php

namespace Modules\Packages\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasTranslation;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends BaseModel
{
    use HasTenancy, HasTranslation, HasActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'base_price_minor',
        'validity_days',
        'min_deposit_percent',
        'is_transferable',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'base_price_minor' => 'integer',
        'validity_days' => 'integer',
        'min_deposit_percent' => 'integer',
        'is_transferable' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public array $translatable = ['name', 'description'];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Package $package) {
            if (empty($package->validity_days)) {
                $package->validity_days = config('packages.default_validity_days', 365);
            }
            if (!isset($package->is_active)) {
                $package->is_active = true;
            }
        });
    }

    // Relationships
    public function items(): HasMany
    {
        return $this->hasMany(PackageItem::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(PackageSubscription::class);
    }

    // Accessors
    public function getTranslatedNameAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale())
            ?? $this->getTranslation('name', 'en')
            ?? '';
    }

    public function getFormattedPriceAttribute(): string
    {
        return format_money($this->base_price_minor);
    }

    /**
     * Get calculated total from all items
     */
    public function getCalculatedTotalMinorAttribute(): int
    {
        return $this->items->sum('total_price_minor');
    }

    /**
     * Get the effective price (calculated from items if items exist, else base_price)
     */
    public function getEffectivePriceMinorAttribute(): int
    {
        $calculated = $this->calculated_total_minor;
        return $calculated > 0 ? $calculated : $this->base_price_minor;
    }

    /**
     * Recalculate and update base_price_minor from items
     */
    public function recalculatePrice(): void
    {
        $this->base_price_minor = $this->items()->sum(
            \Illuminate\Support\Facades\DB::raw('quantity * unit_price_minor')
        );
        $this->saveQuietly();
    }

    /**
     * Get total sessions across all items (session-based items only)
     */
    public function getTotalSessionsAttribute(): int
    {
        return $this->items
            ->where('consumption_type', PackageItem::CONSUMPTION_SESSIONS)
            ->sum('quantity');
    }

    /**
     * Get total pulses across all items (pulse-based items only)
     */
    public function getTotalPulsesAttribute(): int
    {
        return $this->items
            ->where('consumption_type', PackageItem::CONSUMPTION_PULSES)
            ->sum(fn ($item) => $item->quantity * ($item->pulses_per_session ?? 1));
    }

    public function getActiveSubscriptionsCountAttribute(): int
    {
        return $this->subscriptions()
            ->whereIn('status', [
                PackageSubscription::STATUS_ACTIVE,
                PackageSubscription::STATUS_FROZEN,
            ])
            ->count();
    }

    // Query helpers
    public function getServiceQuantity(string $serviceId): int
    {
        return $this->items()
            ->where('service_id', $serviceId)
            ->value('quantity') ?? 0;
    }

    public function hasService(string $serviceId): bool
    {
        return $this->items()->where('service_id', $serviceId)->exists();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->whereRaw("name->>'en' ILIKE ?", ["%{$term}%"])
                ->orWhereRaw("name->>'ar' ILIKE ?", ["%{$term}%"]);
        });
    }

    /**
     * Check if package has any session-based items
     */
    public function hasSessionBasedItems(): bool
    {
        return $this->items->contains('consumption_type', PackageItem::CONSUMPTION_SESSIONS);
    }

    /**
     * Check if package has any pulse-based items
     */
    public function hasPulseBasedItems(): bool
    {
        return $this->items->contains(fn ($item) =>
            $item->consumption_type === PackageItem::CONSUMPTION_PULSES ||
            ($item->pulses_per_session ?? 0) > 1
        );
    }

    /**
     * Check if package is primarily pulse-based (all items are pulse-based)
     */
    public function isPulseBased(): bool
    {
        if ($this->items->isEmpty()) {
            return false;
        }
        // Check if all items are pulse-based by consumption_type OR have pulses_per_session > 1
        return $this->items->every(fn ($item) =>
            $item->consumption_type === PackageItem::CONSUMPTION_PULSES ||
            ($item->pulses_per_session ?? 0) > 1
        );
    }

    /**
     * Check if package is primarily session-based (all items are session-based)
     */
    public function isSessionBased(): bool
    {
        if ($this->items->isEmpty()) {
            return true; // Default to session-based
        }
        return $this->items->every(fn ($item) => $item->consumption_type === PackageItem::CONSUMPTION_SESSIONS);
    }

    /**
     * Get consumption type label for display
     */
    public function getConsumptionTypeAttribute(): string
    {
        if ($this->isPulseBased()) {
            return 'pulses';
        }
        if ($this->hasPulseBasedItems() && $this->hasSessionBasedItems()) {
            return 'mixed';
        }
        return 'sessions';
    }

    /**
     * Get total consumption units (pulses or sessions) based on package type
     */
    public function getTotalConsumptionAttribute(): int
    {
        if ($this->isPulseBased()) {
            return $this->total_pulses;
        }
        return $this->total_sessions;
    }

    /**
     * Get consumption unit label
     */
    public function getConsumptionUnitAttribute(): string
    {
        return $this->isPulseBased() ? 'pulses' : 'sessions';
    }

    public function getMinDepositAmountAttribute(): int
    {
        if ($this->min_deposit_percent <= 0) {
            return 0;
        }
        return (int) ceil($this->effective_price_minor * $this->min_deposit_percent / 100);
    }

    public function requiresFullPayment(): bool
    {
        return $this->min_deposit_percent <= 0 || $this->min_deposit_percent >= 100;
    }
}
