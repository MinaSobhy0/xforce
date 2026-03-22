<?php

namespace Modules\Billing\Services;

use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\DefaultAccountsService;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\TaxRate;
use Modules\Patients\Models\Patient;

class AccountingIntegrationService
{
    protected DefaultAccountsService $defaultAccounts;

    public function __construct()
    {
        $this->defaultAccounts = new DefaultAccountsService;
    }

    /**
     * Create journal entry when invoice is issued.
     *
     * Debit: Accounts Receivable (total amount customer owes)
     * Debit: Tax Receivable (for negative taxes like Withholding - we get back from govt)
     * Debit: Sales Discount (if invoice-level discount exists)
     * Credit: Revenue Account (per line)
     * Credit: Tax Payable (for positive taxes like VAT - we owe to govt)
     */
    public function createInvoiceJournalEntry(Invoice $invoice): ?JournalEntry
    {
        // Only create for issued invoices
        if (! $invoice->isIssued() && ! $invoice->isPartiallyPaid() && ! $invoice->isPaid()) {
            return null;
        }

        // Load relationships (including product category for account hierarchy)
        $invoice->load('lines.product.category', 'lines.service.category', 'patient');

        // Get Sales Journal
        $salesJournal = Journal::getSalesJournal();
        if (! $salesJournal) {
            \Log::warning('AccountingIntegrationService: Sales journal not found');

            return null;
        }

        // Get Accounts Receivable account from defaults
        $arAccount = $this->defaultAccounts->getPatientReceivableAccount();

        if (! $arAccount) {
            \Log::warning('AccountingIntegrationService: AR account not found');

            return null;
        }

        // Group revenue by account and taxes by tax account
        $revenueByAccount = [];
        $taxByAccount = []; // [key => ['account_id' => id, 'amount' => int, 'is_credit' => bool]]
        $linesSubtotal = 0; // Sum of line subtotals (after line-level discounts, before tax)
        $totalVat = 0;
        $totalWithholding = 0;

        foreach ($invoice->lines as $line) {
            // Calculate line revenue (subtotal - discount, before tax)
            $lineRevenue = (int) round($line->quantity * $line->unit_price_minor);
            // Apply line-level discount
            if ($line->discount_minor > 0) {
                if ($line->discount_type === 'percent') {
                    $lineRevenue -= (int) round($lineRevenue * $line->discount_minor / 100);
                } else {
                    $lineRevenue -= $line->discount_minor;
                }
            }
            $afterDiscount = max(0, $lineRevenue);

            $linesSubtotal += $afterDiscount;

            // Revenue account hierarchy: Line -> Product -> Product Category -> Service -> Service Category -> Default
            $revenueAccountId = $line->account_id
                ?? $line->product?->getEffectiveIncomeAccountId()
                ?? $this->getDefaultRevenueAccountForLine($line)?->id;

            if ($revenueAccountId) {
                if (! isset($revenueByAccount[$revenueAccountId])) {
                    $revenueByAccount[$revenueAccountId] = 0;
                }
                $revenueByAccount[$revenueAccountId] += $afterDiscount;
            }

            // Process each tax rate in the tax_rates array
            $taxRates = $line->tax_rates ?? [];
            foreach ($taxRates as $rateValue) {
                $rateFloat = floatval($rateValue);
                if ($rateFloat == 0) {
                    continue;
                }

                // Calculate tax amount for this rate
                $taxAmount = (int) round($afterDiscount * abs($rateFloat) / 100);
                if ($taxAmount <= 0) {
                    continue;
                }

                // Find the tax rate record to get its account
                $taxRate = TaxRate::where('rate', $rateFloat)
                    ->where('type', TaxRate::TYPE_SALES)
                    ->first();

                // For sales invoices:
                // Positive tax (VAT): Credit Tax Payable (we owe to government)
                // Negative tax (Withholding): Debit Tax Receivable (we get back from government)
                $isPositiveTax = $rateFloat > 0;

                if ($isPositiveTax) {
                    // Positive tax (VAT): Credit Tax Payable
                    $taxAccountId = $taxRate?->account_id
                        ?? $this->defaultAccounts->getTaxPayableAccount()?->id;
                    $totalVat += $taxAmount;
                } else {
                    // Negative tax (Withholding): Debit Tax Receivable
                    $taxAccountId = $taxRate?->account_id
                        ?? $this->defaultAccounts->getTaxReceivableAccount()?->id;
                    $totalWithholding += $taxAmount;
                }

                if ($taxAccountId) {
                    $key = $taxAccountId.'_'.($isPositiveTax ? 'credit' : 'debit');
                    if (! isset($taxByAccount[$key])) {
                        $taxByAccount[$key] = [
                            'account_id' => $taxAccountId,
                            'amount' => 0,
                            'is_credit' => $isPositiveTax,
                        ];
                    }
                    $taxByAccount[$key]['amount'] += $taxAmount;
                }
            }
        }

        // Calculate invoice-level discount
        $invoiceDiscount = 0;
        if ($invoice->discount_minor > 0) {
            if ($invoice->discount_type === Invoice::DISCOUNT_PERCENT) {
                $invoiceDiscount = (int) round($linesSubtotal * $invoice->discount_minor / 100);
            } else {
                $invoiceDiscount = $invoice->discount_minor;
            }
        }

        // Calculate total AR (what customer owes): subtotal - invoice_discount + VAT - withholding
        $totalAR = $linesSubtotal - $invoiceDiscount + $totalVat - $totalWithholding;

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

        // Debit: Accounts Receivable (what customer actually owes)
        $entry->lines()->create([
            'tenant_id' => $invoice->tenant_id,
            'account_id' => $arAccount->id,
            'debit_minor' => $totalAR,
            'credit_minor' => 0,
            'description' => "Customer: {$invoice->patient?->full_name}",
            'branch_id' => $invoice->branch_id,
            'partner_type' => $invoice->patient_id ? Patient::class : null,
            'partner_id' => $invoice->patient_id,
        ]);

        // Debit: Sales Discount (if invoice-level discount exists)
        if ($invoiceDiscount > 0) {
            $discountAccount = $this->defaultAccounts->getDiscountAccount();
            if ($discountAccount) {
                $entry->lines()->create([
                    'tenant_id' => $invoice->tenant_id,
                    'account_id' => $discountAccount->id,
                    'debit_minor' => $invoiceDiscount,
                    'credit_minor' => 0,
                    'description' => "Discount on invoice {$invoice->code}",
                    'branch_id' => $invoice->branch_id,
                    'partner_type' => $invoice->patient_id ? Patient::class : null,
                    'partner_id' => $invoice->patient_id,
                ]);
            }
        }

        // Credit: Revenue accounts
        foreach ($revenueByAccount as $accountId => $amount) {
            if ($amount > 0) {
                $entry->lines()->create([
                    'tenant_id' => $invoice->tenant_id,
                    'account_id' => $accountId,
                    'debit_minor' => 0,
                    'credit_minor' => $amount,
                    'description' => 'Services revenue',
                    'branch_id' => $invoice->branch_id,
                    'partner_type' => $invoice->patient_id ? Patient::class : null,
                    'partner_id' => $invoice->patient_id,
                ]);
            }
        }

        // Tax account entries
        foreach ($taxByAccount as $taxData) {
            if ($taxData['amount'] > 0) {
                if ($taxData['is_credit']) {
                    // Positive tax (VAT): Credit Tax Payable
                    $entry->lines()->create([
                        'tenant_id' => $invoice->tenant_id,
                        'account_id' => $taxData['account_id'],
                        'debit_minor' => 0,
                        'credit_minor' => $taxData['amount'],
                        'description' => "Output VAT on invoice {$invoice->code}",
                        'branch_id' => $invoice->branch_id,
                        'partner_type' => $invoice->patient_id ? Patient::class : null,
                        'partner_id' => $invoice->patient_id,
                    ]);
                } else {
                    // Negative tax (Withholding): Debit Tax Receivable
                    $entry->lines()->create([
                        'tenant_id' => $invoice->tenant_id,
                        'account_id' => $taxData['account_id'],
                        'debit_minor' => $taxData['amount'],
                        'credit_minor' => 0,
                        'description' => "Withholding tax on invoice {$invoice->code}",
                        'branch_id' => $invoice->branch_id,
                        'partner_type' => $invoice->patient_id ? Patient::class : null,
                        'partner_id' => $invoice->patient_id,
                    ]);
                }
            }
        }

        // Recalculate and post
        $entry->recalculateTotals();
        $entry->post();

        \Log::info('Invoice: Journal entry created', [
            'invoice_id' => $invoice->id,
            'invoice_code' => $invoice->code,
            'entry_id' => $entry->id,
            'lines_subtotal' => $linesSubtotal,
            'invoice_discount' => $invoiceDiscount,
            'total_vat' => $totalVat,
            'total_withholding' => $totalWithholding,
            'total_ar' => $totalAR,
        ]);

        return $entry;
    }

