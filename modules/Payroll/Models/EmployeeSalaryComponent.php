<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;
use Modules\Staff\Models\StaffProfile;
use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;

class EmployeeSalaryComponent extends BaseModel
{
    use HasTenancy;

    protected $table = 'employee_salary_components';

    // Component types
    public const COMPONENT_TYPE_EARNING = 'earning';
    public const COMPONENT_TYPE_DEDUCTION = 'deduction';

    public const COMPONENT_TYPES = [
        self::COMPONENT_TYPE_EARNING => 'Earning',
        self::COMPONENT_TYPE_DEDUCTION => 'Deduction',
    ];

    public const COMPONENT_TYPE_COLORS = [
        self::COMPONENT_TYPE_EARNING => 'success',
        self::COMPONENT_TYPE_DEDUCTION => 'danger',
    ];

    // Calculation types
    public const CALCULATION_TYPE_FIXED = 'fixed';
    public const CALCULATION_TYPE_PERCENTAGE = 'percentage';
    public const CALCULATION_TYPE_FORMULA = 'formula';

    public const CALCULATION_TYPES = [
        self::CALCULATION_TYPE_FIXED => 'Fixed Amount',
        self::CALCULATION_TYPE_PERCENTAGE => 'Percentage',
        self::CALCULATION_TYPE_FORMULA => 'Formula',
    ];

    protected $fillable = [
        'tenant_id',
        'staff_profile_id',
        'salary_rule_id',
        'name',
        'component_type',
        'calculation_type',
        'amount_minor',
        'percentage',
        'formula',
        'effective_date',
        'end_date',
        'is_taxable',
        'is_active',
        'loan_id',
        'created_by',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'percentage' => 'decimal:4',
        'effective_date' => 'date',
        'end_date' => 'date',
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'calculation_type' => self::CALCULATION_TYPE_FIXED,
        'amount_minor' => 0,
        'is_taxable' => true,
        'is_active' => true,
    ];

    /**
     * Get the staff profile.
     */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_profile_id');
    }

    /**
     * Get the salary rule (optional).
     */
    public function salaryRule(): BelongsTo
    {
        return $this->belongsTo(SalaryRule::class, 'salary_rule_id');
    }

    /**
     * Get the user who created this component.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to active components.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to currently effective components.
     */
    public function scopeEffective($query)
    {
        $now = now()->toDateString();
        return $query->where('effective_date', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $now);
            });
    }

    /**
     * Scope to earnings only.
     */
    public function scopeEarnings($query)
    {
        return $query->where('component_type', self::COMPONENT_TYPE_EARNING);
    }

    /**
     * Scope to deductions only.
     */
    public function scopeDeductions($query)
    {
        return $query->where('component_type', self::COMPONENT_TYPE_DEDUCTION);
    }

    /**
     * Scope to taxable components.
     */
    public function scopeTaxable($query)
    {
        return $query->where('is_taxable', true);
    }

    /**
     * Get amount in major units.
     */
    public function getAmountAttribute(): float
    {
        return $this->amount_minor / 100;
    }

    /**
     * Set amount from major units.
     */
    public function setAmountAttribute($value): void
    {
        $this->attributes['amount_minor'] = (int) round($value * 100);
    }

    /**
     * Check if this component is currently effective.
     */
    public function isEffective(): bool
    {
        $now = now()->toDateString();

        if ($this->effective_date > $now) {
            return false;
        }

        if ($this->end_date && $this->end_date < $now) {
            return false;
        }

        return true;
    }

    /**
     * Get the display name (from rule or custom name).
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->salaryRule) {
            return $this->salaryRule->name;
        }
        return $this->name;
    }

    /**
     * Calculate the value for this component.
     */
    public function calculateValue(array $context = []): int
    {
        if (!$this->is_active || !$this->isEffective()) {
            return 0;
        }

        switch ($this->calculation_type) {
            case self::CALCULATION_TYPE_FIXED:
                return $this->amount_minor;

            case self::CALCULATION_TYPE_PERCENTAGE:
                $baseSalary = $context['base_salary'] ?? $context['BASE_SALARY'] ?? 0;
                return (int) round($baseSalary * ($this->percentage / 100));

            case self::CALCULATION_TYPE_FORMULA:
                // Formula evaluation will be handled by FormulaEvaluator service
                // For now, return 0 if formula
                if ($this->salaryRule) {
                    return $this->salaryRule->calculateAmount($context);
                }
                return 0;

            default:
                return 0;
        }
    }

    /**
     * Get the sign for this component (+ for earning, - for deduction).
     */
    public function getSignAttribute(): int
    {
        return $this->component_type === self::COMPONENT_TYPE_EARNING ? 1 : -1;
    }

    /**
     * Get signed amount in minor units.
     */
    public function getSignedAmountMinorAttribute(): int
    {
        return $this->calculateValue() * $this->sign;
    }
}
