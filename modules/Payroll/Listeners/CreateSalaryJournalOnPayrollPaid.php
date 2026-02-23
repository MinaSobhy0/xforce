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
     */
    protected array $systemAccountCodes = [
        'SI_EMP' => [
            'debit' => '2100',  // Salaries Payable
            'credit' => '2210', // Social Insurance Payable
        ],
        'TAX' => [
            'debit' => '2100',  // Salaries Payable
            'credit' => '2200', // Tax Payable
        ],
        'VIOLATION' => [
            'debit' => '2100',  // Salaries Payable
            'credit' => '2150', // Deductions Payable
        ],
    ];

    /**
     * Default account codes for category types (fallback).
     */
    protected array $categoryDefaultCodes = [
        'earning' => [
            'debit' => '6100',  // Salary Expense
            'credit' => '2100', // Salaries Payable
        ],
        'allowance' => [
            'debit' => '6110',  // Allowances Expense
            'credit' => '2100', // Salaries Payable
        ],
        'benefit' => [
            'debit' => '6120',  // Benefits Expense
            'credit' => '2100', // Salaries Payable
        ],
        'deduction' => [
            'debit' => '2100',  // Salaries Payable
            'credit' => '2150', // Deductions Payable
        ],
        'employer_contribution' => [
            'debit' => '6200',  // Employer Contributions Expense
            'credit' => '2160', // Employer Contributions Payable
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
            }

            // Add final payment line (Credit Cash/Bank for net salary)
            if ($totalNetSalary > 0) {
                $cashAccountCode = $this->getCashAccountCode($connection);
                $key = "credit_{$cashAccountCode}";
                if (!isset($journalLines[$key])) {
                    $journalLines[$key] = [
                        'account_code' => $cashAccountCode,
                        'debit' => 0,
                        'credit' => 0,
                    ];
                }
                $journalLines[$key]['credit'] += $totalNetSalary;
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
                referenceId: $payrollRun->id
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
        // For system rules (SI, Tax, Violations), use system defaults
        if ($isSystem && isset($this->systemAccountCodes[$ruleCode])) {
            return $this->systemAccountCodes[$ruleCode];
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
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('code', '1100')
                    ->orWhere('code', 'like', '11%')
                    ->orWhere('sub_type', 'cash')
                    ->orWhere('sub_type', 'bank');
            })
            ->orderByRaw("CASE WHEN code = '1100' THEN 0 ELSE 1 END")
            ->first();

        return $cashAccount?->code ?? '1100';
    }
}
