<?php

namespace Modules\Packages\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Services\DefaultAccountsService;
use Modules\Booking\Models\Appointment;
use Modules\Packages\Models\PackageSubscription;
use Modules\Packages\Models\PackageSessionUsage;
use Modules\Patients\Models\Patient;

/**
 * Handles ASC 606 compliant revenue recognition for packages.
 *
 * When a package is sold:
 *   DR: Accounts Receivable (or Cash)
 *   CR: Unearned Revenue (Liability)
 *
 * When a session is delivered:
 *   DR: Unearned Revenue
 *   CR: Service Revenue
 */
class RevenueRecognitionService
{
    protected DefaultAccountsService $defaultAccounts;

    public function __construct()
    {
        $this->defaultAccounts = new DefaultAccountsService();
    }

    /**
     * Get the PackageItem for a specific service in a subscription's package.
     */
    public function getPackageItemForService(PackageSubscription $subscription, int $serviceId): ?\Modules\Packages\Models\PackageItem
    {
        return $subscription->package?->items()
            ->where('service_id', $serviceId)
            ->first();
    }

    /**
     * Calculate the per-session value for a specific service in the package.
     * Now uses item-level pricing instead of averaged package price.
     */
    public function getPerSessionValue(PackageSubscription $subscription, ?int $serviceId = null): int
    {
        // If service ID provided, get item-specific price
        if ($serviceId) {
            $item = $this->getPackageItemForService($subscription, $serviceId);
            if ($item) {
                return $item->unit_price_minor;
            }
        }

        // Fallback: calculate average (for backwards compatibility)
        $totalSessions = $subscription->package?->total_sessions ?? 0;
        if ($totalSessions <= 0) {
            return 0;
        }

        return (int) floor($subscription->package_price_minor / $totalSessions);
    }

    /**
     * Calculate the per-pulse value for a specific service.
     * Uses item-level pricing with pulses_per_session.
     */
    public function getPerPulseValue(PackageSubscription $subscription, ?int $serviceId = null): float
    {
        // If service ID provided, get item-specific price
        if ($serviceId) {
            $item = $this->getPackageItemForService($subscription, $serviceId);
            if ($item && $item->pulses_per_session > 0) {
                return $item->unit_price_minor / $item->pulses_per_session;
            }
        }

        // Fallback: calculate from total package
        $totalPulses = $subscription->package?->total_pulses ?? 0;
        if ($totalPulses <= 0) {
            return 0;
        }

        return $subscription->package_price_minor / $totalPulses;
    }

    /**
     * Calculate amount to recognize based on usage.
     * Now uses item-level pricing for accurate revenue recognition.
     */
    public function calculateRecognitionAmount(
        PackageSubscription $subscription,
        int $quantityUsed,
        string $unitType = 'session',
        ?int $serviceId = null
    ): int {
        if ($unitType === 'pulse') {
            $perPulseValue = $this->getPerPulseValue($subscription, $serviceId);
            return (int) round($perPulseValue * $quantityUsed);
        }

        // Session-based: recognize per-session value for the specific service
        return $this->getPerSessionValue($subscription, $serviceId) * $quantityUsed;
    }

