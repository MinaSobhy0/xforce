<?php

namespace Modules\Staff\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;
use Spatie\Translatable\HasTranslations;
use XLinic\Framework\Core\Model\BaseModel;

class StaffProfile extends BaseModel
{
    use HasTranslations;

    protected $table = 'staff_profiles';

    public array $translatable = ['bio', 'specializations'];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'branch_id',
        'employee_number',
        'job_title',
        'bio',
        'specializations',
        'commission_type',
        'commission_percentage',
        'base_salary_minor',
        'hire_date',
        'contract_end_date',
        'is_active',
    ];

    protected $casts = [
        'id' => 'string',
        'bio' => 'array',
        'specializations' => 'array',
        'commission_percentage' => 'decimal:2',
        'base_salary_minor' => 'integer',
        'hire_date' => 'date',
        'contract_end_date' => 'date',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'commission_type' => self::COMMISSION_PERCENTAGE,
        'commission_percentage' => 10.00,
        'base_salary_minor' => 0,
        'is_active' => true,
    ];

    // Commission types
    public const COMMISSION_FLAT = 'flat';
    public const COMMISSION_PERCENTAGE = 'percentage';
    public const COMMISSION_TIERED = 'tiered';

    public const COMMISSION_TYPES = [
        self::COMMISSION_FLAT => 'Flat Amount',
        self::COMMISSION_PERCENTAGE => 'Percentage',
        self::COMMISSION_TIERED => 'Tiered',
    ];

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the branch.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get commission rules.
     */
    public function commissionRules(): HasMany
    {
        return $this->hasMany(StaffCommission::class);
    }

    /**
     * Get commission records.
     */
    public function commissionRecords(): HasMany
    {
        return $this->hasMany(StaffCommissionRecord::class);
    }

    /**
     * Scope to active staff only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get total earnings for a period.
     */
    public function getEarningsForPeriod(string $startDate, string $endDate): int
    {
        return $this->commissionRecords()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', StaffCommissionRecord::STATUS_APPROVED)
            ->sum('amount_minor');
    }

    /**
     * Get pending earnings.
     */
    public function getPendingEarningsAttribute(): int
    {
        return $this->commissionRecords()
            ->where('status', StaffCommissionRecord::STATUS_PENDING)
            ->sum('amount_minor');
    }

    /**
     * Get total paid earnings.
     */
    public function getTotalPaidAttribute(): int
    {
        return $this->commissionRecords()
            ->where('status', StaffCommissionRecord::STATUS_PAID)
            ->sum('amount_minor');
    }

    /**
     * Get base salary in major units.
     */
    public function getBaseSalaryAttribute(): float
    {
        return $this->base_salary_minor / 100;
    }

    /**
     * Calculate commission for an amount.
     */
    public function calculateCommission(int $amountMinor, ?string $serviceId = null): int
    {
        // Check if there's a specific commission rule for this service
        if ($serviceId) {
            $rule = $this->commissionRules()
                ->where('service_id', $serviceId)
                ->where('is_active', true)
                ->first();

            if ($rule) {
                return $rule->calculateAmount($amountMinor);
            }
        }

        // Use default commission settings
        switch ($this->commission_type) {
            case self::COMMISSION_FLAT:
                return (int) ($this->commission_percentage * 100); // stored as amount in cents

            case self::COMMISSION_PERCENTAGE:
                return (int) ($amountMinor * $this->commission_percentage / 100);

            case self::COMMISSION_TIERED:
                // For tiered, look for matching tier rule
                $tierRule = $this->commissionRules()
                    ->where('commission_type', 'tiered')
                    ->where('tier_from_minor', '<=', $amountMinor)
                    ->where(function ($q) use ($amountMinor) {
                        $q->whereNull('tier_to_minor')
                          ->orWhere('tier_to_minor', '>=', $amountMinor);
                    })
                    ->where('is_active', true)
                    ->first();

                if ($tierRule) {
                    return $tierRule->calculateAmount($amountMinor);
                }

                // Fall back to default percentage
                return (int) ($amountMinor * $this->commission_percentage / 100);

            default:
                return 0;
        }
    }
}
