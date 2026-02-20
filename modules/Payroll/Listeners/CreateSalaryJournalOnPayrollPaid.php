<?php

namespace Modules\Payroll\Listeners;

use Modules\Payroll\Events\PayrollPaid;

class CreateSalaryJournalOnPayrollPaid
{
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

        // Create journal entry for salary expenses
        $accountingService = app(\Modules\Accounting\Services\AccountingIntegrationService::class);

        $accountingService->createJournalEntry(
            date: $payrollRun->paid_at ?? now(),
            description: "Payroll - {$payrollRun->period_label}",
            lines: [
                // Debit: Salary Expense
                [
                    'account_code' => '6100', // Salary Expense
                    'debit' => $payrollRun->total_base_salary_minor,
                    'credit' => 0,
                ],
                // Debit: Commission Expense
                [
                    'account_code' => '6110', // Commission Expense
                    'debit' => $payrollRun->total_commissions_minor,
                    'credit' => 0,
                ],
                // Debit: Bonus Expense
                [
                    'account_code' => '6120', // Bonus Expense
                    'debit' => $payrollRun->total_bonuses_minor,
                    'credit' => 0,
                ],
                // Credit: Cash/Bank
                [
                    'account_code' => '1100', // Cash
                    'debit' => 0,
                    'credit' => $payrollRun->total_net_salary_minor,
                ],
                // Credit: Tax Payable
                [
                    'account_code' => '2200', // Tax Payable
                    'debit' => 0,
                    'credit' => $payrollRun->lines->sum('tax_minor'),
                ],
                // Credit: Social Insurance Payable
                [
                    'account_code' => '2210', // Social Insurance Payable
                    'debit' => 0,
                    'credit' => $payrollRun->lines->sum('social_insurance_minor'),
                ],
            ],
            referenceType: 'payroll_run',
            referenceId: $payrollRun->id
        );
    }
}