    /**
     * Create revenue recognition journal entry when a session is used.
     *
     * When session is delivered:
     *   DR: Unearned Revenue (decrease liability) - from service category
     *   CR: Service Revenue (increase income) - from service category
     */
    public function recognizeSessionRevenue(
        PackageSubscription $subscription,
        PackageSessionUsage $usage,
        ?Appointment $appointment = null
    ): ?JournalEntry {
        // Get General Journal or Sales Journal
        $journal = Journal::getSalesJournal();
        if (!$journal) {
            \Log::warning('RevenueRecognitionService: Sales journal not found');
            return null;
        }

        // Get accounts from service category, fall back to defaults
        $service = $usage->service()->with('category')->first();
        $category = $service?->category;

        // Unearned Revenue: service category -> subscription -> default
        $unearnedRevenueAccount = null;
        if ($category?->unearned_revenue_account_id) {
            $unearnedRevenueAccount = \Modules\Accounting\Models\ChartOfAccount::find($category->unearned_revenue_account_id);
        }
        if (!$unearnedRevenueAccount && $subscription->unearned_revenue_account_id) {
            $unearnedRevenueAccount = \Modules\Accounting\Models\ChartOfAccount::find($subscription->unearned_revenue_account_id);
        }
        if (!$unearnedRevenueAccount) {
            $unearnedRevenueAccount = $this->defaultAccounts->getPackageUnearnedRevenueAccount();
        }

        // Service Revenue: service category -> default
        $revenueAccount = null;
        if ($category?->service_revenue_account_id) {
            $revenueAccount = \Modules\Accounting\Models\ChartOfAccount::find($category->service_revenue_account_id);
        }
        if (!$revenueAccount) {
            $revenueAccount = $this->defaultAccounts->getPackageRevenueAccount();
        }

        if (!$unearnedRevenueAccount || !$revenueAccount) {
            \Log::warning('RevenueRecognitionService: Required accounts not found', [
                'unearned_account' => $unearnedRevenueAccount?->id,
                'revenue_account' => $revenueAccount?->id,
            ]);
            return null;
        }

        // Calculate amount to recognize using item-level pricing
        $amountToRecognize = $this->calculateRecognitionAmount(
            $subscription,
            $usage->quantity_used,
            $usage->unit_type,
            $usage->service_id
        );

        if ($amountToRecognize <= 0) {
            \Log::info('RevenueRecognitionService: Zero amount to recognize', [
                'subscription_id' => $subscription->id,
                'usage_id' => $usage->id,
            ]);
            return null;
        }

        // Ensure we don't over-recognize
        $maxRecognizable = $subscription->unrecognized_revenue_minor;
        $amountToRecognize = min($amountToRecognize, $maxRecognizable);

        if ($amountToRecognize <= 0) {
            \Log::info('RevenueRecognitionService: All revenue already recognized', [
                'subscription_id' => $subscription->id,
            ]);
            return null;
        }

        $package = $subscription->package;
        $patient = $subscription->patient;
        $service = $usage->service;
        $sessionNumber = $subscription->sessions_used;
        $totalSessions = $package->total_sessions;

        return DB::transaction(function () use (
            $subscription,
            $usage,
            $appointment,
            $journal,
            $unearnedRevenueAccount,
            $revenueAccount,
            $amountToRecognize,
            $package,
            $patient,
            $service,
            $sessionNumber,
            $totalSessions
        ) {
            // Create journal entry
            $entry = JournalEntry::create([
                'tenant_id' => $subscription->tenant_id,
                'journal_id' => $journal->id,
                'date' => $usage->used_at ?? now(),
                'reference' => $appointment?->code ?? "PKG-{$subscription->id}-{$usage->id}",
                'description' => "Revenue Recognition: {$package->translated_name} - Session {$sessionNumber}/{$totalSessions} - {$patient?->full_name}",
                'source_type' => PackageSessionUsage::class,
                'source_id' => $usage->id,
            ]);

            $description = $service?->translated_name ?? 'Package Service';
            if ($usage->unit_type === 'pulse') {
                $description .= " ({$usage->quantity_used} pulses)";
            }

            // Debit: Unearned Revenue (decrease liability)
            $entry->lines()->create([
                'tenant_id' => $subscription->tenant_id,
                'account_id' => $unearnedRevenueAccount->id,
                'debit_minor' => $amountToRecognize,
                'credit_minor' => 0,
                'description' => "Recognize: {$description}",
                'branch_id' => $subscription->branch_id,
                'partner_type' => Patient::class,
                'partner_id' => $subscription->patient_id,
            ]);

            // Credit: Service Revenue (increase income)
            $entry->lines()->create([
                'tenant_id' => $subscription->tenant_id,
                'account_id' => $revenueAccount->id,
                'debit_minor' => 0,
                'credit_minor' => $amountToRecognize,
                'description' => "Package: {$package->translated_name} - {$description}",
                'branch_id' => $subscription->branch_id,
                'partner_type' => Patient::class,
                'partner_id' => $subscription->patient_id,
            ]);

            // Recalculate and post
            $entry->recalculateTotals();
            $entry->post();

            // Update usage with journal entry reference
            $usage->update(['journal_entry_id' => $entry->id]);

            // Update subscription revenue tracking
            $subscription->recordRevenueRecognition($amountToRecognize);

            \Log::info('RevenueRecognitionService: Revenue recognized', [
                'subscription_id' => $subscription->id,
                'usage_id' => $usage->id,
                'entry_id' => $entry->id,
                'amount_recognized' => $amountToRecognize,
                'total_recognized' => $subscription->recognized_revenue_minor,
                'remaining_unrecognized' => $subscription->unrecognized_revenue_minor,
            ]);

            return $entry;
        });
    }

    /**
     * Recognize revenue for multiple usages (batch processing).
     */
    public function recognizeBatchRevenue(array $usageIds): array {
        $results = [];

        foreach ($usageIds as $usageId) {
            $usage = PackageSessionUsage::with(['subscription', 'appointment'])->find($usageId);

            if (!$usage || $usage->hasRevenueRecognition()) {
                continue;
            }

            $entry = $this->recognizeSessionRevenue(
                $usage->subscription,
                $usage,
                $usage->appointment
            );

            $results[$usageId] = [
                'success' => $entry !== null,
                'entry_id' => $entry?->id,
            ];
        }

        return $results;
    }

