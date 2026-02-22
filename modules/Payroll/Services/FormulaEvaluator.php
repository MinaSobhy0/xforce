<?php

namespace Modules\Payroll\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

class FormulaEvaluator
{
    protected ExpressionLanguage $expressionLanguage;

    /**
     * Available variables in the calculation context.
     */
    protected array $availableVariables = [
        // Base salary variables
        'base_salary' => 'Employee base salary (major units)',
        'base_salary_minor' => 'Employee base salary (minor units)',
        'BASIC' => 'Alias for base_salary',
        'BASE_SALARY' => 'Alias for base_salary',

        // Time-based variables
        'total_days' => 'Total working days in the period',
        'worked_days' => 'Actual days worked',
        'working_days' => 'Alias for total_days',
        'DAYS' => 'Alias for total_days',
        'WORKING_DAYS' => 'Alias for total_days',

        // Attendance variables
        'overtime_hours' => 'Total overtime hours',
        'late_minutes' => 'Total late arrival minutes',
        'absence_days' => 'Days absent without leave',

        // Leave variables
        'paid_leave_days' => 'Days on paid leave',
        'unpaid_leave_days' => 'Days on unpaid leave',
        'sick_leave_days' => 'Days on sick leave',
        'annual_leave_days' => 'Days on annual leave',

        // Commission variables
        'commission_amount' => 'Total commission amount',
        'commission_count' => 'Number of commission records',

        // Calculated totals (set during calculation)
        'GROSS' => 'Gross salary (earnings before deductions)',
        'gross_salary' => 'Alias for GROSS',
        'TOTAL_EARNINGS' => 'Total earnings',
        'TOTAL_ALLOWANCE' => 'Total allowances',
        'TOTAL_DEDUCTION' => 'Total deductions',
        'NET' => 'Net salary (after deductions)',
        'net_salary' => 'Alias for NET',

        // Tax variables
        'tax_rate' => 'Applicable tax rate percentage',
        'taxable_amount' => 'Taxable portion of salary',
        'taxable_income' => 'Taxable income (GROSS - Social Insurance)',

        // Additional variables from rules/components
        'bonus_amount' => 'Bonus amount for the period',
        'loan_deduction' => 'Loan repayment amount',
        'other_deductions' => 'Other deduction amounts',

        // Rule code results (dynamically added during calculation)
        'HRA' => 'Housing Allowance result',
        'TA' => 'Transport Allowance result',
        'MEAL' => 'Meal Allowance result',
        'PHONE' => 'Phone Allowance result',
        'COMM' => 'Commission result',
        'BONUS' => 'Bonus result',
        'OT' => 'Overtime Pay result',
        'SI_EMP' => 'Social Insurance (Employee) result',
        'TAX' => 'Tax deduction result',
        'ABSENCE' => 'Absence deduction result',
        'LATE' => 'Late deduction result',
        'LOAN' => 'Loan repayment result',
        'OTHER_DED' => 'Other deductions result',

        // Hourly rate (calculated)
        'hourly_rate' => 'Hourly rate based on base salary',
        'daily_rate' => 'Daily rate based on base salary',

        // Employee info
        'EMPLOYEE_ID' => 'Staff profile ID',
    ];

    public function __construct()
    {
        $this->expressionLanguage = new ExpressionLanguage();
        $this->registerCustomFunctions();
    }

