<?php

namespace App\Models;

use App\Traits\HasPostgresBoolean;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\Tenant;
use Spatie\Translatable\HasTranslations;

class SubscriptionPlan extends Model
{
    use HasPostgresBoolean, HasTranslations, SoftDeletes;

    /**
     * The database connection that should be used by the model.
     * SubscriptionPlan lives in public schema, not tenant schema.
     */
    protected $connection = 'central';

    protected $table = 'public.subscription_plans';

    public array $translatable = ['name', 'description'];

    /**
     * Supported countries with their currencies and default tax rates.
     */
    public const COUNTRIES = [
        'EG' => ['name' => 'Egypt', 'currency' => 'EGP', 'default_tax' => 14],
        'SA' => ['name' => 'Saudi Arabia', 'currency' => 'SAR', 'default_tax' => 15],
        'AE' => ['name' => 'UAE', 'currency' => 'AED', 'default_tax' => 5],
        'KW' => ['name' => 'Kuwait', 'currency' => 'KWD', 'default_tax' => 0],
        'QA' => ['name' => 'Qatar', 'currency' => 'QAR', 'default_tax' => 0],
        'BH' => ['name' => 'Bahrain', 'currency' => 'BHD', 'default_tax' => 10],
        'OM' => ['name' => 'Oman', 'currency' => 'OMR', 'default_tax' => 5],
        'JO' => ['name' => 'Jordan', 'currency' => 'JOD', 'default_tax' => 16],
        'LB' => ['name' => 'Lebanon', 'currency' => 'USD', 'default_tax' => 11],
    ];

    // SECURITY: Only allow non-sensitive fields for mass assignment
    // Pricing, limits, and features must be set explicitly via admin operations
    protected $fillable = [
        'code',
        'name',
        'description',
        'currency',
        'trial_days',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    // SECURITY: Guard all pricing, limit, and feature fields to prevent subscription fraud
    // These define what tenants get - must only be modified through verified admin operations
    protected $guarded = [
        'id',
        // Pricing - manipulation allows free/discounted access
        'price_monthly_minor',
        'price_yearly_minor',
        'prices',
        // Hard limits - manipulation allows exceeding plan restrictions
        'max_users',
        'max_branches',
        'max_patients',
        'max_storage_mb',
        'max_equipment',
        'max_products',
        'max_treatments',
        'max_api_calls_daily',
        // Soft limits - manipulation allows unlimited messaging/appointments
        'max_appointments_monthly',
        'max_whatsapp_monthly',
        'max_sms_monthly',
        'max_emails_monthly',
        'max_campaign_recipients',
        // Overage pricing - manipulation to avoid overage charges
        'overage_appointment_minor',
        'overage_whatsapp_minor',
        'overage_sms_minor',
        'overage_email_minor',
        'overage_storage_gb_minor',
        // Feature flags - manipulation to enable premium features
        'allow_white_label',
        'allow_custom_domain',
        'allow_data_export',
        'allow_api_access',
        'has_priority_support',
        'data_retention_days',
        'max_concurrent_sessions',
        // Modules - manipulation to enable premium modules
        'included_module_codes',
    ];

    protected $casts = [
        'price_monthly_minor' => 'integer',
        'price_yearly_minor' => 'integer',
        'prices' => 'array',
        'trial_days' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'max_users' => 'integer',
        'max_branches' => 'integer',
        'max_patients' => 'integer',
        'max_storage_mb' => 'integer',
        'max_equipment' => 'integer',
        'max_products' => 'integer',
        'max_treatments' => 'integer',
        'max_api_calls_daily' => 'integer',
        'max_appointments_monthly' => 'integer',
        'max_whatsapp_monthly' => 'integer',
        'max_sms_monthly' => 'integer',
        'max_emails_monthly' => 'integer',
        'max_campaign_recipients' => 'integer',
        'overage_appointment_minor' => 'integer',
        'overage_whatsapp_minor' => 'integer',
        'overage_sms_minor' => 'integer',
        'overage_email_minor' => 'integer',
        'overage_storage_gb_minor' => 'integer',
        'allow_white_label' => 'boolean',
        'allow_custom_domain' => 'boolean',
        'allow_data_export' => 'boolean',
        'allow_api_access' => 'boolean',
        'has_priority_support' => 'boolean',
        'data_retention_days' => 'integer',
        'max_concurrent_sessions' => 'integer',
        'included_module_codes' => 'array',
    ];

    protected function getPostgresBooleanFields(): array
    {
        return ['is_active', 'is_featured', 'allow_white_label', 'allow_custom_domain', 'allow_data_export', 'allow_api_access', 'has_priority_support'];
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'subscription_plan_id');
    }