    /**
     * Calculate total deferred revenue for a patient (all active packages).
     */
    public function getPatientDeferredRevenue(int $patientId): int
    {
        return PackageSubscription::where('patient_id', $patientId)
            ->whereIn('status', [
                PackageSubscription::STATUS_ACTIVE,
                PackageSubscription::STATUS_FROZEN,
            ])
            ->sum('unrecognized_revenue_minor');
    }

    /**
     * Calculate total deferred revenue for a branch.
     */
    public function getBranchDeferredRevenue(int $branchId): int
    {
        return PackageSubscription::where('branch_id', $branchId)
            ->whereIn('status', [
                PackageSubscription::STATUS_ACTIVE,
                PackageSubscription::STATUS_FROZEN,
            ])
            ->sum('unrecognized_revenue_minor');
    }

    /**
     * Get revenue recognition summary for a subscription.
     */
    public function getRecognitionSummary(PackageSubscription $subscription): array
    {
        $package = $subscription->package;
        $totalSessions = $package->total_sessions;
        $sessionsUsed = $subscription->sessions_used;
        $sessionsRemaining = $subscription->sessions_remaining;

        // Get per-item breakdown
        $itemBreakdown = [];
        foreach ($package->items as $item) {
            $itemBreakdown[] = [
                'service_name' => $item->service?->translated_name ?? 'Unknown',
                'quantity' => $item->quantity,
                'consumption_type' => $item->consumption_type,
                'unit_price' => $item->unit_price_minor,
                'total_price' => $item->total_price_minor,
                'pulses_per_session' => $item->pulses_per_session,
            ];
        }

        return [
            'subscription_id' => $subscription->id,
            'package_name' => $package->translated_name,
            'total_price' => $subscription->package_price_minor,
            'recognized_revenue' => $subscription->recognized_revenue_minor,
            'unrecognized_revenue' => $subscription->unrecognized_revenue_minor,
            'recognition_percentage' => $subscription->package_price_minor > 0
                ? round(($subscription->recognized_revenue_minor / $subscription->package_price_minor) * 100, 2)
                : 0,
            'total_sessions' => $totalSessions,
            'sessions_used' => $sessionsUsed,
            'sessions_remaining' => $sessionsRemaining,
            'items' => $itemBreakdown,
            'status' => $subscription->status,
            'expires_at' => $subscription->expires_at?->format('Y-m-d'),
        ];
    }

    /**
     * Process pending revenue recognitions (for usages without journal entries).
     * Useful for batch processing or fixing missed recognitions.
     */
    public function processPendingRecognitions(?int $subscriptionId = null): array
    {
        $query = PackageSessionUsage::whereNull('journal_entry_id')
            ->with(['subscription', 'appointment']);

        if ($subscriptionId) {
            $query->whereHas('subscription', fn ($q) => $q->where('id', $subscriptionId));
        }

        $pendingUsages = $query->get();
        $results = [];

        foreach ($pendingUsages as $usage) {
            if (!$usage->subscription) {
                continue;
            }

            $entry = $this->recognizeSessionRevenue(
                $usage->subscription,
                $usage,
                $usage->appointment
            );

            $results[] = [
                'usage_id' => $usage->id,
                'subscription_id' => $usage->subscription_id,
                'success' => $entry !== null,
                'entry_id' => $entry?->id,
            ];
        }

        return $results;
    }

    /**
     * Reverse revenue recognition (for cancelled/voided sessions).
     */
    public function reverseRecognition(PackageSessionUsage $usage, string $reason): ?JournalEntry
    {
        if (!$usage->hasRevenueRecognition()) {
            return null;
        }

        $originalEntry = $usage->journalEntry;
        if (!$originalEntry) {
            return null;
        }

        return DB::transaction(function () use ($usage, $originalEntry, $reason) {
            // Reverse the journal entry
            $reversalEntry = $originalEntry->reverse($reason);

            // Update subscription revenue tracking (subtract recognized amount)
            $recognizedAmount = $originalEntry->total_debit_minor; // Amount that was recognized
            $subscription = $usage->subscription;

            $subscription->update([
                'recognized_revenue_minor' => max(0, $subscription->recognized_revenue_minor - $recognizedAmount),
                'unrecognized_revenue_minor' => $subscription->unrecognized_revenue_minor + $recognizedAmount,
            ]);

            // Clear journal entry reference on usage
            $usage->update(['journal_entry_id' => null]);

            return $reversalEntry;
        });
    }
}
