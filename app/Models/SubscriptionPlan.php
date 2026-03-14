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
    use SoftDeletes, HasTranslations, HasPostgresBoolean;

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

    protected $fillable = [
        'code',
        'name',
        'description',
        'price_monthly_minor',
        'price_yearly_minor',
        'prices',
        'currency',
        'trial_days',
        'is_active',
        'is_featured',
        'sort_order',
        // Hard limits
        'max_users',
        'max_branches',
        'max_patients',
        'max_storage_mb',
        'max_equipment',
        'max_products',
        'max_treatments',
        'max_api_calls_daily',
        // Soft limits (monthly)
        'max_appointments_monthly',
        'max_whatsapp_monthly',
        'max_sms_monthly',
        'max_emails_monthly',
        'max_campaign_recipients',
        // Overage pricing
        'overage_appointment_minor',
        'overage_whatsapp_minor',
        'overage_sms_minor',
        'overage_email_minor',
        'overage_storage_gb_minor',
        // Feature flags
        'allow_white_label',
        'allow_custom_domain',
        'allow_data_export',
        'allow_api_access',
        'has_priority_support',
        'data_retention_days',
        'max_concurrent_sessions',
        // Modules included
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
        return number_format($this->price_monthly_minor / 100, 0) . ' ' . ($this->currency ?? 'EGP');
    }

    public function getFormattedYearlyPriceAttribute(): string
    {
        return number_format($this->price_yearly_minor / 100, 0) . ' ' . ($this->currency ?? 'EGP');
    }

    public function getYearlySavingsPercentAttribute(): int
    {
        if (!$this->price_monthly_minor || !$this->price_yearly_minor) {
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

        if (isset($prices[$countryCode])) {
            return [
                'amount_minor' => $prices[$countryCode][$interval] ?? $prices[$countryCode]['monthly'] ?? 0,
                'currency' => $prices[$countryCode]['currency'] ?? self::COUNTRIES[$countryCode]['currency'] ?? 'USD',
            ];
        }

        // Fallback to default prices (EG)
        if (isset($prices['EG'])) {
            return [
                'amount_minor' => $prices['EG'][$interval] ?? $prices['EG']['monthly'] ?? 0,
                'currency' => 'EGP',
            ];
        }

        // Fallback to legacy columns
        return [
            'amount_minor' => $interval === 'yearly' ? $this->price_yearly_minor : $this->price_monthly_minor,
            'currency' => $this->currency ?? 'EGP',
        ];
    }

    /**
     * Get formatted price for a country.
     */
    public function getFormattedPriceForCountry(string $countryCode, string $interval = 'monthly'): string
    {
        $price = $this->getPriceForCountry($countryCode, $interval);
        return $price['currency'] . ' ' . number_format($price['amount_minor'] / 100, 2);
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
