<?php

namespace Modules\Billing\Services;

use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\TaxRate;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Services\DefaultAccountsService;
use Modules\Patients\Models\Patient;

class AccountingIntegrationService
{
    protected DefaultAccountsService $defaultAccounts;

    public function __construct()
    {
        $this->defaultAccounts = new DefaultAccountsService();
    }

    /**
     * Create journal entry when invoice is issued.
     *
     * Debit: Accounts Receivable
     * Credit: Revenue Account (per line)
     * Credit: Tax Payable (per tax rate account)
     */
    public function createInvoiceJournalEntry(Invoice $invoice): ?JournalEntry
    {
        // Only create for issued invoices
        if (!$invoice->isIssued() && !$invoice->isPartiallyPaid() && !$invoice->isPaid()) {
            return null;
        }

        // Get Sales Journal
        $salesJournal = Journal::getSalesJournal();
        if (!$salesJournal) {
            \Log::warning('AccountingIntegrationService: Sales journal not found');
            return null;
        }

        // Get Accounts Receivable account from defaults
        $arAccount = $this->defaultAccounts->getPatientReceivableAccount();

        if (!$arAccount) {
            \Log::warning('AccountingIntegrationService: AR account not found');
            return null;
        }

        // Create journal entry
        $entry = JournalEntry::create([
            'tenant_id' => $invoice->tenant_id,
            'journal_id' => $salesJournal->id,
            'date' => $invoice->issued_at ?? now(),
            'reference' => $invoice->code,
            'description' => "Invoice {$invoice->code} - {$invoice->patient?->full_name}",
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
        ]);

        // Group revenue by account and taxes by tax account
        $revenueByAccount = [];
        $taxByAccount = [];
        $totalAmount = 0;

        foreach ($invoice->lines as $line) {
            // Calculate line revenue (subtotal - discount, before tax)
            $lineRevenue = (int) round($line->quantity * $line->unit_price_minor);
            // Apply discount
            if ($line->discount_minor > 0) {
                if ($line->discount_type === 'percent') {
                    $lineRevenue -= (int) round($lineRevenue * $line->discount_minor / 100);
                } else {
                    $lineRevenue -= $line->discount_minor;
                }
            }

            $totalAmount += $lineRevenue;

            // Revenue - use line account, or product account, or default revenue account
            $revenueAccountId = $line->account_id
                ?? $line->product?->income_account_id
                ?? $this->getDefaultRevenueAccountForLine($line)?->id;

            if ($revenueAccountId) {
                if (!isset($revenueByAccount[$revenueAccountId])) {
                    $revenueByAccount[$revenueAccountId] = 0;
                }
                $revenueByAccount[$revenueAccountId] += $lineRevenue;
            }

            // Tax - find tax rate and use its account
            if ($line->tax_minor > 0 && $line->tax_rate > 0) {
                $taxRate = TaxRate::where('rate', $line->tax_rate)
                    ->where('type', TaxRate::TYPE_SALES)
                    ->first();

                $taxAccountId = $taxRate?->account_id
                    ?? $this->defaultAccounts->getTaxPayableAccount()?->id;

                if ($taxAccountId) {
                    if (!isset($taxByAccount[$taxAccountId])) {
                        $taxByAccount[$taxAccountId] = 0;
                    }
                    $taxByAccount[$taxAccountId] += $line->tax_minor;
                }

                $totalAmount += $line->tax_minor;
            }
        }

        // Debit: Accounts Receivable (revenue + tax, correctly calculated from lines)
        $entry->lines()->create([
            'tenant_id' => $invoice->tenant_id,
            'account_id' => $arAccount->id,
            'debit_minor' => $totalAmount,
            'credit_minor' => 0,
            'description' => "Customer: {$invoice->patient?->full_name}",
            'branch_id' => $invoice->branch_id,
            'partner_type' => $invoice->patient_id ? Patient::class : null,
            'partner_id' => $invoice->patient_id,
        ]);

        // Credit: Revenue accounts
        foreach ($revenueByAccount as $accountId => $amount) {
            if ($amount > 0) {
                $entry->lines()->create([
                    'tenant_id' => $invoice->tenant_id,
                    'account_id' => $accountId,
                    'debit_minor' => 0,
                    'credit_minor' => $amount,
                    'description' => "Services revenue",
                    'branch_id' => $invoice->branch_id,
                    'partner_type' => $invoice->patient_id ? Patient::class : null,
                    'partner_id' => $invoice->patient_id,
                ]);
            }
        }

        // Credit: Tax accounts (separate line per tax account)
        foreach ($taxByAccount as $accountId => $amount) {
            if ($amount > 0) {
                $entry->lines()->create([
                    'tenant_id' => $invoice->tenant_id,
                    'account_id' => $accountId,
                    'debit_minor' => 0,
                    'credit_minor' => $amount,
                    'description' => "Tax on invoice {$invoice->code}",
                    'branch_id' => $invoice->branch_id,
                    'partner_type' => $invoice->patient_id ? Patient::class : null,
                    'partner_id' => $invoice->patient_id,
                ]);
            }
        }

        // Recalculate and post
        $entry->recalculateTotals();
        $entry->post();

        return $entry;
    }