    /**
     * Register custom functions for formula evaluation.
     */
    protected function registerCustomFunctions(): void
    {
        // min function
        $this->expressionLanguage->register(
            'min',
            fn ($a, $b) => "min($a, $b)",
            fn ($args, $a, $b) => min($a, $b)
        );

        // max function
        $this->expressionLanguage->register(
            'max',
            fn ($a, $b) => "max($a, $b)",
            fn ($args, $a, $b) => max($a, $b)
        );

        // abs function
        $this->expressionLanguage->register(
            'abs',
            fn ($a) => "abs($a)",
            fn ($args, $a) => abs($a)
        );

        // round function
        $this->expressionLanguage->register(
            'round',
            fn ($a, $precision = 0) => "round($a, $precision)",
            fn ($args, $a, $precision = 0) => round($a, $precision)
        );

        // floor function
        $this->expressionLanguage->register(
            'floor',
            fn ($a) => "floor($a)",
            fn ($args, $a) => floor($a)
        );

        // ceil function
        $this->expressionLanguage->register(
            'ceil',
            fn ($a) => "ceil($a)",
            fn ($args, $a) => ceil($a)
        );

        // if_else function for conditional logic
        $this->expressionLanguage->register(
            'if_else',
            fn ($condition, $true, $false) => "($condition ? $true : $false)",
            fn ($args, $condition, $trueVal, $falseVal) => $condition ? $trueVal : $falseVal
        );

        // percentage function
        $this->expressionLanguage->register(
            'percentage',
            fn ($base, $percent) => "($base * $percent / 100)",
            fn ($args, $base, $percent) => $base * $percent / 100
        );
    }

    /**
     * Evaluate a formula with the given context.
     *
     * @param string $formula The formula to evaluate
     * @param array $context The variables available in the formula
     * @return float|int The result of the evaluation
     */
    public function evaluate(string $formula, array $context): float|int
    {
        $formula = trim($formula);

        if (empty($formula)) {
            return 0;
        }

        // If it's just a number, return it directly
        if (is_numeric($formula)) {
            return (float) $formula;
        }

        try {
            // Normalize context - ensure all expected variables exist with defaults
            $normalizedContext = $this->normalizeContext($context);

            // Evaluate the expression
            $result = $this->expressionLanguage->evaluate($formula, $normalizedContext);

            // Ensure we return a numeric value
            if (!is_numeric($result)) {
                Log::warning('FormulaEvaluator: Non-numeric result', [
                    'formula' => $formula,
                    'result' => $result,
                ]);
                return 0;
            }

            return (float) $result;

        } catch (SyntaxError $e) {
            Log::error('FormulaEvaluator: Syntax error in formula', [
                'formula' => $formula,
                'error' => $e->getMessage(),
            ]);
            return 0;

        } catch (\Throwable $e) {
            Log::error('FormulaEvaluator: Error evaluating formula', [
                'formula' => $formula,
                'error' => $e->getMessage(),
                'context_keys' => array_keys($context),
            ]);
            return 0;
        }
    }