    public function scopeActive($query)
    {
        return $query->whereRaw('is_active = true');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function getActiveTenantCountAttribute(): int
    {
        return $this->tenants()
            ->whereIn('status', ['active', 'trial'])
            ->count();
    }

    public function getMrrAttribute(): int
    {
        return $this->tenants()
            ->where('status', 'active')
            ->count() * $this->price_monthly_minor;
    }

    public function getFormattedMonthlyPriceAttribute(): string
    {
        return number_format($this->price_monthly_minor / 100, 0).' '.($this->currency ?? 'EGP');
    }

    public function getFormattedYearlyPriceAttribute(): string
    {
        return number_format($this->price_yearly_minor / 100, 0).' '.($this->currency ?? 'EGP');
    }

    public function getYearlySavingsPercentAttribute(): int
    {
        if (! $this->price_monthly_minor || ! $this->price_yearly_minor) {
            return 0;
        }
        $monthlyTotal = $this->price_monthly_minor * 12;

        return (int) round((1 - ($this->price_yearly_minor / $monthlyTotal)) * 100);
    }

    public function hasModule(string $moduleCode): bool
    {
        return in_array($moduleCode, $this->included_module_codes ?? []);
    }

    /**
     * Get price for a specific country.
     */
    public function getPriceForCountry(string $countryCode, string $interval = 'monthly'): ?array
    {
        $countryCode = strtoupper($countryCode);
        $prices = $this->prices ?? [];
        $baseCurrency = $this->currency ?? 'EGP';
        $baseAmount = (int) ($interval === 'yearly' ? $this->price_yearly_minor : $this->price_monthly_minor);

        // A per-country override only counts when its cell for this interval is
        // actually filled in. The prices JSON keeps every country key present
        // with null values, so isset() alone is not enough — a blank cell must
        // fall through to the plan's base price instead of showing 0.
        //
        // NOTE: per-country prices are entered in WHOLE currency units in the
        // admin form (e.g. "40000" = 40,000 EGP), whereas the base columns are
        // stored in minor units. This method returns minor units everywhere, so
        // per-country values are scaled up by the currency divisor.
        $filled = fn ($v) => $v !== null && $v !== '';
        $toMinor = fn ($major, $currency) => (int) round(((float) $major) * currency_minor_divisor($currency));

        // 1. The tenant's own country, when priced.
        if ($filled($prices[$countryCode][$interval] ?? null)) {
            $currency = ($prices[$countryCode]['currency'] ?? null)
                ?: (self::COUNTRIES[$countryCode]['currency'] ?? $baseCurrency);

            return [
                'amount_minor' => $toMinor($prices[$countryCode][$interval], $currency),
                'currency' => $currency,
            ];
        }

        // 2. Egypt as the default region, when priced.
        if ($filled($prices['EG'][$interval] ?? null)) {
            $currency = ($prices['EG']['currency'] ?? null) ?: 'EGP';

            return [
                'amount_minor' => $toMinor($prices['EG'][$interval], $currency),
                'currency' => $currency,
            ];
        }

        // 3. The plan's base columns (kept in the plan's own currency).
        return [
            'amount_minor' => $baseAmount,
            'currency' => $baseCurrency,
        ];
    }

    /**
     * Get formatted price for a country.
     */
    public function getFormattedPriceForCountry(string $countryCode, string $interval = 'monthly'): string
    {
        $price = $this->getPriceForCountry($countryCode, $interval);
        // This price has its OWN currency (per-country pricing), not the
        // current tenant's, so pass it explicitly to get the right precision.
        $divisor = currency_minor_divisor($price['currency']);
        $decimals = $divisor > 1 ? (int) log10($divisor) : 0;

        return $price['currency'].' '.number_format($price['amount_minor'] / $divisor, $decimals);
    }

    /**
     * Check if plan has pricing for a specific country.
     */
    public function hasPriceForCountry(string $countryCode): bool
    {
        $countryCode = strtoupper($countryCode);

        return isset($this->prices[$countryCode]);
    }

    /**
     * Get tax rate for a specific country (as percentage, e.g., 14 for 14%).
     */
    public function getTaxRateForCountry(string $countryCode): float
    {
        $countryCode = strtoupper($countryCode);
        $prices = $this->prices ?? [];

        // Check if country has specific tax rate in plan
        if (isset($prices[$countryCode]['tax_rate'])) {
            return (float) $prices[$countryCode]['tax_rate'];
        }

        // Fallback to country's default tax rate
        return (float) (self::COUNTRIES[$countryCode]['default_tax'] ?? 14);
    }
}