    /**
     * Create journal entry when payment is received.
     *
     * Debit: Cash/Bank (based on payment journal)
     * Credit: Accounts Receivable
     */
    public function createPaymentJournalEntry(Payment $payment): ?JournalEntry
    {
        // Get payment journal (Cash, Bank, Card, etc.)
        $paymentJournal = $payment->journal;
        if (!$paymentJournal) {
            return null;
        }

        // Get Accounts Receivable account from defaults
        $arAccount = $this->defaultAccounts->getPatientReceivableAccount();

        // Determine debit account based on journal type or default
        $debitAccount = $paymentJournal->default_debit_account_id
            ? ChartOfAccount::find($paymentJournal->default_debit_account_id)
            : $this->getAccountByJournalType($paymentJournal->type);

        if (!$arAccount || !$debitAccount) {
            return null;
        }

        $invoice = $payment->invoice;

        // Create journal entry
        $entry = JournalEntry::create([
            'tenant_id' => $payment->tenant_id,
            'journal_id' => $paymentJournal->id,
            'date' => $payment->paid_at ?? now(),
            'reference' => $payment->code,
            'description' => "Payment {$payment->code} for Invoice {$invoice?->code}",
            'source_type' => Payment::class,
            'source_id' => $payment->id,
        ]);

        // Debit: Cash/Bank
        $entry->lines()->create([
            'tenant_id' => $payment->tenant_id,
            'account_id' => $debitAccount->id,
            'debit_minor' => $payment->amount_minor,
            'credit_minor' => 0,
            'description' => "Payment received via {$paymentJournal->name}",
            'branch_id' => $invoice?->branch_id,
            'partner_type' => $invoice?->patient_id ? Patient::class : null,
            'partner_id' => $invoice?->patient_id,
        ]);

        // Credit: Accounts Receivable
        $entry->lines()->create([
            'tenant_id' => $payment->tenant_id,
            'account_id' => $arAccount->id,
            'debit_minor' => 0,
            'credit_minor' => $payment->amount_minor,
            'description' => "Customer: {$invoice?->patient?->full_name}",
            'branch_id' => $invoice?->branch_id,
            'partner_type' => $invoice?->patient_id ? Patient::class : null,
            'partner_id' => $invoice?->patient_id,
        ]);

        // Recalculate and post
        $entry->recalculateTotals();
        $entry->post();

        return $entry;
    }

    /**
     * Get default revenue account based on line item type.
     */
    protected function getDefaultRevenueAccountForLine($line): ?ChartOfAccount
    {
        // If line has a product, use product revenue account
        if ($line->product_id && $line->product?->income_account_id) {
            return ChartOfAccount::find($line->product->income_account_id);
        }

        // If line has a service, use service revenue account
        if ($line->service_id) {
            return $this->defaultAccounts->getServiceRevenueAccount();
        }

        // Default to service revenue
        return $this->defaultAccounts->getServiceRevenueAccount();
    }

    /**
     * Get account based on journal type using defaults.
     */
    protected function getAccountByJournalType(string $type): ?ChartOfAccount
    {
        return match ($type) {
            'cash' => $this->defaultAccounts->getCashAccount(),
            'bank' => $this->defaultAccounts->getBankAccount(),
            default => $this->defaultAccounts->getCashAccount(),
        };
    }

    /**
     * Create refund journal entry (reverses payment).
     */
    public function createRefundJournalEntry(Payment $payment): ?JournalEntry
    {
        // Find the original payment journal entry
        $originalEntry = JournalEntry::where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->first();

        if ($originalEntry) {
            return $originalEntry->reverse("Refund for payment {$payment->code}");
        }

        return null;
    }
}
