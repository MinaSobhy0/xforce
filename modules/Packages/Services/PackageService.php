<?php

namespace Modules\Packages\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Services\DefaultAccountsService;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\TaxRate;
use Modules\Billing\Services\InvoiceCalculationService;
use Modules\Packages\Models\Package;
use Modules\Packages\Models\PackageSubscription;
use Modules\Packages\Models\PackageSessionUsage;
use Modules\Patients\Models\Patient;

class PackageService
{
    protected DefaultAccountsService $defaultAccounts;
    protected InvoiceCalculationService $invoiceService;

    public function __construct()
    {
        $this->defaultAccounts = new DefaultAccountsService();
        $this->invoiceService = new InvoiceCalculationService();
    }

    /**
     * Sell a package to a patient.
     * Creates subscription, invoice, and journal entry for deferred revenue.
     */
    public function sellPackage(
        int $patientId,
        int $packageId,
        int $branchId,
        int $depositAmount = 0,
        string $activationRule = PackageSubscription::ACTIVATION_IMMEDIATE,
        ?int $createdByUserId = null
    ): PackageSubscription {
        return DB::transaction(function () use (
            $patientId,
            $packageId,
            $branchId,
            $depositAmount,
            $activationRule,
            $createdByUserId
        ) {
            $package = Package::with('items')->findOrFail($packageId);
            $patient = Patient::findOrFail($patientId);

            // Calculate package price from items (uses effective_price_minor)
            $packagePrice = $package->effective_price_minor;
            $minDeposit = $package->min_deposit_amount;

            // Ensure deposit meets minimum requirement
            if ($depositAmount > 0 && $depositAmount < $minDeposit) {
                throw new \InvalidArgumentException(
                    "Deposit must be at least {$minDeposit} (minimum " . ($package->min_deposit_percent) . "%)"
                );
            }

            // If no deposit specified and package requires full payment, pay full amount
            if ($depositAmount === 0 && $package->requiresFullPayment()) {
                $depositAmount = $packagePrice;
            }

            $balanceRemaining = max(0, $packagePrice - $depositAmount);

            // Determine initial status based on activation rule
            $status = PackageSubscription::STATUS_ACTIVE;
            if ($activationRule === PackageSubscription::ACTIVATION_PAID_IN_FULL && $balanceRemaining > 0) {
                $status = PackageSubscription::STATUS_FROZEN; // Pending activation
            }

            // Create the subscription
            $subscription = PackageSubscription::create([
                'tenant_id' => $patient->tenant_id,
                'patient_id' => $patientId,
                'package_id' => $packageId,
                'branch_id' => $branchId,
                'package_price_minor' => $packagePrice,
                'deposit_paid_minor' => $depositAmount,
                'balance_remaining_minor' => $balanceRemaining,
                'activation_rule' => $activationRule,
                'unrecognized_revenue_minor' => $packagePrice,
                'recognized_revenue_minor' => 0,
                'status' => $status,
                'purchased_at' => now(),
                'expires_at' => now()->addDays($package->validity_days),
                'created_by_user_id' => $createdByUserId,
            ]);

            // Create invoice for the package (also links invoice to subscription)
            $invoice = $this->createPackageInvoice($subscription, $createdByUserId);

            // Create journal entry for deferred revenue
            $this->createPackageSaleJournalEntry($subscription, $invoice);

            return $subscription->fresh(['package', 'patient', 'invoice']);
        });
    }

