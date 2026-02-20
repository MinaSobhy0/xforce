<?php

namespace Modules\Memberships\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasTranslation;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Membership extends BaseModel
{
    use HasTenancy, HasTranslation, HasActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'tier',
        'price_monthly_minor',
        'price_yearly_minor',
        'discount_percentage',
        'included_sessions_monthly',
        'loyalty_multiplier',
        'priority_booking',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'price_monthly_minor' => 'integer',
        'price_yearly_minor' => 'integer',
        'discount_percentage' => 'integer',
        'included_sessions_monthly' => 'array',
        'loyalty_multiplier' => 'float',
        'priority_booking' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public array $translatable = ['name', 'description'];

    // Tier constants
    public const TIER_SILVER = 'silver';
    public const TIER_GOLD = 'gold';
    public const TIER_PLATINUM = 'platinum';
    public const TIER_DIAMOND = 'diamond';

    public const TIERS = [
        self::TIER_SILVER => 'Silver',
        self::TIER_GOLD => 'Gold',
        self::TIER_PLATINUM => 'Platinum',
        self::TIER_DIAMOND => 'Diamond',
    ];

    public const TIER_COLORS = [
        self::TIER_SILVER => 'gray',
        self::TIER_GOLD => 'warning',
        self::TIER_PLATINUM => 'info',
        self::TIER_DIAMOND => 'success',
    ];

    public const TIER_ICONS = [
        self::TIER_SILVER => 'heroicon-o-star',
        self::TIER_GOLD => 'heroicon-s-star',
        self::TIER_PLATINUM => 'heroicon-o-sparkles',
        self::TIER_DIAMOND => 'heroicon-s-sparkles',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Membership $membership) {
            if (!isset($membership->is_active)) {
                $membership->is_active = true;
            }
            if (empty($membership->loyalty_multiplier)) {
                $membership->loyalty_multiplier = config("memberships.default_loyalty_multipliers.{$membership->tier}", 1.0);
            }
            if (empty($membership->discount_percentage)) {
                $membership->discount_percentage = config("memberships.default_discounts.{$membership->tier}", 0);
            }
        });
    }

    // Relationships
    public function subscriptions(): HasMany
    {
        return $this->hasMany(MembershipSubscription::class);
    }

    public function activeSubscriptions(): HasMany
    {
        return $this->subscriptions()->where('status', MembershipSubscription::STATUS_ACTIVE);
    }

    // Accessors
    public function getTranslatedNameAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale())
            ?? $this->getTranslation('name', 'en')
            ?? '';
    }

    public function getTierLabelAttribute(): string
    {
        return self::TIERS[$this->tier] ?? $this->tier;
    }

    public function getTierColorAttribute(): string
    {
        return self::TIER_COLORS[$this->tier] ?? 'gray';
    }

    public function getTierIconAttribute(): string
    {
        return self::TIER_ICONS[$this->tier] ?? 'heroicon-o-star';
    }

    public function getFormattedMonthlyPriceAttribute(): string
    {
        return number_format($this->price_monthly_minor / 100, 2) . ' ' . config('app.currency_symbol', 'EGP');
    }

    public function getFormattedYearlyPriceAttribute(): string
    {
        return number_format($this->price_yearly_minor / 100, 2) . ' ' . config('app.currency_symbol', 'EGP');
    }

    public function getYearlySavingsMinorAttribute(): int
    {
        $monthlyTotal = $this->price_monthly_minor * 12;
        return max(0, $monthlyTotal - $this->price_yearly_minor);
    }

    public function getActiveSubscriptionsCountAttribute(): int
    {
        return $this->activeSubscriptions()->count();
    }

    // Query helpers
    public function getIncludedSessionsForTreatment(string $treatmentId): int
    {
        return $this->included_sessions_monthly[$treatmentId] ?? 0;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByTier($query, string $tier)
    {
        return $query->where('tier', $tier);
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
