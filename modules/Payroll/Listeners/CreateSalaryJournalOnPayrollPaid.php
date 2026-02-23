<?php

namespace Modules\Payroll\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Payroll\Events\PayrollPaid;
use Modules\Payroll\Models\SalaryRule;

class CreateSalaryJournalOnPayrollPaid
{
    /**
     * Default account codes for system deductions.
     * Uses account codes that exist in standard chart of accounts.
     */
    protected array $systemAccountCodes = [
        'SI_EMP' => [
            'debit' => '2110',  // Accrued Salaries (reduce payable)
            'credit' => '2100', // Accrued Expenses (SI payable)
        ],
        'TAX' => [
            'debit' => '2110',  // Accrued Salaries (reduce payable)
            'credit' => '2320', // Income Tax Payable
        ],
        'VIOLATION' => [
            'debit' => '2110',  // Accrued Salaries (reduce payable)
            'credit' => '4300', // Other Income (penalty income)
        ],
    ];

    /**
     * Default account codes for category types (fallback).
     * Uses account codes that exist in standard chart of accounts.
     */
    protected array $categoryDefaultCodes = [
        'earning' => [
            'debit' => '5110',  // Staff Salaries Expense
            'credit' => '2110', // Accrued Salaries
        ],
        'allowance' => [
            'debit' => '5110',  // Staff Salaries Expense
            'credit' => '2110', // Accrued Salaries
        ],
        'benefit' => [
            'debit' => '5110',  // Staff Salaries Expense
            'credit' => '2110', // Accrued Salaries
        ],
        'deduction' => [
            'debit' => '2110',  // Accrued Salaries (reduce payable)
            'credit' => '2100', // Accrued Expenses
        ],
        'employer_contribution' => [
            'debit' => '5110',  // Staff Salaries Expense
            'credit' => '2100', // Accrued Expenses
        ],
    ];

