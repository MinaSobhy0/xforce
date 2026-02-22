<?php

namespace Modules\Payroll\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Payroll\Models\EmployeeSalaryComponent;
use Modules\Payroll\Models\EmployeeSalaryStructure;
use Modules\Payroll\Models\PayrollLine;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\SalaryRule;
use Modules\Staff\Models\StaffCommissionRecord;
use Modules\Staff\Models\StaffProfile;

class PayrollCalculationService
{
    protected FormulaEvaluator $formulaEvaluator;

    /**
     * Tax brackets for Egypt (2024).
     * These could be moved to config/database for more flexibility.
     */
    protected array $taxBrackets = [
        ['min' => 0, 'max' => 40000, 'rate' => 0],       // 0% up to 40,000
        ['min' => 40000, 'max' => 55000, 'rate' => 10],  // 10% from 40,001 to 55,000
        ['min' => 55000, 'max' => 70000, 'rate' => 15],  // 15% from 55,001 to 70,000
        ['min' => 70000, 'max' => 200000, 'rate' => 20], // 20% from 70,001 to 200,000
        ['min' => 200000, 'max' => 400000, 'rate' => 22.5], // 22.5% from 200,001 to 400,000
        ['min' => 400000, 'max' => null, 'rate' => 25],  // 25% above 400,000
    ];

    /**
     * Social insurance rate (employee contribution).
     */
    protected float $socialInsuranceRate = 0.11; // 11%

    /**
     * Maximum social insurance base salary.
     */
    protected int $socialInsuranceMaxBase = 12600; // EGP per month

    public function __construct(FormulaEvaluator $formulaEvaluator)
    {
        $this->formulaEvaluator = $formulaEvaluator;
    }