    /**
     * Validate a formula syntax without evaluating it.
     *
     * @param string $formula The formula to validate
     * @return array{valid: bool, error?: string}
     */
    public function validateFormula(string $formula): array
    {
        $formula = trim($formula);

        if (empty($formula)) {
            return ['valid' => true];
        }

        // If it's just a number, it's valid
        if (is_numeric($formula)) {
            return ['valid' => true];
        }

        try {
            // Create a dummy context with all available variables set to 1
            $dummyContext = [];
            foreach (array_keys($this->availableVariables) as $var) {
                $dummyContext[$var] = 1;
            }

            // Try to parse and evaluate with dummy values
            $this->expressionLanguage->evaluate($formula, $dummyContext);

            return ['valid' => true];

        } catch (SyntaxError $e) {
            return [
                'valid' => false,
                'error' => 'Syntax error: ' . $e->getMessage(),
            ];

        } catch (\Throwable $e) {
            // If it's a variable not found error, that might be okay
            // as we're using dummy context
            if (str_contains($e->getMessage(), 'Variable')) {
                return ['valid' => true]; // Might reference custom variables
            }

            return [
                'valid' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Evaluate a condition formula (returns boolean).
     *
     * @param string $condition The condition formula
     * @param array $context The variables available
     * @return bool
     */
    public function evaluateCondition(string $condition, array $context): bool
    {
        $condition = trim($condition);

        if (empty($condition)) {
            return true; // Empty condition means always true
        }

        try {
            $normalizedContext = $this->normalizeContext($context);
            $result = $this->expressionLanguage->evaluate($condition, $normalizedContext);

            return (bool) $result;

        } catch (\Throwable $e) {
            Log::error('FormulaEvaluator: Error evaluating condition', [
                'condition' => $condition,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get all available variables with descriptions.
     *
     * @return array<string, string>
     */
    public function getAvailableVariables(): array
    {
        return $this->availableVariables;
    }

    /**
     * Get formula examples for documentation.
     *
     * @return array<string, string>
     */
    public function getFormulaExamples(): array
    {
        return [
            'Fixed Amount' => '500',
            '10% of Base Salary' => 'base_salary * 0.10',
            'Housing Allowance' => 'base_salary * 0.25',
            'Transport Allowance' => 'min(base_salary * 0.10, 500)',
            'Attendance Bonus' => 'worked_days == total_days ? 200 : 0',
            'Absence Deduction' => '(base_salary / total_days) * absence_days',
            'Overtime Pay (1.5x)' => 'hourly_rate * overtime_hours * 1.5',
            'Late Deduction' => '(hourly_rate / 60) * late_minutes',
            'Pro-rated Salary' => 'base_salary * (worked_days / total_days)',
            'Tax Calculation' => 'taxable_amount * tax_rate / 100',
            'Conditional Bonus' => 'base_salary > 5000 ? 1000 : 500',
            'Commission Cap' => 'min(commission_amount, base_salary * 0.5)',
            'Social Insurance (11%)' => 'round(base_salary * 0.11, 2)',
        ];
    }

    /**
     * Normalize context by ensuring all expected variables exist.
     *
     * @param array $context
     * @return array
     */
    protected function normalizeContext(array $context): array
    {
        $defaults = [
            'base_salary' => 0,
            'base_salary_minor' => 0,
            'BASIC' => 0,
            'BASE_SALARY' => 0,
            'total_days' => 30,
            'worked_days' => 30,
            'working_days' => 30,
            'DAYS' => 30,
            'WORKING_DAYS' => 30,
            'overtime_hours' => 0,
            'late_minutes' => 0,
            'absence_days' => 0,
            'paid_leave_days' => 0,
            'unpaid_leave_days' => 0,
            'sick_leave_days' => 0,
            'annual_leave_days' => 0,
            'commission_amount' => 0,
            'commission_count' => 0,
            'GROSS' => 0,
            'gross_salary' => 0,
            'TOTAL_EARNINGS' => 0,
            'TOTAL_ALLOWANCE' => 0,
            'TOTAL_DEDUCTION' => 0,
            'NET' => 0,
            'net_salary' => 0,
            'tax_rate' => 0,
            'taxable_amount' => 0,
            'taxable_income' => 0,
            'hourly_rate' => 0,
            'daily_rate' => 0,
            'EMPLOYEE_ID' => null,

            // Additional context variables
            'bonus_amount' => 0,
            'loan_deduction' => 0,
            'other_deductions' => 0,

            // Rule code results (defaults, will be overwritten during calculation)
            'HRA' => 0,
            'TA' => 0,
            'MEAL' => 0,
            'PHONE' => 0,
            'COMM' => 0,
            'BONUS' => 0,
            'OT' => 0,
            'SI_EMP' => 0,
            'TAX' => 0,
            'ABSENCE' => 0,
            'LATE' => 0,
            'LOAN' => 0,
            'OTHER_DED' => 0,
        ];

        // Merge defaults with provided context
        $normalized = array_merge($defaults, $context);

        // Create aliases
        if (isset($context['base_salary'])) {
            $normalized['BASIC'] = $context['base_salary'];
            $normalized['BASE_SALARY'] = $context['base_salary'];
        }

        if (isset($context['total_days'])) {
            $normalized['DAYS'] = $context['total_days'];
            $normalized['WORKING_DAYS'] = $context['total_days'];
            $normalized['working_days'] = $context['total_days'];
        }

        if (isset($context['GROSS'])) {
            $normalized['gross_salary'] = $context['GROSS'];
        }

        if (isset($context['NET'])) {
            $normalized['net_salary'] = $context['NET'];
        }

        return $normalized;
    }
}