    /**
     * Handle the event.
     *
     * Creates journal entries for salary expenses when payroll is paid.
     */
    public function handle(PayrollPaid $event): void
    {
        $payrollRun = $event->payrollRun;

        // Check if Accounting module is available
        if (!class_exists(\Modules\Accounting\Services\AccountingIntegrationService::class)) {
            return;
        }

        try {
            $accountingService = app(\Modules\Accounting\Services\AccountingIntegrationService::class);
            $connection = $payrollRun->getConnectionName();

            // Load all salary rules that might be used (for account lookup)
            $salaryRules = SalaryRule::on($connection)
                ->withoutGlobalScopes()
                ->with(['debitAccount', 'creditAccount', 'category'])
                ->get()
                ->keyBy('id');

            // Aggregate amounts by rule/account combination
            $journalLines = [];
            $totalNetSalary = 0;

            // Process each payroll line
            foreach ($payrollRun->lines as $line) {
                $totalNetSalary += $line->net_salary_minor;
                $ruleAmounts = $line->rule_amounts_json ?? [];

                // If rule_amounts_json is populated, use it
                if (!empty($ruleAmounts)) {
                    foreach ($ruleAmounts as $ruleAmount) {
                        $ruleId = $ruleAmount['rule_id'] ?? null;
                        $ruleCode = $ruleAmount['rule_code'] ?? '';
                        $categoryType = $ruleAmount['category_type'] ?? 'earning';
                        $amountMinor = $ruleAmount['amount_minor'] ?? 0;
                        $isSystem = $ruleAmount['is_system'] ?? false;

                        if ($amountMinor == 0) {
                            continue;
                        }

                        // Get accounts based on rule configuration or defaults
                        $accounts = $this->getAccountsForRule(
                            $ruleId,
                            $ruleCode,
                            $categoryType,
                            $isSystem,
                            $salaryRules,
                            $connection
                        );

                        if (!$accounts['debit'] && !$accounts['credit']) {
                            continue; // No accounts configured, skip
                        }

                        // Create debit line
                        if ($accounts['debit']) {
                            $key = "debit_{$accounts['debit']}";
                            if (!isset($journalLines[$key])) {
                                $journalLines[$key] = [
                                    'account_code' => $accounts['debit'],
                                    'debit' => 0,
                                    'credit' => 0,
                                ];
                            }
                            $journalLines[$key]['debit'] += $amountMinor;
                        }

                        // Create credit line
                        if ($accounts['credit']) {
                            $key = "credit_{$accounts['credit']}";
                            if (!isset($journalLines[$key])) {
                                $journalLines[$key] = [
                                    'account_code' => $accounts['credit'],
                                    'debit' => 0,
                                    'credit' => 0,
                                ];
                            }
                            $journalLines[$key]['credit'] += $amountMinor;
                        }
                    }
                } else {
                    // Fallback: Create journal entries from aggregate amounts
                    $this->addFallbackJournalLines($journalLines, $line);
                }
            }

            // Add final payment line (Debit Salaries Payable, Credit Cash/Bank for net salary)
            if ($totalNetSalary > 0) {
                // Debit: Reduce salary payable (2110)
                $payableKey = 'debit_2110';
                if (!isset($journalLines[$payableKey])) {
                    $journalLines[$payableKey] = [
                        'account_code' => '2110',
                        'debit' => 0,
                        'credit' => 0,
                    ];
                }
                $journalLines[$payableKey]['debit'] += $totalNetSalary;

                // Credit: Cash/Bank payment
                $cashAccountCode = $this->getCashAccountCode($connection);
                $cashKey = "credit_{$cashAccountCode}";
                if (!isset($journalLines[$cashKey])) {
                    $journalLines[$cashKey] = [
                        'account_code' => $cashAccountCode,
                        'debit' => 0,
                        'credit' => 0,
                    ];
                }
                $journalLines[$cashKey]['credit'] += $totalNetSalary;
            }

            // Filter out zero-amount lines and convert to array
            $lines = array_values(array_filter($journalLines, function ($line) {
                return $line['debit'] > 0 || $line['credit'] > 0;
            }));

            if (empty($lines)) {
                Log::warning('CreateSalaryJournalOnPayrollPaid: No journal lines to create', [
                    'payroll_run_id' => $payrollRun->id,
                ]);
                return;
            }

            // Create the journal entry
            $accountingService->createJournalEntry(
                date: $payrollRun->paid_at ?? now(),
                description: "Payroll - {$payrollRun->period_label}",
                lines: $lines,
                referenceType: 'payroll_run',
                referenceId: $payrollRun->id,
                autoPost: true,
                tenantId: $payrollRun->tenant_id
            );

            Log::info('CreateSalaryJournalOnPayrollPaid: Journal entry created', [
                'payroll_run_id' => $payrollRun->id,
                'lines_count' => count($lines),
            ]);

        } catch (\Exception $e) {
            Log::error('CreateSalaryJournalOnPayrollPaid: Failed to create journal entry', [
                'payroll_run_id' => $payrollRun->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Add fallback journal lines from aggregate amounts (for payslips without rule_amounts_json).
     */
    protected function addFallbackJournalLines(array &$journalLines, $line): void
    {
        // Gross salary (Base + Allowances + Commissions + Bonuses)
        $grossSalary = $line->base_salary_minor + $line->allowances_minor
                     + $line->commissions_minor + $line->bonuses_minor;

        if ($grossSalary > 0) {
            // Debit: Salary Expense (5110)
            $this->addToJournalLine($journalLines, '5110', $grossSalary, 0);
            // Credit: Salaries Payable (2110)
            $this->addToJournalLine($journalLines, '2110', 0, $grossSalary);
        }

        // Social Insurance
        if ($line->social_insurance_minor > 0) {
            // Debit: Salaries Payable (2110)
            $this->addToJournalLine($journalLines, '2110', $line->social_insurance_minor, 0);
            // Credit: Accrued Expenses (2100) - for SI payable
            $this->addToJournalLine($journalLines, '2100', 0, $line->social_insurance_minor);
        }

        // Tax
        if ($line->tax_minor > 0) {
            // Debit: Salaries Payable (2110)
            $this->addToJournalLine($journalLines, '2110', $line->tax_minor, 0);
            // Credit: Tax Payable (2320)
            $this->addToJournalLine($journalLines, '2320', 0, $line->tax_minor);
        }

        // Other Deductions
        if ($line->deductions_minor > 0) {
            // Debit: Salaries Payable (2110)
            $this->addToJournalLine($journalLines, '2110', $line->deductions_minor, 0);
            // Credit: Deductions Payable (2100)
            $this->addToJournalLine($journalLines, '2100', 0, $line->deductions_minor);
        }
    }

    /**
     * Helper to add amount to journal line.
     */
    protected function addToJournalLine(array &$journalLines, string $accountCode, int $debit, int $credit): void
    {
        $key = ($debit > 0 ? 'debit_' : 'credit_') . $accountCode;
        if (!isset($journalLines[$key])) {
            $journalLines[$key] = [
                'account_code' => $accountCode,
                'debit' => 0,
                'credit' => 0,
            ];
        }
        $journalLines[$key]['debit'] += $debit;
        $journalLines[$key]['credit'] += $credit;
    }

    /**
     * Get debit and credit account codes for a rule.
     */
    protected function getAccountsForRule(
        ?string $ruleId,
        string $ruleCode,
        string $categoryType,
        bool $isSystem,
        $salaryRules,
        string $connection
    ): array {
        // For system rules, first try to find matching salary rule by code
        if ($isSystem && !$ruleId) {
            $rule = $salaryRules->firstWhere('code', $ruleCode);
            if ($rule && $rule->creates_journal_entry) {
                $debitAccount = $rule->debitAccount;
                $creditAccount = $rule->creditAccount;
                if ($debitAccount || $creditAccount) {
                    return [
                        'debit' => $debitAccount?->code,
                        'credit' => $creditAccount?->code,
                    ];
                }
            }
            // Fall back to system defaults
            if (isset($this->systemAccountCodes[$ruleCode])) {
                return $this->systemAccountCodes[$ruleCode];
            }
        }

        // For salary rules, check if they have configured accounts
        if ($ruleId && $salaryRules->has($ruleId)) {
            $rule = $salaryRules->get($ruleId);

            // Only create journal entries for rules that have it enabled
            if ($rule->creates_journal_entry) {
                $debitAccount = $rule->debitAccount;
                $creditAccount = $rule->creditAccount;

                // If accounts are configured on the rule, use them
                if ($debitAccount || $creditAccount) {
                    return [
                        'debit' => $debitAccount?->code,
                        'credit' => $creditAccount?->code,
                    ];
                }

                // If creates_journal_entry is true but no accounts configured,
                // use default codes stored on the rule
                if ($rule->default_debit_account_code || $rule->default_credit_account_code) {
                    return [
                        'debit' => $rule->default_debit_account_code,
                        'credit' => $rule->default_credit_account_code,
                    ];
                }

                // Fall back to category defaults
                return $this->categoryDefaultCodes[$categoryType] ?? [
                    'debit' => null,
                    'credit' => null,
                ];
            }

            // Rule doesn't have journal entry creation enabled
            return ['debit' => null, 'credit' => null];
        }

        // For rules without explicit configuration, use category defaults
        // But only if we don't have a rule_id (meaning it's an old calculation)
        if (!$ruleId) {
            return $this->categoryDefaultCodes[$categoryType] ?? [
                'debit' => null,
                'credit' => null,
            ];
        }

        return ['debit' => null, 'credit' => null];
    }

    /**
     * Get the cash/bank account code for payment.
     */
    protected function getCashAccountCode(string $connection): string
    {
        // Try to find a cash account
        $cashAccount = ChartOfAccount::on($connection)
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('code', '1000')
                    ->orWhere('code', '1020')
                    ->orWhere('code', 'like', '10%')
                    ->orWhere('name', 'like', '%Cash%')
                    ->orWhere('name', 'like', '%Bank%');
            })
            ->orderByRaw("CASE WHEN code = '1020' THEN 0 WHEN code = '1000' THEN 1 ELSE 2 END")
            ->first();

        return $cashAccount?->code ?? '1020';
    }
}
