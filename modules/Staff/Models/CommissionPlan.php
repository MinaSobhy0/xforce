<?php

namespace Modules\Staff\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use XLinic\Framework\Core\Model\BaseModel;

class CommissionPlan extends BaseModel
{
    protected $table = 'commission_plans';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'commission_type',
        'default_percentage',
        'default_flat_amount_minor',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'default_percentage' => 'decimal:2',
        'default_flat_amount_minor' => 'integer',
        'is_active' => 'boolean',
    ];

    // Commission types
    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FLAT = 'flat';
    public const TYPE_TIERED = 'tiered';

    public const TYPES = [
        self::TYPE_PERCENTAGE => 'Percentage',
        self::TYPE_FLAT => 'Flat Amount',
        self::TYPE_TIERED => 'Tiered',
    ];

    /**
     * Staff profiles using this plan.
     */
    public function staffProfiles(): HasMany
    {
        return $this->hasMany(StaffProfile::class, 'commission_plan_id');
    }

    /**
     * Service-specific rules within this plan.
     */
    public function serviceRules(): HasMany
    {
        return $this->hasMany(CommissionPlanRule::class, 'commission_plan_id');
    }

    /**
     * Scope: Active plans only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Calculate commission for a given revenue amount.
     *
     * @param int $revenueMinor Revenue in minor units
     * @param string|null $serviceId Optional service ID for specific rules
     * @param string|null $categoryId Optional category ID for specific rules
     * @return int Commission amount in minor units
     */
    public function calculateCommission(int $revenueMinor, ?string $serviceId = null, ?string $categoryId = null): int
    {
        // Check for service-specific rule first
        if ($serviceId) {
            $rule = $this->serviceRules()
                ->where('service_id', $serviceId)
                ->where('is_active', true)
                ->first();

            if ($rule) {
                return $rule->calculateCommission($revenueMinor);
            }
        }

        // Check for category rule
        if ($categoryId) {
            $rule = $this->serviceRules()
                ->where('service_category_id', $categoryId)
                ->whereNull('service_id')
                ->where('is_active', true)
                ->first();

            if ($rule) {
                return $rule->calculateCommission($revenueMinor);
            }
        }

        // Use plan defaults
        return $this->calculateDefaultCommission($revenueMinor);
    }

    /**
     * Calculate commission using plan defaults.
     */
    protected function calculateDefaultCommission(int $revenueMinor): int
    {
        switch ($this->commission_type) {
            case self::TYPE_FLAT:
                return $this->default_flat_amount_minor ?? 0;

            case self::TYPE_PERCENTAGE:
                return (int) round($revenueMinor * ($this->default_percentage ?? 0) / 100);

            default:
                return 0;
        }
    }

    /**
     * Get formatted default amount.
     */
    public function getFormattedDefaultAttribute(): string
    {
        if ($this->commission_type === self::TYPE_PERCENTAGE) {
            return ($this->default_percentage ?? 0) . '%';
        }

        return format_money($this->default_flat_amount_minor ?? 0);
    }
}
