<?php

namespace Modules\Billing\Services;

use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\ChartOfAccount;

class AccountingIntegrationService
{
    /**
     * Create journal entry when invoice is issued.
     *
     * Debit: Accounts Receivable
     * Credit: Revenue Account
     * Credit: Tax Payable (if applicable)
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
            return null;
        }

        // Get required accounts
        $arAccount = ChartOfAccount::where('code', '1130')->first(); // Accounts Receivable
        $revenueAccount = ChartOfAccount::where('code', '4110')->first(); // Treatment Revenue
        $taxPayableAccount = ChartOfAccount::where('code', '2120')->first(); // Tax Payable

        if (!$arAccount || !$revenueAccount) {
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

        // Debit: Accounts Receivable (full invoice amount)
        $entry->lines()->create([
            'tenant_id' => $invoice->tenant_id,
            'account_id' => $arAccount->id,
            'debit_minor' => $invoice->total_minor,
            'credit_minor' => 0,
            'description' => "Customer: {$invoice->patient?->full_name}",
            'branch_id' => $invoice->branch_id,
        ]);

        // Credit: Revenue (subtotal amount)
        $revenueAmount = $invoice->subtotal_minor - $invoice->discount_minor;
        $entry->lines()->create([
            'tenant_id' => $invoice->tenant_id,
            'account_id' => $revenueAccount->id,
            'debit_minor' => 0,
            'credit_minor' => $revenueAmount,
            'description' => "Services rendered",
            'branch_id' => $invoice->branch_id,
        ]);

        // Credit: Tax Payable (if tax exists)
        if ($invoice->tax_minor > 0 && $taxPayableAccount) {
            $entry->lines()->create([
                'tenant_id' => $invoice->tenant_id,
                'account_id' => $taxPayableAccount->id,
                'debit_minor' => 0,
                'credit_minor' => $invoice->tax_minor,
                'description' => "VAT on invoice {$invoice->code}",
                'branch_id' => $invoice->branch_id,
            ]);
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

        // Get required accounts
        $arAccount = ChartOfAccount::where('code', '1130')->first(); // Accounts Receivable

        // Determine debit account based on journal type
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
        ]);

        // Credit: Accounts Receivable
        $entry->lines()->create([
            'tenant_id' => $payment->tenant_id,
            'account_id' => $arAccount->id,
            'debit_minor' => 0,
            'credit_minor' => $payment->amount_minor,
            'description' => "Customer: {$invoice?->patient?->full_name}",
            'branch_id' => $invoice?->branch_id,
        ]);

        // Recalculate and post
        $entry->recalculateTotals();
        $entry->post();

        return $entry;
    }

    /**
     * Get account based on journal type.
     */
    protected function getAccountByJournalType(string $type): ?ChartOfAccount
    {
        return match ($type) {
            'cash' => ChartOfAccount::where('code', '1110')->first(), // Cash
            'bank' => ChartOfAccount::where('code', '1120')->first(), // Bank
            default => ChartOfAccount::where('code', '1110')->first(), // Default to Cash
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