    /**
     * Create an invoice for a package subscription.
     * Creates a line item for each service in the package using the configured price
     * and unearned revenue account from the service category.
     */
    public function createPackageInvoice(
        PackageSubscription $subscription,
        ?int $createdByUserId = null
    ): Invoice {
        $package = $subscription->package()->with('items.service.category')->first();
        $taxRates = $this->invoiceService->getDefaultTaxRates();

        // Calculate totals from all items
        $subtotalMinor = 0;
        $taxMinor = 0;
        $totalMinor = 0;

        $lineData = [];
        foreach ($package->items as $item) {
            $lineTotal = $item->total_price_minor; // quantity * unit_price_minor
            $lineCalc = $this->invoiceService->calculateLine(
                $lineTotal,
                1,
                0,
                'fixed',
                $taxRates
            );

            $subtotalMinor += $lineCalc['subtotal_minor'];
            $taxMinor += $lineCalc['tax_minor'];
            $totalMinor += $lineCalc['total_minor'];

            // Get unearned revenue account from service category
            $unearnedAccountId = $item->service?->category?->unearned_revenue_account_id;

            $lineData[] = [
                'tenant_id' => $subscription->tenant_id,
                'service_id' => $item->service_id,
                'account_id' => $unearnedAccountId,
                'line_type' => InvoiceLine::LINE_TYPE_PACKAGE,
                'description' => $item->service?->translated_name ?? $package->translated_name,
                'quantity' => $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
                'discount_minor' => 0,
                'discount_type' => 'fixed',
                'tax_rates' => $taxRates,
                'tax_minor' => $lineCalc['tax_minor'],
                'total_minor' => $lineCalc['total_minor'],
                'package_subscription_id' => $subscription->id,
                'sort_order' => $item->sort_order ?? 0,
            ];
        }

        // Create invoice
        $invoice = Invoice::create([
            'tenant_id' => $subscription->tenant_id,
            'patient_id' => $subscription->patient_id,
            'branch_id' => $subscription->branch_id,
            'type' => Invoice::TYPE_STANDARD,
            'subtotal_minor' => $subtotalMinor,
            'discount_minor' => 0,
            'tax_minor' => $taxMinor,
            'total_minor' => $totalMinor,
            'notes' => __('packages::packages.invoice_notes', [
                'package' => $package->translated_name,
                'days' => $package->validity_days,
            ]),
            'created_by_user_id' => $createdByUserId,
        ]);

        // Create line items for each service
        foreach ($lineData as $data) {
            $invoice->lines()->create($data);
        }

        // Link invoice to subscription
        $subscription->update(['invoice_id' => $invoice->id]);

        return $invoice;
    }