    /**
     * Calculate all payslips for a payroll run.
     *
     * @param PayrollRun $run
     * @return array{success: bool, count: int, errors: array}
     */
    public function calculatePayrollRun(PayrollRun $run): array
    {
        $errors = [];
        $count = 0;

        try {
            // Get eligible employees (outside transaction to avoid long locks)
            $employees = $this->getEligibleEmployees($run);

            foreach ($employees as $staff) {
                // Use individual transaction per employee to prevent PostgreSQL abort issues
                try {
                    DB::beginTransaction();

                    // Check if line already exists
                    $existingLine = $run->lines()
                        ->where('staff_profile_id', $staff->id)
                        ->first();

                    if ($existingLine) {
                        // Recalculate existing line
                        $this->recalculatePayslip($existingLine);
                    } else {
                        // Create new line
                        $line = $this->calculateEmployeePayslip($staff, $run);
                        $line->save();
                    }

                    DB::commit();
                    $count++;

                } catch (\Throwable $e) {
                    DB::rollBack();

                    $errors[] = [
                        'employee_id' => $staff->id,
                        'employee_name' => $staff->user?->name ?? $staff->employee_number,
                        'error' => $e->getMessage(),
                    ];
                    Log::error('PayrollCalculation: Error calculating payslip', [
                        'staff_id' => $staff->id,
                        'run_id' => $run->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            // Update run totals (separate transaction)
            DB::beginTransaction();
            try {
                $this->updateRunTotals($run);

                // Update run status if it was in draft
                if ($run->status === PayrollRun::STATUS_DRAFT) {
                    $run->status = PayrollRun::STATUS_REVIEW;
                    $run->employee_count = $count;
                    $run->save();
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            return [
                'success' => $count > 0,
                'count' => $count,
                'errors' => $errors,
            ];

        } catch (\Throwable $e) {
            Log::error('PayrollCalculation: Error calculating payroll run', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'count' => 0,
                'errors' => [['error' => $e->getMessage()]],
            ];
        }
    }

    /**
     * Calculate a single employee's payslip.
     *
     * @param StaffProfile $staff
     * @param PayrollRun $run
     * @return PayrollLine
     */
    public function calculateEmployeePayslip(StaffProfile $staff, PayrollRun $run): PayrollLine
    {
        // Get the period dates
        $periodStart = Carbon::create($run->period_year, $run->period_month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        // Build calculation context
        $context = $this->buildCalculationContext($staff, $run, $periodStart, $periodEnd);

        // Get employee's salary structure
        $employeeSalaryStructure = $this->getEmployeeSalaryStructure($staff);
        $salaryStructure = $employeeSalaryStructure?->salaryStructure;

        // Initialize amounts
        $baseSalaryMinor = $this->getEmployeeBaseSalary($staff, $employeeSalaryStructure);

        // If we have a salary structure with rules, start from 0 - the BASIC rule will add base salary
        // Otherwise use base salary as fallback
        $totalEarningsMinor = $salaryStructure ? 0 : $baseSalaryMinor;
        $totalAllowancesMinor = 0;
        $totalBonusesMinor = 0;
        $totalDeductionsMinor = 0;
        $commissionsMinor = $context['commission_amount'] * 100; // Convert to minor

        // Calculation details for debugging/auditing
        $calculationDetails = [
            'base_salary' => $baseSalaryMinor,
            'rules_applied' => [],
            'components_applied' => [],
        ];

        // Update context with base salary
        $context['base_salary'] = $baseSalaryMinor / 100; // Major units
        $context['base_salary_minor'] = $baseSalaryMinor;
        $context['BASIC'] = $context['base_salary'];
        $context['BASE_SALARY'] = $context['base_salary'];

        // Calculate daily/hourly rates
        $context['daily_rate'] = $context['base_salary'] / $context['total_days'];
        $context['hourly_rate'] = $context['daily_rate'] / 8; // Assuming 8-hour workday

        // Apply salary structure rules if available
        if ($salaryStructure) {
            $rules = $salaryStructure->rules()
                ->with('category')
                ->orderBy('salary_structure_rules.sequence')
                ->get();

            foreach ($rules as $rule) {
                if (!$rule->is_active) {
                    continue;
                }

                // Check condition
                if (!$this->checkRuleCondition($rule, $context)) {
                    continue;
                }

                // Calculate rule amount
                $amountMinor = $this->calculateRuleAmount($rule, $context);

                // Track the calculation
                $calculationDetails['rules_applied'][] = [
                    'code' => $rule->code,
                    'name' => $rule->name,
                    'category' => $rule->category?->type,
                    'amount_minor' => $amountMinor,
                ];

                // Add rule result to context for subsequent calculations
                // This allows formulas like "BASIC + HRA + TA" to work
                $context[$rule->code] = $amountMinor / 100; // Major units

                // Add to appropriate category
                $categoryType = $rule->category?->type ?? 'earning';

                switch ($categoryType) {
                    case 'allowance':
                        $totalAllowancesMinor += $amountMinor;
                        $totalEarningsMinor += $amountMinor;
                        break;
                    case 'benefit':
                        $totalEarningsMinor += $amountMinor;
                        break;
                    case 'deduction':
                        $totalDeductionsMinor += $amountMinor;
                        break;
                    case 'earning':
                    default:
                        $totalEarningsMinor += $amountMinor;
                        break;
                }

                // Update context for subsequent calculations
                $this->updateCalculationContext($context, $categoryType, $amountMinor);
            }
        }

        // Apply employee-specific salary components
        // Note: Skip components that are linked to salary rules (they're already handled via rules)
        // Also skip components that are mapped to allowance context variables
        $allowanceFields = ['housing_allowance', 'transport_allowance', 'meal_allowance', 'phone_allowance'];
        $allowancePatterns = ['housing', 'transport', 'meal', 'phone', 'hra', 'ta'];

        $components = EmployeeSalaryComponent::on($staff->getConnectionName())
            ->where('staff_profile_id', $staff->id)
            ->whereNull('salary_rule_id') // Only custom components not tied to rules
            ->active()
            ->effective(now())
            ->get();

        foreach ($components as $component) {
            // Skip components that match allowance patterns (already handled via rules)
            $componentName = strtolower($component->name ?? '');
            $isAllowance = false;
            foreach ($allowancePatterns as $pattern) {
                if (str_contains($componentName, $pattern)) {
                    $isAllowance = true;
                    break;
                }
            }

            // If this is an allowance and already in context with value > 0, skip it
            if ($isAllowance) {
                continue;
            }

            $amountMinor = $this->calculateComponentAmount($component, $context);

            $calculationDetails['components_applied'][] = [
                'name' => $component->display_name,
                'type' => $component->component_type,
                'amount_minor' => $amountMinor,
            ];

            if ($component->component_type === EmployeeSalaryComponent::COMPONENT_TYPE_EARNING) {
                $totalEarningsMinor += $amountMinor;
            } else {
                $totalDeductionsMinor += $amountMinor;
            }
        }

        // Calculate gross salary (before tax and social insurance)
        $grossSalaryMinor = $totalEarningsMinor + $commissionsMinor;

        // Update context with gross
        $context['GROSS'] = $grossSalaryMinor / 100;
        $context['gross_salary'] = $context['GROSS'];
        $context['TOTAL_EARNINGS'] = $totalEarningsMinor / 100;
        $context['TOTAL_ALLOWANCE'] = $totalAllowancesMinor / 100;

        // Calculate social insurance
        $socialInsuranceMinor = $this->calculateSocialInsurance($baseSalaryMinor / 100);
        $totalDeductionsMinor += $socialInsuranceMinor;

        // Update context with social insurance and taxable income
        $context['SI_EMP'] = $socialInsuranceMinor / 100;
        $context['taxable_income'] = $context['GROSS'] - $context['SI_EMP'];
        $context['taxable_amount'] = $context['taxable_income'];

        // Calculate tax
        $annualGross = ($grossSalaryMinor / 100) * 12; // Estimate annual
        $taxMinor = $this->calculateTax($annualGross / 12); // Monthly tax
        $totalDeductionsMinor += $taxMinor;

        // Calculate net salary
        $netSalaryMinor = $grossSalaryMinor - $totalDeductionsMinor;

        // Update calculation details
        $calculationDetails['gross_salary'] = $grossSalaryMinor;
        $calculationDetails['social_insurance'] = $socialInsuranceMinor;
        $calculationDetails['tax'] = $taxMinor;
        $calculationDetails['total_deductions'] = $totalDeductionsMinor;
        $calculationDetails['net_salary'] = $netSalaryMinor;

        // Create PayrollLine
        $line = new PayrollLine([
            'tenant_id' => $staff->tenant_id ?? $run->tenant_id,
            'payroll_run_id' => $run->id,
            'staff_profile_id' => $staff->id,
            'base_salary_minor' => $baseSalaryMinor,
            'commissions_minor' => (int) $commissionsMinor,
            'bonuses_minor' => $totalBonusesMinor,
            'deductions_minor' => $totalDeductionsMinor - $socialInsuranceMinor - $taxMinor, // Other deductions
            'tax_minor' => $taxMinor,
            'social_insurance_minor' => $socialInsuranceMinor,
            'net_salary_minor' => max(0, $netSalaryMinor), // Ensure non-negative
        ]);

        // Store calculation details as JSON (if column exists)
        if (in_array('calculation_details', $line->getFillable())) {
            $line->calculation_details = $calculationDetails;
        }

        return $line;
    }

    /**
     * Recalculate an existing payslip.
     *
     * @param PayrollLine $line
     * @return PayrollLine
     */
    public function recalculatePayslip(PayrollLine $line): PayrollLine
    {
        $staff = $line->staffProfile;
        $run = $line->payrollRun;

        $newLine = $this->calculateEmployeePayslip($staff, $run);

        // Update existing line with new values
        $line->base_salary_minor = $newLine->base_salary_minor;
        $line->commissions_minor = $newLine->commissions_minor;
        $line->bonuses_minor = $newLine->bonuses_minor;
        $line->deductions_minor = $newLine->deductions_minor;
        $line->tax_minor = $newLine->tax_minor;
        $line->social_insurance_minor = $newLine->social_insurance_minor;
        $line->net_salary_minor = $newLine->net_salary_minor;

        if (in_array('calculation_details', $line->getFillable())) {
            $line->calculation_details = $newLine->calculation_details ?? null;
        }

        $line->save();

        return $line;
    }

    /**
     * Build the calculation context for an employee.
     *
     * @param StaffProfile $staff
     * @param PayrollRun $run
     * @param Carbon $periodStart
     * @param Carbon $periodEnd
     * @return array
     */
    public function buildCalculationContext(
        StaffProfile $staff,
        PayrollRun $run,
        Carbon $periodStart,
        Carbon $periodEnd
    ): array {
        // Get working days in period
        $totalDays = $this->getWorkingDaysInPeriod($periodStart, $periodEnd);

        // Get commission data
        $commissionData = $this->getCommissionData($staff, $periodStart, $periodEnd);

        // TODO: Get attendance data when module is available
        $attendanceData = $this->getAttendanceData($staff, $periodStart, $periodEnd);

        // TODO: Get leave data when module is available
        $leaveData = $this->getLeaveData($staff, $periodStart, $periodEnd);

        // Get allowance values from employee salary components
        $allowanceData = $this->getEmployeeAllowances($staff);

        return [
            // Employee info
            'EMPLOYEE_ID' => $staff->id,

            // Base salary (will be set later)
            'base_salary' => 0,
            'base_salary_minor' => 0,
            'BASIC' => 0,
            'BASE_SALARY' => 0,

            // Time-based
            'total_days' => $totalDays,
            'worked_days' => $attendanceData['worked_days'] ?? $totalDays,
            'working_days' => $totalDays,
            'DAYS' => $totalDays,
            'WORKING_DAYS' => $totalDays,

            // Attendance
            'overtime_hours' => $attendanceData['overtime_hours'] ?? 0,
            'late_minutes' => $attendanceData['late_minutes'] ?? 0,
            'absence_days' => $attendanceData['absence_days'] ?? 0,

            // Leave
            'paid_leave_days' => $leaveData['paid_leave_days'] ?? 0,
            'unpaid_leave_days' => $leaveData['unpaid_leave_days'] ?? 0,
            'sick_leave_days' => $leaveData['sick_leave_days'] ?? 0,
            'annual_leave_days' => $leaveData['annual_leave_days'] ?? 0,

            // Commission
            'commission_amount' => $commissionData['total_amount'],
            'commission_count' => $commissionData['count'],

            // Calculated totals (will be updated during calculation)
            'GROSS' => 0,
            'gross_salary' => 0,
            'TOTAL_EARNINGS' => 0,
            'TOTAL_ALLOWANCE' => 0,
            'TOTAL_DEDUCTION' => 0,
            'NET' => 0,
            'net_salary' => 0,

            // Tax (will be calculated)
            'tax_rate' => 0,
            'taxable_amount' => 0,
            'taxable_income' => 0, // Will be calculated as GROSS - SI_EMP

            // Rates (will be calculated based on base salary)
            'hourly_rate' => 0,
            'daily_rate' => 0,

            // Additional context variables for rule formulas
            'bonus_amount' => 0, // Set from employee bonuses if available
            'loan_deduction' => 0, // Set from active loans if available
            'other_deductions' => 0, // Set from other deduction sources

            // Allowances from employee salary components
            'housing_allowance' => $allowanceData['housing_allowance'] ?? 0,
            'transport_allowance' => $allowanceData['transport_allowance'] ?? 0,
            'meal_allowance' => $allowanceData['meal_allowance'] ?? 0,
            'phone_allowance' => $allowanceData['phone_allowance'] ?? 0,
        ];
    }

    /**
     * Get allowance values from employee salary components.
     *
     * @param StaffProfile $staff
     * @return array
     */
    public function getEmployeeAllowances(StaffProfile $staff): array
    {
        $allowances = [
            'housing_allowance' => 0,
            'transport_allowance' => 0,
            'meal_allowance' => 0,
            'phone_allowance' => 0,
        ];

        // Map component names/codes to context variable names
        $componentMapping = [
            'HRA' => 'housing_allowance',
            'housing' => 'housing_allowance',
            'housing_allowance' => 'housing_allowance',
            'TA' => 'transport_allowance',
            'transport' => 'transport_allowance',
            'transport_allowance' => 'transport_allowance',
            'MEAL' => 'meal_allowance',
            'meal' => 'meal_allowance',
            'meal_allowance' => 'meal_allowance',
            'PHONE' => 'phone_allowance',
            'phone' => 'phone_allowance',
            'phone_allowance' => 'phone_allowance',
        ];

        $components = EmployeeSalaryComponent::on($staff->getConnectionName())
            ->where('staff_profile_id', $staff->id)
            ->active()
            ->effective(now())
            ->where('component_type', EmployeeSalaryComponent::COMPONENT_TYPE_EARNING)
            ->get();

        foreach ($components as $component) {
            // Check by salary rule code first
            if ($component->salaryRule) {
                $ruleCode = $component->salaryRule->code;
                if (isset($componentMapping[$ruleCode])) {
                    $key = $componentMapping[$ruleCode];
                    $allowances[$key] += $component->amount_minor / 100; // Convert to major units
                    continue;
                }
            }

            // Check by component name
            $name = strtolower($component->name ?? '');
            foreach ($componentMapping as $pattern => $key) {
                if (str_contains($name, strtolower($pattern))) {
                    $allowances[$key] += $component->amount_minor / 100;
                    break;
                }
            }
        }

        return $allowances;
    }

    /**
     * Get eligible employees for payroll.
     *
     * @param PayrollRun $run
     * @return Collection<StaffProfile>
     */
    public function getEligibleEmployees(PayrollRun $run): Collection
    {
        // Use the same connection as the payroll run
        return StaffProfile::on($run->getConnectionName())
            ->with(['user', 'salaryStructures.salaryStructure', 'salaryComponents'])
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get employee's current salary structure.
     *
     * @param StaffProfile $staff
     * @return EmployeeSalaryStructure|null
     */
    public function getEmployeeSalaryStructure(StaffProfile $staff): ?EmployeeSalaryStructure
    {
        // Try eager-loaded relationship first
        if ($staff->relationLoaded('currentSalaryStructure') && $staff->currentSalaryStructure) {
            return $staff->currentSalaryStructure;
        }

        // Query directly using the same connection as the staff model
        return EmployeeSalaryStructure::on($staff->getConnectionName())
            ->where('staff_profile_id', $staff->id)
            ->where('is_current', true)
            ->with('salaryStructure.rules.category')
            ->first();
    }

    /**
     * Get employee's base salary in minor units.
     *
     * @param StaffProfile $staff
     * @param EmployeeSalaryStructure|null $employeeSalaryStructure
     * @return int
     */
    public function getEmployeeBaseSalary(
        StaffProfile $staff,
        ?EmployeeSalaryStructure $employeeSalaryStructure
    ): int {
        // Priority:
        // 1. EmployeeSalaryStructure base salary
        // 2. StaffProfile base salary
        // 3. 0

        if ($employeeSalaryStructure && $employeeSalaryStructure->base_salary_minor > 0) {
            return $employeeSalaryStructure->base_salary_minor;
        }

        return $staff->base_salary_minor ?? 0;
    }

    /**
     * Get working days in a period (excluding weekends).
     *
     * @param Carbon $start
     * @param Carbon $end
     * @return int
     */
    public function getWorkingDaysInPeriod(Carbon $start, Carbon $end): int
    {
        $days = 0;
        $current = $start->copy();

        while ($current <= $end) {
            // Skip Friday (5) and Saturday (6) for Egypt
            // This could be made configurable per tenant
            if (!in_array($current->dayOfWeek, [5, 6])) {
                $days++;
            }
            $current->addDay();
        }

        return max($days, 1); // At least 1 to avoid division by zero
    }

    /**
     * Get commission data for the period.
     *
     * @param StaffProfile $staff
     * @param Carbon $start
     * @param Carbon $end
     * @return array{total_amount: float, count: int}
     */
    public function getCommissionData(StaffProfile $staff, Carbon $start, Carbon $end): array
    {
        $commissions = StaffCommissionRecord::on($staff->getConnectionName())
            ->where('staff_profile_id', $staff->id)
            ->whereBetween('created_at', [$start, $end])
            ->where('status', StaffCommissionRecord::STATUS_APPROVED)
            ->get();

        return [
            'total_amount' => $commissions->sum('amount_minor') / 100,
            'count' => $commissions->count(),
        ];
    }

    /**
     * Get attendance data for the period.
     * TODO: Implement when Attendance module is available.
     *
     * @param StaffProfile $staff
     * @param Carbon $start
     * @param Carbon $end
     * @return array
     */
    public function getAttendanceData(StaffProfile $staff, Carbon $start, Carbon $end): array
    {
        // Placeholder - return full attendance
        return [
            'worked_days' => $this->getWorkingDaysInPeriod($start, $end),
            'overtime_hours' => 0,
            'late_minutes' => 0,
            'absence_days' => 0,
        ];
    }

    /**
     * Get leave data for the period.
     * TODO: Implement when Leave module is available.
     *
     * @param StaffProfile $staff
     * @param Carbon $start
     * @param Carbon $end
     * @return array
     */
    public function getLeaveData(StaffProfile $staff, Carbon $start, Carbon $end): array
    {
        // Placeholder - return no leaves
        return [
            'paid_leave_days' => 0,
            'unpaid_leave_days' => 0,
            'sick_leave_days' => 0,
            'annual_leave_days' => 0,
        ];
    }

    /**
     * Check if a salary rule's condition is met.
     *
     * @param SalaryRule $rule
     * @param array $context
     * @return bool
     */
    protected function checkRuleCondition(SalaryRule $rule, array $context): bool
    {
        if ($rule->condition_type === SalaryRule::CONDITION_ALWAYS || empty($rule->condition_type)) {
            return true;
        }

        if ($rule->condition_type === SalaryRule::CONDITION_FORMULA && !empty($rule->condition_formula)) {
            return $this->formulaEvaluator->evaluateCondition($rule->condition_formula, $context);
        }

        return true;
    }

    /**
     * Calculate a salary rule's amount.
     *
     * @param SalaryRule $rule
     * @param array $context
     * @return int Amount in minor units
     */
    protected function calculateRuleAmount(SalaryRule $rule, array $context): int
    {
        switch ($rule->amount_type) {
            case SalaryRule::AMOUNT_TYPE_FIXED:
                // Check if rule uses field_mapping to get value from context
                if (!empty($rule->field_mapping)) {
                    $fieldValue = $context[$rule->field_mapping] ?? 0;
                    // If field value is in major units, convert to minor
                    return (int) round($fieldValue * 100);
                }
                return $rule->amount_fixed_minor;

            case SalaryRule::AMOUNT_TYPE_PERCENTAGE:
                // Get the base for percentage calculation
                $base = $context['base_salary'];

                if ($rule->percentage_base_id && $rule->percentageBase) {
                    // Use another rule's result as base
                    $baseRuleCode = $rule->percentageBase->code;
                    $base = $context[$baseRuleCode] ?? $context['base_salary'];
                }

                $amount = $base * ($rule->amount_percentage / 100);
                return (int) round($amount * 100); // Convert to minor

            case SalaryRule::AMOUNT_TYPE_FORMULA:
                if (empty($rule->amount_formula)) {
                    return 0;
                }
                $amount = $this->formulaEvaluator->evaluate($rule->amount_formula, $context);
                return (int) round($amount * 100); // Convert to minor

            default:
                return 0;
        }
    }

    /**
     * Calculate an employee salary component's amount.
     *
     * @param EmployeeSalaryComponent $component
     * @param array $context
     * @return int Amount in minor units
     */
    protected function calculateComponentAmount(EmployeeSalaryComponent $component, array $context): int
    {
        switch ($component->calculation_type) {
            case EmployeeSalaryComponent::CALCULATION_TYPE_FIXED:
                return $component->amount_minor;

            case EmployeeSalaryComponent::CALCULATION_TYPE_PERCENTAGE:
                $amount = $context['base_salary'] * ($component->percentage / 100);
                return (int) round($amount * 100);

            case EmployeeSalaryComponent::CALCULATION_TYPE_FORMULA:
                if (empty($component->formula)) {
                    return 0;
                }
                $amount = $this->formulaEvaluator->evaluate($component->formula, $context);
                return (int) round($amount * 100);

            default:
                return 0;
        }
    }

    /**
     * Update the calculation context after applying a rule.
     *
     * @param array &$context
     * @param string $categoryType
     * @param int $amountMinor
     * @return void
     */
    protected function updateCalculationContext(array &$context, string $categoryType, int $amountMinor): void
    {
        $amountMajor = $amountMinor / 100;

        switch ($categoryType) {
            case 'allowance':
                $context['TOTAL_ALLOWANCE'] = ($context['TOTAL_ALLOWANCE'] ?? 0) + $amountMajor;
                $context['TOTAL_EARNINGS'] = ($context['TOTAL_EARNINGS'] ?? 0) + $amountMajor;
                break;
            case 'deduction':
                $context['TOTAL_DEDUCTION'] = ($context['TOTAL_DEDUCTION'] ?? 0) + $amountMajor;
                break;
            case 'earning':
            case 'benefit':
            default:
                $context['TOTAL_EARNINGS'] = ($context['TOTAL_EARNINGS'] ?? 0) + $amountMajor;
                break;
        }
    }

    /**
     * Calculate social insurance contribution.
     *
     * @param float $baseSalary Base salary in major units
     * @return int Social insurance in minor units
     */
    protected function calculateSocialInsurance(float $baseSalary): int
    {
        // Cap at maximum base
        $base = min($baseSalary, $this->socialInsuranceMaxBase);

        $amount = $base * $this->socialInsuranceRate;

        return (int) round($amount * 100);
    }

    /**
     * Calculate monthly tax using Egyptian progressive brackets.
     *
     * @param float $monthlyGross Monthly gross in major units
     * @return int Tax in minor units
     */
    protected function calculateTax(float $monthlyGross): int
    {
        // Convert to annual for bracket calculation
        $annualGross = $monthlyGross * 12;
        $annualTax = 0;
        $remainingIncome = $annualGross;

        foreach ($this->taxBrackets as $bracket) {
            if ($remainingIncome <= 0) {
                break;
            }

            $bracketMin = $bracket['min'];
            $bracketMax = $bracket['max'] ?? PHP_INT_MAX;
            $rate = $bracket['rate'] / 100;

            $taxableInBracket = min($remainingIncome, $bracketMax - $bracketMin);
            $annualTax += $taxableInBracket * $rate;
            $remainingIncome -= $taxableInBracket;
        }

        // Convert back to monthly
        $monthlyTax = $annualTax / 12;

        return (int) round($monthlyTax * 100);
    }

    /**
     * Update the payroll run totals after calculation.
     *
     * @param PayrollRun $run
     * @return void
     */
    protected function updateRunTotals(PayrollRun $run): void
    {
        $totals = $run->lines()
            ->selectRaw('
                COUNT(*) as employee_count,
                SUM(base_salary_minor) as total_base_salary_minor,
                SUM(commissions_minor) as total_commissions_minor,
                SUM(bonuses_minor) as total_bonuses_minor,
                SUM(deductions_minor + tax_minor + social_insurance_minor) as total_deductions_minor,
                SUM(net_salary_minor) as total_net_salary_minor
            ')
            ->first();

        $run->employee_count = $totals->employee_count ?? 0;
        $run->total_base_salary_minor = $totals->total_base_salary_minor ?? 0;
        $run->total_commissions_minor = $totals->total_commissions_minor ?? 0;
        $run->total_bonuses_minor = $totals->total_bonuses_minor ?? 0;
        $run->total_deductions_minor = $totals->total_deductions_minor ?? 0;
        $run->total_net_salary_minor = $totals->total_net_salary_minor ?? 0;
        $run->save();
    }
}