    /**
     * Create journal entry when payment is received.
     *
     * For Cash/Bank:
     *   Debit: Cash/Bank (based on payment journal)
     *   Credit: Accounts Receivable
     *
     * For Gift Card: Skip - the GiftCardService handles GL entries
     */
    public function createPaymentJournalEntry(Payment $payment): ?JournalEntry
    {
        // Get payment journal (Cash, Bank, Card, etc.)
        $paymentJournal = $payment->journal;
        if (! $paymentJournal) {
            \Log::warning('AccountingIntegrationService: Payment has no journal', [
                'payment_id' => $payment->id,
            ]);

            return null;
        }

        // For gift card payments, the GL entry is handled by GiftCardService::redeem()
        // which creates: DR Gift Card Liability, CR Accounts Receivable
        if ($paymentJournal->type === 'gift_card') {
            return null;
        }

        // Get Accounts Receivable account from defaults
        $arAccount = $this->defaultAccounts->getPatientReceivableAccount();

        // Determine debit account based on journal type or default
        $debitAccount = $paymentJournal->default_debit_account_id
            ? ChartOfAccount::find($paymentJournal->default_debit_account_id)
            : $this->getAccountByJournalType($paymentJournal->type);

        if (! $arAccount || ! $debitAccount) {
            \Log::warning('AccountingIntegrationService: Missing AR or debit account for payment', [
                'payment_id' => $payment->id,
                'ar_account' => $arAccount?->id,
                'debit_account' => $debitAccount?->id,
            ]);

            return null;
        }

        // Get details from invoice if available, otherwise from payment directly
        $invoice = $payment->invoice;
        $patient = $invoice?->patient ?? $payment->patient;
        $patientId = $invoice?->patient_id ?? $payment->patient_id;
        $branchId = $invoice?->branch_id ?? $payment->branch_id;

        // Build description
        $description = 'Payment';
        if ($invoice) {
            $description .= " for Invoice {$invoice->code}";
        } elseif ($payment->appointment_id) {
            $description .= ' for Appointment';
        }
        if ($patient) {
            $description .= " - {$patient->full_name}";
        }

        // Create journal entry
        $entry = JournalEntry::create([
            'tenant_id' => $payment->tenant_id,
            'journal_id' => $paymentJournal->id,
            'date' => $payment->paid_at ?? now(),
            'reference' => $invoice?->code ?? $payment->reference_number,
            'description' => $description,
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
            'branch_id' => $branchId,
            'partner_type' => $patientId ? Patient::class : null,
            'partner_id' => $patientId,
        ]);

        // Credit: Accounts Receivable
        $entry->lines()->create([
            'tenant_id' => $payment->tenant_id,
            'account_id' => $arAccount->id,
            'debit_minor' => 0,
            'credit_minor' => $payment->amount_minor,
            'description' => "Customer: {$patient?->full_name}",
            'branch_id' => $branchId,
            'partner_type' => $patientId ? Patient::class : null,
            'partner_id' => $patientId,
        ]);

        // Recalculate and post
        $entry->recalculateTotals();
        $entry->post();

        \Log::info('AccountingIntegrationService: Payment journal entry created', [
            'payment_id' => $payment->id,
            'payment_code' => $payment->code,
            'entry_id' => $entry->id,
            'amount_minor' => $payment->amount_minor,
        ]);

        return $entry;
    }

    /**
     * Get default revenue account based on line item type.
     * This handles the fallback when line.account_id and product.getEffectiveIncomeAccountId() are both null.
     */
    protected function getDefaultRevenueAccountForLine($line): ?ChartOfAccount
    {
        // If line is a package, credit Unearned Revenue (deferred revenue)
        // Revenue recognition happens when sessions are used via RevenueRecognitionService
        if ($line->line_type === \Modules\Billing\Models\InvoiceLine::LINE_TYPE_PACKAGE) {
            return $this->defaultAccounts->getPackageUnearnedRevenueAccount();
        }

        // If line has a product, use product's effective revenue account (product -> category -> null)
        if ($line->product_id && $line->product) {
            $productAccount = $line->product->getEffectiveIncomeAccount();
            if ($productAccount) {
                return $productAccount;
            }
        }

        // If line has a service, use service's effective revenue account (service -> category -> null)
        if ($line->service_id && $line->service) {
            $serviceAccount = $line->service->getEffectiveServiceRevenueAccount();
            if ($serviceAccount) {
                return $serviceAccount;
            }
        }

        // Default to service revenue from system defaults
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
            'gift_card' => $this->defaultAccounts->getGiftCardLiabilityAccount(),
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
