<?php

namespace App\Models;

use App\Traits\HasPostgresBoolean;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\Tenant;

class AddOn extends Model
{
    use HasFactory, SoftDeletes, HasPostgresBoolean;

    protected $connection = 'central';

    protected $table = 'public.add_ons';

    protected $fillable = [
        'code',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'icon',
        'monthly_price',
        'yearly_price',
        'prices',
        'is_active',
        'is_recurring',
        'billing_interval',
        'features',
        'limits',
        'sort_order',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'prices' => 'array',
        'is_active' => 'boolean',
        'is_recurring' => 'boolean',
        'features' => 'array',
        'limits' => 'array',
        'sort_order' => 'integer',
    ];

    /**
     * Supported countries with their currencies.
     */
    public const COUNTRIES = [
        'EG' => ['name' => 'Egypt', 'currency' => 'EGP'],
        'SA' => ['name' => 'Saudi Arabia', 'currency' => 'SAR'],
        'AE' => ['name' => 'UAE', 'currency' => 'AED'],
        'KW' => ['name' => 'Kuwait', 'currency' => 'KWD'],
        'QA' => ['name' => 'Qatar', 'currency' => 'QAR'],
        'BH' => ['name' => 'Bahrain', 'currency' => 'BHD'],
        'OM' => ['name' => 'Oman', 'currency' => 'OMR'],
        'JO' => ['name' => 'Jordan', 'currency' => 'JOD'],
        'LB' => ['name' => 'Lebanon', 'currency' => 'USD'],
    ];

    protected function getPostgresBooleanFields(): array
    {
        return ['is_active', 'is_recurring'];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_add_ons')
            ->withPivot(['activated_at', 'expires_at', 'billing_interval', 'price'])
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->whereRaw('is_active = true');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function getActiveSubscribersCountAttribute(): int
    {
        return $this->tenants()
            ->whereNull('tenant_add_ons.expires_at')
            ->orWhere('tenant_add_ons.expires_at', '>', now())
            ->count();
    }

    public function getTotalRevenueAttribute(): float
    {
        return $this->tenants()
            ->whereNotNull('tenant_add_ons.activated_at')
            ->sum('tenant_add_ons.price');
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'ar' && $this->name_ar
            ? $this->name_ar
            : $this->name;
    }

    public function getLocalizedDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ar' && $this->description_ar
            ? $this->description_ar
            : $this->description;
    }

    /**
     * Get price for a specific country.
     */
    public function getPriceForCountry(string $countryCode, string $interval = 'monthly'): ?array
    {
        $countryCode = strtoupper($countryCode);
        $prices = $this->prices ?? [];

        if (isset($prices[$countryCode])) {
            return [
                'amount' => $prices[$countryCode][$interval] ?? $prices[$countryCode]['monthly'] ?? 0,
                'currency' => $prices[$countryCode]['currency'] ?? self::COUNTRIES[$countryCode]['currency'] ?? 'USD',
            ];
        }

        // Fallback to default prices (EG)
        if (isset($prices['EG'])) {
            return [
                'amount' => $prices['EG'][$interval] ?? $prices['EG']['monthly'] ?? 0,
                'currency' => 'EGP',
            ];
        }

        // Fallback to legacy columns
        return [
            'amount' => $interval === 'yearly' ? $this->yearly_price : $this->monthly_price,
            'currency' => 'EGP',
        ];
    }

    /**
     * Get formatted price for a country.
     */
    public function getFormattedPriceForCountry(string $countryCode, string $interval = 'monthly'): string
    {
        $price = $this->getPriceForCountry($countryCode, $interval);
        return $price['currency'] . ' ' . number_format($price['amount'], 2);
    }

    /**
     * Check if add-on has pricing for a specific country.
     */
    public function hasPriceForCountry(string $countryCode): bool
    {
        $countryCode = strtoupper($countryCode);
        return isset($this->prices[$countryCode]);
    }
}
