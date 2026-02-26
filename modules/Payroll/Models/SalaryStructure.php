<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;

class SalaryStructure extends BaseModel
{
    use HasTenancy;

    protected $table = 'salary_structures';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'pay_frequency',
        'currency',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'pay_frequency' => self::PAY_FREQUENCY_MONTHLY,
        'currency' => 'EGP',
        'is_active' => true,
    ];

    // Pay frequencies
    public const PAY_FREQUENCY_MONTHLY = 'monthly';
    public const PAY_FREQUENCY_BIWEEKLY = 'bi-weekly';
    public const PAY_FREQUENCY_WEEKLY = 'weekly';
    public const PAY_FREQUENCY_DAILY = 'daily';
    public const PAY_FREQUENCY_HOURLY = 'hourly';

    public const PAY_FREQUENCIES = [
        self::PAY_FREQUENCY_MONTHLY => 'Monthly',
        self::PAY_FREQUENCY_BIWEEKLY => 'Bi-Weekly',
        self::PAY_FREQUENCY_WEEKLY => 'Weekly',
        self::PAY_FREQUENCY_DAILY => 'Daily',
        self::PAY_FREQUENCY_HOURLY => 'Hourly',
    ];

    public const PAY_FREQUENCY_COLORS = [
        self::PAY_FREQUENCY_MONTHLY => 'primary',
        self::PAY_FREQUENCY_BIWEEKLY => 'info',
        self::PAY_FREQUENCY_WEEKLY => 'success',
        self::PAY_FREQUENCY_DAILY => 'warning',
        self::PAY_FREQUENCY_HOURLY => 'gray',
    ];

    /**
     * Get the creator.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'created_by');
    }

    /**
     * Get salary rules attached to this structure.
     */
    public function rules(): BelongsToMany
    {
        return $this->belongsToMany(SalaryRule::class, 'salary_structure_rules')
            ->withPivot('sequence')
            ->withTimestamps()
            ->orderByPivot('sequence');
    }

    /**
     * Get earning rules.
     */
    public function earningRules(): BelongsToMany
    {
        return $this->rules()
            ->whereHas('category', function ($q) {
                $q->whereIn('type', [
                    SalaryRuleCategory::TYPE_EARNING,
                    SalaryRuleCategory::TYPE_ALLOWANCE,
                    SalaryRuleCategory::TYPE_BENEFIT,
                ]);
            });
    }

    /**
     * Get deduction rules.
     */
    public function deductionRules(): BelongsToMany
    {
        return $this->rules()
            ->whereHas('category', function ($q) {
                $q->where('type', SalaryRuleCategory::TYPE_DEDUCTION);
            });
    }

    /**
     * Get employee salary structure assignments.
     */
    public function employeeStructures(): HasMany
    {
        return $this->hasMany(EmployeeSalaryStructure::class);
    }

    /**
     * Get current active employee assignments.
     */
    public function activeEmployeeStructures(): HasMany
    {
        return $this->hasMany(EmployeeSalaryStructure::class)
            ->where('is_current', true);
    }

    /**
     * Scope to active structures.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get active employee count.
     */
    public function getActiveEmployeeCountAttribute(): int
    {
        return $this->activeEmployeeStructures()->count();
    }

    /**
     * Get rules count.
     */
    public function getRulesCountAttribute(): int
    {
        return $this->rules()->count();
    }

    /**
     * Get pay frequency label.
     */
    public function getPayFrequencyLabelAttribute(): string
    {
        return self::PAY_FREQUENCIES[$this->pay_frequency] ?? $this->pay_frequency;
    }

    /**
     * Get pay frequency color.
     */
    public function getPayFrequencyColorAttribute(): string
    {
        return self::PAY_FREQUENCY_COLORS[$this->pay_frequency] ?? 'gray';
    }

    /**
     * Calculate payroll for an employee using this structure.
     * Returns array with all calculated components.
     */
    public function calculatePayroll(array $context): array
    {
        $results = [
            'earnings' => [],
            'deductions' => [],
            'total_earnings_minor' => 0,
            'total_deductions_minor' => 0,
            'gross_salary_minor' => 0,
            'net_salary_minor' => 0,
            'rule_results' => [],
        ];

        // Add base salary to context
        $context['gross_salary_minor'] = $context['base_salary_minor'] ?? 0;
        $context['rule_results'] = [];

        // Process rules in sequence
        $rules = $this->rules()->with('category')->get();

        foreach ($rules as $rule) {
            $amount = $rule->calculateAmount(array_merge($context, [
                'rule_results' => $context['rule_results'],
            ]));

            // Store rule result for subsequent rules
            $context['rule_results'][$rule->code] = $amount;

            $ruleResult = [
                'rule_id' => $rule->id,
                'rule_code' => $rule->code,
                'rule_name' => $rule->name,
                'category_type' => $rule->category->type,
                'amount_minor' => $amount,
            ];

            if ($rule->isEarning()) {
                $results['earnings'][] = $ruleResult;
                $results['total_earnings_minor'] += $amount;
            } else {
                $results['deductions'][] = $ruleResult;
                $results['total_deductions_minor'] += $amount;
            }
        }

        // Calculate gross (base salary + earnings)
        $results['gross_salary_minor'] = ($context['base_salary_minor'] ?? 0) + $results['total_earnings_minor'];

        // Calculate net (gross - deductions)
        $results['net_salary_minor'] = $results['gross_salary_minor'] - $results['total_deductions_minor'];

        $results['rule_results'] = $context['rule_results'];

        return $results;
    }

    /**
     * Duplicate this structure with a new name/code.
     */
    public function duplicate(string $name, string $code): self
    {
        $new = $this->replicate(['id', 'created_at', 'updated_at']);
        $new->name = $name;
        $new->code = $code;
        $new->created_by = auth()->id();
        $new->save();

        // Copy rules with sequences
        foreach ($this->rules as $rule) {
            $new->rules()->attach($rule->id, [
                'sequence' => $rule->pivot->sequence,
            ]);
        }

        return $new;
    }
}
