<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Accounting\Models\ChartOfAccount;
use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;

class SalaryRule extends BaseModel
{
    use HasTenancy, SoftDeletes;

    protected $table = 'salary_rules';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'category_id',
        'amount_type',
        'amount_fixed_minor',
        'amount_percentage',
        'amount_formula',
        'condition_type',
        'condition_formula',
        'percentage_base_id',
        'field_mapping',
        'sequence',
        'is_active',
        'appears_on_payslip',
        'show_in_mobile_app',
        // Journal entry accounts
        'debit_account_id',
        'credit_account_id',
        'creates_journal_entry',
        'default_debit_account_code',
        'default_credit_account_code',
        'odoo_id',
        'odoo_synced_at',
    ];

    protected $casts = [
        'amount_fixed_minor' => 'integer',
        'amount_percentage' => 'decimal:4',
        'sequence' => 'integer',
        'is_active' => 'boolean',
        'creates_journal_entry' => 'boolean',
        'appears_on_payslip' => 'boolean',
        'show_in_mobile_app' => 'boolean',
    ];

    protected $attributes = [
        'amount_type' => self::AMOUNT_TYPE_FIXED,
        'amount_fixed_minor' => 0,
        'sequence' => 0,
        'is_active' => true,
    ];

    // Amount types
    public const AMOUNT_TYPE_FIXED = 'fixed';

    public const AMOUNT_TYPE_PERCENTAGE = 'percentage';

    public const AMOUNT_TYPE_FORMULA = 'formula';

    public const AMOUNT_TYPES = [
        self::AMOUNT_TYPE_FIXED => 'Fixed Amount',
        self::AMOUNT_TYPE_PERCENTAGE => 'Percentage',
        self::AMOUNT_TYPE_FORMULA => 'Formula',
    ];

    // Condition types
    public const CONDITION_ALWAYS = 'always';

    public const CONDITION_RANGE = 'range';

    public const CONDITION_FORMULA = 'formula';

    public const CONDITION_TYPES = [
        self::CONDITION_ALWAYS => 'Always Apply',
        self::CONDITION_RANGE => 'Range Condition',
        self::CONDITION_FORMULA => 'Formula Condition',
    ];

    /**
     * Get the category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(SalaryRuleCategory::class, 'category_id');
    }

    /**
     * Get the percentage base rule.
     */
    public function percentageBase(): BelongsTo
    {
        return $this->belongsTo(SalaryRule::class, 'percentage_base_id');
    }

    /**
     * Get rules that depend on this rule.
     */
    public function dependentRules(): HasMany
    {
        return $this->hasMany(SalaryRule::class, 'percentage_base_id');
    }

    /**
     * Get salary structures using this rule.
     */
    public function salaryStructures(): BelongsToMany
    {
        return $this->belongsToMany(SalaryStructure::class, 'salary_structure_rules')
            ->withPivot('sequence')
            ->withTimestamps()
            ->orderByPivot('sequence');
    }

    /**
     * Get the debit account for journal entries.
     */
    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'debit_account_id');
    }

    /**
     * Get the credit account for journal entries.
     */
    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'credit_account_id');
    }

    /**
     * Check if this rule should create journal entry lines.
     */
    public function shouldCreateJournalEntry(): bool
    {
        return $this->creates_journal_entry
            && ($this->debit_account_id || $this->credit_account_id);
    }

    /**
     * Get default account codes based on rule category type.
     */
    public static function getDefaultAccountCodes(string $categoryType): array
    {
        return match ($categoryType) {
            SalaryRuleCategory::TYPE_EARNING => [
                'debit' => '6100',  // Salary Expense
                'credit' => '2100', // Salaries Payable
            ],
            SalaryRuleCategory::TYPE_ALLOWANCE => [
                'debit' => '6110',  // Allowances Expense
                'credit' => '2100', // Salaries Payable
            ],
            SalaryRuleCategory::TYPE_BENEFIT => [
                'debit' => '6120',  // Benefits Expense
                'credit' => '2100', // Salaries Payable
            ],
            SalaryRuleCategory::TYPE_DEDUCTION => [
                'debit' => '2100',  // Salaries Payable
                'credit' => '2150', // Deductions Payable
            ],
            SalaryRuleCategory::TYPE_EMPLOYER_CONTRIBUTION => [
                'debit' => '6200',  // Employer Contributions Expense
                'credit' => '2160', // Employer Contributions Payable
            ],
            default => [
                'debit' => null,
                'credit' => null,
            ],
        };
    }

    /**
     * Scope to active rules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to ordered rules.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sequence');
    }

    /**
     * Scope to earnings rules.
     */
    public function scopeEarnings($query)
    {
        return $query->whereHas('category', function ($q) {
            $q->whereIn('type', [
                SalaryRuleCategory::TYPE_EARNING,
                SalaryRuleCategory::TYPE_ALLOWANCE,
                SalaryRuleCategory::TYPE_BENEFIT,
            ]);
        });
    }

    /**
     * Scope to deduction rules.
     */
    public function scopeDeductions($query)
    {
        return $query->whereHas('category', function ($q) {
            $q->where('type', SalaryRuleCategory::TYPE_DEDUCTION);
        });
    }

    /**
     * Get amount in major units.
     */
    public function getAmountFixedAttribute(): float
    {
        return ($this->attributes['amount_fixed_minor'] ?? 0) / 100;
    }

    /**
     * Set amount from major units.
     */
    public function setAmountFixedAttribute($value): void
    {
        $this->attributes['amount_fixed_minor'] = (int) round($value * 100);
    }

    /**
     * Check if this is a fixed amount rule.
     */
    public function isFixed(): bool
    {
        return $this->amount_type === self::AMOUNT_TYPE_FIXED;
    }

    /**
     * Check if this is a percentage rule.
     */
    public function isPercentage(): bool
    {
        return $this->amount_type === self::AMOUNT_TYPE_PERCENTAGE;
    }

    /**
     * Check if this is a formula rule.
     */
    public function isFormula(): bool
    {
        return $this->amount_type === self::AMOUNT_TYPE_FORMULA;
    }

    /**
     * Check if this is an earning rule.
     */
    public function isEarning(): bool
    {
        return $this->category?->isEarning() ?? false;
    }

    /**
     * Check if this is a deduction rule.
     */
    public function isDeduction(): bool
    {
        return $this->category?->isDeduction() ?? false;
    }

    /**
     * Calculate amount based on context.
     * Returns amount in minor units.
     */
    public function calculateAmount(array $context): int
    {
        // Check condition first
        if (! $this->evaluateCondition($context)) {
            return 0;
        }

        return match ($this->amount_type) {
            self::AMOUNT_TYPE_FIXED => $this->amount_fixed_minor,
            self::AMOUNT_TYPE_PERCENTAGE => $this->calculatePercentage($context),
            self::AMOUNT_TYPE_FORMULA => $this->evaluateFormula($context),
            default => 0,
        };
    }

    /**
     * Evaluate the condition.
     */
    protected function evaluateCondition(array $context): bool
    {
        if (! $this->condition_type || $this->condition_type === self::CONDITION_ALWAYS) {
            return true;
        }

        if ($this->condition_type === self::CONDITION_FORMULA && $this->condition_formula) {
            try {
                $evaluator = app(\Modules\Payroll\Services\FormulaEvaluator::class);

                return (bool) $evaluator->evaluate($this->condition_formula, $context);
            } catch (\Exception $e) {
                return true; // Default to applying the rule if condition fails
            }
        }

        return true;
    }

    /**
     * Calculate percentage amount.
     */
    protected function calculatePercentage(array $context): int
    {
        $baseAmount = 0;

        if ($this->percentage_base_id && $this->percentageBase) {
            // Use another rule as base
            $baseAmount = $context['rule_results'][$this->percentageBase->code] ?? 0;
        } elseif (isset($context['base_salary_minor'])) {
            // Default to base salary
            $baseAmount = $context['base_salary_minor'];
        }

        $percentage = $this->amount_percentage ?? 0;

        return (int) round($baseAmount * ($percentage / 100));
    }

    /**
     * Evaluate formula.
     */
    protected function evaluateFormula(array $context): int
    {
        if (! $this->amount_formula) {
            return 0;
        }

        try {
            $evaluator = app(\Modules\Payroll\Services\FormulaEvaluator::class);
            $result = $evaluator->evaluate($this->amount_formula, $context);

            return (int) round($result * 100); // Convert to minor units
        } catch (\Exception $e) {
            logger()->error('Salary rule formula evaluation failed', [
                'rule_id' => $this->id,
                'formula' => $this->amount_formula,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get amount type label.
     */
    public function getAmountTypeLabelAttribute(): string
    {
        return self::AMOUNT_TYPES[$this->amount_type] ?? $this->amount_type;
    }

    /**
     * Get display value based on amount type.
     */
    public function getDisplayValueAttribute(): string
    {
        return match ($this->amount_type) {
            self::AMOUNT_TYPE_FIXED => number_format($this->amount_fixed, 2),
            self::AMOUNT_TYPE_PERCENTAGE => number_format($this->amount_percentage, 2).'%',
            self::AMOUNT_TYPE_FORMULA => 'Formula',
            default => '-',
        };
    }

    /**
     * Translate Odoo amount_select values to local amount_type values + default
     * the NOT NULL columns Odoo doesn't carry directly.
     *
     * Odoo amount_select: fix | percentage | code
     * Local amount_type:   fixed | percentage | formula
     */
    public static function applyOdooImport(array $data, $mapping = null, ?array $odooData = null): array
    {
        $map = ['fix' => self::AMOUNT_TYPE_FIXED, 'code' => self::AMOUNT_TYPE_FORMULA];
        $current = $data['amount_type'] ?? null;
        $data['amount_type'] = $map[$current] ?? $current ?? self::AMOUNT_TYPE_FIXED;

        $data['amount_fixed_minor'] = $data['amount_fixed_minor'] ?? 0;
        $data['sequence'] = $data['sequence'] ?? 0;

        return $data;
    }
}