    /**
     * Create journal entry for package sale.
     *
     * When package is sold:
     *   DR: Cash/Accounts Receivable (amount received/owed)
     *   CR: Unearned Revenue/Deferred Revenue (per service category)
     *
     * Note: The invoice is NOT issued yet - we defer revenue recognition.
     * The invoice journal entry is handled separately when invoice is issued.
     */
    public function createPackageSaleJournalEntry(
        PackageSubscription $subscription,
        Invoice $invoice
    ): ?JournalEntry {
        // Get Sales Journal
        $salesJournal = Journal::getSalesJournal();
        if (!$salesJournal) {
            \Log::warning('PackageService: Sales journal not found');
            return null;
        }

        // Get default accounts
        $defaultUnearnedAccount = $this->defaultAccounts->getPackageUnearnedRevenueAccount();
        $arAccount = $this->defaultAccounts->getPatientReceivableAccount();

        if (!$arAccount) {
            \Log::warning('PackageService: Accounts Receivable account not found');
            return null;
        }

        $package = $subscription->package()->with('items.service.category')->first();
        $patient = $subscription->patient;

        // Create journal entry
        $entry = JournalEntry::create([
            'tenant_id' => $subscription->tenant_id,
            'journal_id' => $salesJournal->id,
            'date' => $subscription->purchased_at ?? now(),
            'reference' => $invoice->code,
            'description' => "Package Sale: {$package->translated_name} - {$patient?->full_name}",
            'source_type' => PackageSubscription::class,
            'source_id' => $subscription->id,
        ]);

        // Calculate total with tax
        $taxRates = $this->invoiceService->getDefaultTaxRates();
        $totalTaxPercent = array_sum(array_map('floatval', $taxRates));
        $taxAmount = (int) round($subscription->package_price_minor * $totalTaxPercent / 100);
        $totalWithTax = $subscription->package_price_minor + $taxAmount;

        // Debit: Accounts Receivable (full amount customer owes including tax)
        $entry->lines()->create([
            'tenant_id' => $subscription->tenant_id,
            'account_id' => $arAccount->id,
            'debit_minor' => $totalWithTax,
            'credit_minor' => 0,
            'description' => "Customer: {$patient?->full_name}",
            'branch_id' => $subscription->branch_id,
            'partner_type' => Patient::class,
            'partner_id' => $subscription->patient_id,
        ]);

        // Credit: Unearned Revenue per service category
        foreach ($package->items as $item) {
            $unearnedAccountId = $item->service?->category?->unearned_revenue_account_id
                ?? $defaultUnearnedAccount?->id;

            if (!$unearnedAccountId) {
                \Log::warning('PackageService: No unearned revenue account for service', [
                    'service_id' => $item->service_id,
                    'service_name' => $item->service?->translated_name,
                ]);
                continue;
            }

            $itemTotal = $item->total_price_minor; // quantity * unit_price_minor

            $entry->lines()->create([
                'tenant_id' => $subscription->tenant_id,
                'account_id' => $unearnedAccountId,
                'debit_minor' => 0,
                'credit_minor' => $itemTotal,
                'description' => "Service: {$item->service?->translated_name}",
                'branch_id' => $subscription->branch_id,
                'partner_type' => Patient::class,
                'partner_id' => $subscription->patient_id,
            ]);
        }

        // Credit: Tax Payable (if applicable)
        if ($taxAmount > 0) {
            $taxAccount = $this->defaultAccounts->getTaxPayableAccount();
            if ($taxAccount) {
                $entry->lines()->create([
                    'tenant_id' => $subscription->tenant_id,
                    'account_id' => $taxAccount->id,
                    'debit_minor' => 0,
                    'credit_minor' => $taxAmount,
                    'description' => "Output VAT on package sale",
                    'branch_id' => $subscription->branch_id,
                    'partner_type' => Patient::class,
                    'partner_id' => $subscription->patient_id,
                ]);
            }
        }

        // Recalculate and post
        $entry->recalculateTotals();
        $entry->post();

        \Log::info('PackageService: Journal entry created for package sale', [
            'subscription_id' => $subscription->id,
            'entry_id' => $entry->id,
            'package_price' => $subscription->package_price_minor,
            'tax_amount' => $taxAmount,
            'total_with_tax' => $totalWithTax,
        ]);

        return $entry;
    }

    /**
     * Record balance payment for a package subscription.
     */
    public function recordBalancePayment(
        PackageSubscription $subscription,
        int $paymentAmount
    ): PackageSubscription {
        return DB::transaction(function () use ($subscription, $paymentAmount) {
            $subscription->recordPayment($paymentAmount);

            // If fully paid and was waiting for activation
            if ($subscription->isFullyPaid() && $subscription->isFrozen()) {
                if ($subscription->activation_rule === PackageSubscription::ACTIVATION_PAID_IN_FULL) {
                    $subscription->unfreeze();
                }
            }

            return $subscription->fresh();
        });
    }

    /**
     * Use a session from a package subscription.
     * Returns the usage record.
     */
    public function useSession(
        PackageSubscription $subscription,
        int $serviceId,
        ?int $appointmentId = null,
        int $quantityUsed = 1,
        ?string $unitType = null
    ): PackageSessionUsage {
        // Validate subscription can be used
        if (!$subscription->isActive()) {
            throw new \InvalidArgumentException('Package subscription is not active');
        }

        // Validate service is in package and get the item
        $packageItem = $subscription->package->items()
            ->where('service_id', $serviceId)
            ->first();

        if (!$packageItem) {
            throw new \InvalidArgumentException('Service is not included in this package');
        }

        // Check remaining sessions for this service
        if ($subscription->getSessionsRemainingByService($serviceId) < $quantityUsed) {
            throw new \InvalidArgumentException('Not enough remaining sessions for this service');
        }

        // Determine unit type from the item's consumption type
        $effectiveUnitType = $unitType ?? ($packageItem->isPulseBased() ? 'pulse' : 'session');

        return $subscription->recordUsage(
            $serviceId,
            $appointmentId,
            $quantityUsed,
            $effectiveUnitType
        );
    }

