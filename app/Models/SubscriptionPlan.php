<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\Tenant;
use Spatie\Translatable\HasTranslations;

class SubscriptionPlan extends Model
{
    use HasUuids, SoftDeletes, HasTranslations;

    protected $table = 'subscription_plans';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'code',
        'name',
        'description',
        'price_monthly_minor',
        'price_yearly_minor',
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
}
