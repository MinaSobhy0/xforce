<?php

namespace Modules\Staff\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use XLinic\Framework\Core\Model\BaseModel;

class CommissionPlanRule extends BaseModel
{
    protected $table = 'commission_plan_rules';

    protected $fillable = [
        'tenant_id',
        'commission_plan_id',
        'service_id',
        'service_category_id',
        'commission_type',
        'percentage',
        'flat_amount_minor',
        'tier_from_minor',
        'tier_to_minor',
        'is_active',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'flat_amount_minor' => 'integer',
        'tier_from_minor' => 'integer',
        'tier_to_minor' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Parent commission plan.
     */
    public function commissionPlan(): BelongsTo
    {
        return $this->belongsTo(CommissionPlan::class, 'commission_plan_id');
    }

    /**
     * Associated service.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * Associated service category.
     */
    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    /**
     * Scope: Active rules only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Calculate commission for a given revenue.
     *
     * @param int $revenueMinor Revenue in minor units
     * @return int Commission in minor units
     */
    public function calculateCommission(int $revenueMinor): int
    {
        switch ($this->commission_type) {
            case CommissionPlan::TYPE_FLAT:
                return $this->flat_amount_minor ?? 0;

            case CommissionPlan::TYPE_PERCENTAGE:
                return (int) round($revenueMinor * ($this->percentage ?? 0) / 100);

            case CommissionPlan::TYPE_TIERED:
                return $this->calculateTieredCommission($revenueMinor);

            default:
                return 0;
        }
    }

    /**
     * Calculate tiered commission.
     */
    protected function calculateTieredCommission(int $revenueMinor): int
    {
        $tierFrom = $this->tier_from_minor ?? 0;
        $tierTo = $this->tier_to_minor ?? PHP_INT_MAX;

        if ($revenueMinor < $tierFrom || $revenueMinor > $tierTo) {
            return 0;
        }

        return (int) round($revenueMinor * ($this->percentage ?? 0) / 100);
    }

    /**
     * Get display name for this rule.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->service_id && $this->service) {
            return $this->service->getTranslation('name', app()->getLocale());
        }

        if ($this->service_category_id && $this->serviceCategory) {
            return $this->serviceCategory->getTranslation('name', app()->getLocale()) . ' (Category)';
        }

        return 'All Services';
    }

    /**
     * Get formatted commission value.
     */
    public function getFormattedValueAttribute(): string
    {
        if ($this->commission_type === CommissionPlan::TYPE_FLAT) {
            return format_money($this->flat_amount_minor ?? 0);
        }

        if ($this->commission_type === CommissionPlan::TYPE_TIERED) {
            return ($this->percentage ?? 0) . '% (' . format_money($this->tier_from_minor ?? 0) . ' - ' . format_money($this->tier_to_minor ?? 0) . ')';
        }

        return ($this->percentage ?? 0) . '%';
    }
}