    /**
     * Get active package subscriptions for a patient that can be used for a specific service.
     */
    public function getAvailableSubscriptionsForService(
        int $patientId,
        int $serviceId
    ): \Illuminate\Database\Eloquent\Collection {
        return PackageSubscription::where('patient_id', $patientId)
            ->active()
            ->forService($serviceId)
            ->with(['package', 'package.items'])
            ->get()
            ->filter(fn ($sub) => $sub->hasRemainingSessionsForService($serviceId));
    }

    /**
     * Check if patient has available package sessions for a service.
     */
    public function hasAvailablePackageSession(int $patientId, int $serviceId): bool
    {
        return $this->getAvailableSubscriptionsForService($patientId, $serviceId)->isNotEmpty();
    }

    /**
     * Transfer a package subscription to another patient.
     */
    public function transferSubscription(
        PackageSubscription $subscription,
        int $newPatientId,
        ?string $reason = null
    ): PackageSubscription {
        if (!$subscription->package->is_transferable) {
            throw new \InvalidArgumentException('This package is not transferable');
        }

        if (!$subscription->isActive()) {
            throw new \InvalidArgumentException('Only active subscriptions can be transferred');
        }

        return DB::transaction(function () use ($subscription, $newPatientId, $reason) {
            $oldPatientId = $subscription->patient_id;

            $subscription->update([
                'patient_id' => $newPatientId,
                'notes' => ($subscription->notes ? $subscription->notes . "\n" : '') .
                    "Transferred from patient ID {$oldPatientId}" .
                    ($reason ? ": {$reason}" : '') .
                    " on " . now()->format('Y-m-d H:i'),
            ]);

            return $subscription->fresh(['patient', 'package']);
        });
    }

    /**
     * Cancel a package subscription with optional refund calculation.
     */
    public function cancelSubscription(
        PackageSubscription $subscription,
        ?string $reason = null,
        bool $processRefund = false
    ): array {
        if (!$subscription->canTransitionTo(PackageSubscription::STATUS_CANCELLED)) {
            throw new \InvalidArgumentException('Subscription cannot be cancelled');
        }

        return DB::transaction(function () use ($subscription, $reason, $processRefund) {
            $refundAmount = 0;

            if ($processRefund) {
                // Calculate refund based on unused sessions
                $usedSessions = $subscription->sessions_used;
                $totalSessions = $subscription->package->total_sessions;

                if ($totalSessions > 0 && $usedSessions < $totalSessions) {
                    $unusedRatio = ($totalSessions - $usedSessions) / $totalSessions;
                    $refundAmount = (int) round($subscription->package_price_minor * $unusedRatio);
                }
            }

            $subscription->cancel($reason);

            return [
                'subscription' => $subscription->fresh(),
                'refund_amount' => $refundAmount,
                'sessions_used' => $subscription->sessions_used,
                'sessions_remaining' => $subscription->sessions_remaining,
            ];
        });
    }

    /**
     * Extend a package subscription.
     */
    public function extendSubscription(
        PackageSubscription $subscription,
        int $additionalDays
    ): PackageSubscription {
        if (!in_array($subscription->status, [
            PackageSubscription::STATUS_ACTIVE,
            PackageSubscription::STATUS_FROZEN,
        ])) {
            throw new \InvalidArgumentException('Only active or frozen subscriptions can be extended');
        }

        $subscription->update([
            'expires_at' => $subscription->expires_at->addDays($additionalDays),
            'notes' => ($subscription->notes ? $subscription->notes . "\n" : '') .
                "Extended by {$additionalDays} days on " . now()->format('Y-m-d H:i'),
        ]);

        return $subscription->fresh();
    }
}
