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
        'type',
        'base_price_minor',
        'validity_days',
        'is_transferable',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'base_price_minor' => 'integer',
        'validity_days' => 'integer',
        'is_transferable' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public array $translatable = ['name', 'description'];

    // Package types
    public const TYPE_SESSION_BUNDLE = 'session_bundle';
    public const TYPE_VALUE_BUNDLE = 'value_bundle';

    public const TYPES = [
        self::TYPE_SESSION_BUNDLE => 'Session Bundle',
        self::TYPE_VALUE_BUNDLE => 'Value Bundle',
    ];

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

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getTotalSessionsAttribute(): int
    {
        if ($this->type !== self::TYPE_SESSION_BUNDLE) {
            return 0;
        }
        return $this->items->sum('quantity');
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

    public function scopeSessionBundles($query)
    {
        return $query->where('type', self::TYPE_SESSION_BUNDLE);
    }

    public function scopeValueBundles($query)
    {
        return $query->where('type', self::TYPE_VALUE_BUNDLE);
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
}
