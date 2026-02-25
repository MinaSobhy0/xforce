<?php

namespace Modules\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;

class CashManagementService
{
    /**
     * Get the current tenant ID from various sources.
     */
    protected function getTenantId(): ?string
    {
        // Try TenantManager first
        try {
            $tenantManager = app(\XLinic\Framework\Core\Tenancy\TenantManager::class);
            if ($tenantManager->current()) {
                return $tenantManager->current()->id;
            }
        } catch (\Exception $e) {
            // Ignore
        }

        // Try app('currentTenant')
        try {
            if ($tenant = app('currentTenant')) {
                return $tenant->id;
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return null;
    }

    /**
     * Get all bank/cash accounts for dropdown.
     */
    public function getCashBankAccounts(): Collection
    {
        return ChartOfAccount::where('is_active', true)
            ->where('type', ChartOfAccount::TYPE_BANK_CASH)
            ->orderBy('code')
            ->get();
    }

    /**
     * Get current balance for an account.
     * Returns balance in minor units (cents).
     */
    public function getAccountBalance(string $accountId): int
    {
        $account = ChartOfAccount::find($accountId);

        if (!$account) {
            return 0;
        }

        // Calculate from posted journal entries
        $lines = JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) {
                $q->where('status', JournalEntry::STATUS_POSTED);
            })
            ->get();

        $totalDebit = $lines->sum('debit_minor');
        $totalCredit = $lines->sum('credit_minor');

        // Bank/Cash accounts are debit-normal (Asset)
        return $totalDebit - $totalCredit;
    }

    /**
     * Get today's activity summary (total in/out) for an account.
     */
    public function getTodayActivity(string $accountId): array
    {
        $today = Carbon::today();

        $lines = JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($today) {
                $q->where('status', JournalEntry::STATUS_POSTED)
                    ->whereDate('date', $today);
            })
            ->get();

        return [
            'cash_in' => $lines->sum('debit_minor'),
            'cash_out' => $lines->sum('credit_minor'),
        ];
    }

    /**
     * Get recent transactions for an account.
     */
    public function getRecentTransactions(string $accountId, int $limit = 20): Collection
    {
        $lines = JournalEntryLine::with(['journalEntry', 'partner'])
            ->where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) {
                $q->where('status', JournalEntry::STATUS_POSTED);
            })
            ->orderByDesc(
                JournalEntry::select('date')
                    ->whereColumn('journal_entries.id', 'journal_entry_lines.journal_entry_id')
                    ->limit(1)
            )
            ->limit($limit)
            ->get();

        return $lines->map(function ($line) {
            $entry = $line->journalEntry;
            return [
                'id' => $entry->id,
                'date' => $entry->date->format('Y-m-d'),
                'code' => $entry->code,
                'reference' => $entry->reference,
                'description' => $line->description ?: $entry->description,
                'cash_in' => $line->debit_minor,
                'cash_out' => $line->credit_minor,
                'partner_name' => $line->partner?->name ?? null,
            ];
        });
    }

    /**
     * Get counter accounts filtered by transaction type.
     */
    public function getCounterAccounts(string $transactionType): Collection
    {
        $types = [];

        if ($transactionType === 'cash_in') {
            // For Cash In: accounts that typically receive credits
            $types = [
                ChartOfAccount::TYPE_INCOME,
                ChartOfAccount::TYPE_OTHER_INCOME,
                ChartOfAccount::TYPE_RECEIVABLE,
                ChartOfAccount::TYPE_EQUITY,
                ChartOfAccount::TYPE_BANK_CASH,
                ChartOfAccount::TYPE_CURRENT_LIABILITY,
                ChartOfAccount::TYPE_PAYABLE,
            ];
        } else {
            // For Cash Out: accounts that typically receive debits
            $types = [
                ChartOfAccount::TYPE_EXPENSE,
                ChartOfAccount::TYPE_DEPRECIATION,
                ChartOfAccount::TYPE_COST_OF_REVENUE,
                ChartOfAccount::TYPE_PAYABLE,
                ChartOfAccount::TYPE_EQUITY,
                ChartOfAccount::TYPE_BANK_CASH,
                ChartOfAccount::TYPE_CURRENT_ASSET,
                ChartOfAccount::TYPE_RECEIVABLE,
            ];
        }

        return ChartOfAccount::where('is_active', true)
            ->whereIn('type', $types)
            ->orderBy('code')
            ->get();
    }

    /**
     * Record a cash in transaction (creates journal entry).
     * Money coming into the cash/bank account.
     *
     * Debit: Cash/Bank Account
     * Credit: Counter Account
     */
    public function recordCashIn(
        string $cashAccountId,
        string $counterAccountId,
        int $amountMinor,
        Carbon $date,
        ?string $partnerId = null,
        ?string $partnerType = null,
        ?string $reference = null,
        ?string $description = null
    ): JournalEntry {
        return $this->createCashTransaction(
            cashAccountId: $cashAccountId,
            counterAccountId: $counterAccountId,
            amountMinor: $amountMinor,
            date: $date,
            isCashIn: true,
            partnerId: $partnerId,
            partnerType: $partnerType,
            reference: $reference,
            description: $description
        );
    }

    /**
     * Record a cash out transaction (creates journal entry).
     * Money going out of the cash/bank account.
     *
     * Debit: Counter Account
     * Credit: Cash/Bank Account
     */
    public function recordCashOut(
        string $cashAccountId,
        string $counterAccountId,
        int $amountMinor,
        Carbon $date,
        ?string $partnerId = null,
        ?string $partnerType = null,
        ?string $reference = null,
        ?string $description = null
    ): JournalEntry {
        return $this->createCashTransaction(
            cashAccountId: $cashAccountId,
            counterAccountId: $counterAccountId,
            amountMinor: $amountMinor,
            date: $date,
            isCashIn: false,
            partnerId: $partnerId,
            partnerType: $partnerType,
            reference: $reference,
            description: $description
        );
    }

    /**
     * Create a cash transaction journal entry.
     */
    protected function createCashTransaction(
        string $cashAccountId,
        string $counterAccountId,
        int $amountMinor,
        Carbon $date,
        bool $isCashIn,
        ?string $partnerId = null,
        ?string $partnerType = null,
        ?string $reference = null,
        ?string $description = null
    ): JournalEntry {
        $tenantId = $this->getTenantId();

        return DB::transaction(function () use (
            $cashAccountId,
            $counterAccountId,
            $amountMinor,
            $date,
            $isCashIn,
            $partnerId,
            $partnerType,
            $reference,
            $description,
            $tenantId
        ) {
            // Get the appropriate journal (Cash or Bank)
            $cashAccount = ChartOfAccount::find($cashAccountId);
            $journal = $this->getJournalForAccount($cashAccount);

            if (!$journal) {
                throw new \Exception('No cash or bank journal found');
            }

            // Create journal entry
            $entry = JournalEntry::create([
                'tenant_id' => $tenantId,
                'journal_id' => $journal->id,
                'date' => $date,
                'reference' => $reference,
                'description' => $description ?? ($isCashIn ? 'Cash In' : 'Cash Out'),
                'status' => JournalEntry::STATUS_DRAFT,
                'created_by_user_id' => auth()->id(),
            ]);

            // Create journal entry lines
            if ($isCashIn) {
                // Cash In: Debit Cash, Credit Counter Account
                JournalEntryLine::create([
                    'tenant_id' => $tenantId,
                    'journal_entry_id' => $entry->id,
                    'account_id' => $cashAccountId,
                    'debit_minor' => $amountMinor,
                    'credit_minor' => 0,
                    'description' => $description,
                    'partner_type' => $partnerType,
                    'partner_id' => $partnerId,
                ]);

                JournalEntryLine::create([
                    'tenant_id' => $tenantId,
                    'journal_entry_id' => $entry->id,
                    'account_id' => $counterAccountId,
                    'debit_minor' => 0,
                    'credit_minor' => $amountMinor,
                    'description' => $description,
                    'partner_type' => $partnerType,
                    'partner_id' => $partnerId,
                ]);
            } else {
                // Cash Out: Debit Counter Account, Credit Cash
                JournalEntryLine::create([
                    'tenant_id' => $tenantId,
                    'journal_entry_id' => $entry->id,
                    'account_id' => $counterAccountId,
                    'debit_minor' => $amountMinor,
                    'credit_minor' => 0,
                    'description' => $description,
                    'partner_type' => $partnerType,
                    'partner_id' => $partnerId,
                ]);

                JournalEntryLine::create([
                    'tenant_id' => $tenantId,
                    'journal_entry_id' => $entry->id,
                    'account_id' => $cashAccountId,
                    'debit_minor' => 0,
                    'credit_minor' => $amountMinor,
                    'description' => $description,
                    'partner_type' => $partnerType,
                    'partner_id' => $partnerId,
                ]);
            }

            // Recalculate totals and post if balanced
            $entry->recalculateTotals();

            if ($entry->isBalanced()) {
                $entry->post();
            }

            Log::info('CashManagementService: Transaction recorded', [
                'entry_id' => $entry->id,
                'code' => $entry->code,
                'type' => $isCashIn ? 'cash_in' : 'cash_out',
                'amount' => $amountMinor,
                'status' => $entry->status,
            ]);

            return $entry;
        });
    }

    /**
     * Get the appropriate journal for a cash/bank account.
     */
    protected function getJournalForAccount(?ChartOfAccount $account): ?Journal
    {
        if (!$account) {
            return Journal::getCashJournal() ?? Journal::getBankJournal();
        }

        // Try to determine if it's a bank or cash account based on name/code
        $nameLower = strtolower($account->name ?? '');
        $codeLower = strtolower($account->code ?? '');

        if (str_contains($nameLower, 'bank') || str_contains($codeLower, 'bank')) {
            $journal = Journal::getBankJournal();
            if ($journal) {
                return $journal;
            }
        }

        // Default to cash journal, or bank if cash doesn't exist
        return Journal::getCashJournal() ?? Journal::getBankJournal() ?? Journal::getMiscJournal();
    }

    /**
     * Get partner options for dropdown (Patients, Suppliers, Staff).
     */
    public function getPartnerOptions(?string $partnerType = null): array
    {
        $options = [];

        // Patients
        if (!$partnerType || $partnerType === 'patient') {
            try {
                if (class_exists(\Modules\Patients\Models\Patient::class)) {
                    $patients = \Modules\Patients\Models\Patient::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->limit(100)
                        ->get();

                    foreach ($patients as $patient) {
                        $options["patient:{$patient->id}"] = "[Patient] {$patient->name}";
                    }
                }
            } catch (\Exception $e) {
                // Module not available
            }
        }

        // Suppliers
        if (!$partnerType || $partnerType === 'supplier') {
            try {
                if (class_exists(\Modules\Inventory\Models\Supplier::class)) {
                    $suppliers = \Modules\Inventory\Models\Supplier::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->limit(100)
                        ->get();

                    foreach ($suppliers as $supplier) {
                        $options["supplier:{$supplier->id}"] = "[Supplier] {$supplier->name}";
                    }
                }
            } catch (\Exception $e) {
                // Module not available
            }
        }

        // Staff (using StaffProfile which links to User)
        if (!$partnerType || $partnerType === 'staff') {
            try {
                if (class_exists(\Modules\Staff\Models\StaffProfile::class)) {
                    $staffProfiles = \Modules\Staff\Models\StaffProfile::query()
                        ->with('user')
                        ->whereHas('user', fn($q) => $q->where('is_active', true))
                        ->limit(100)
                        ->get();

                    foreach ($staffProfiles as $profile) {
                        if ($profile->user) {
                            $options["staff:{$profile->id}"] = "[Staff] {$profile->user->name}";
                        }
                    }
                }
            } catch (\Exception $e) {
                // Module not available
            }
        }

        return $options;
    }

    /**
     * Parse a partner key (e.g., "patient:uuid") into type and id.
     */
    public function parsePartnerKey(?string $key): array
    {
        if (!$key || !str_contains($key, ':')) {
            return ['type' => null, 'id' => null];
        }

        $parts = explode(':', $key, 2);
        return [
            'type' => $this->mapPartnerTypeToMorph($parts[0]),
            'id' => $parts[1] ?? null,
        ];
    }

    /**
     * Map partner type string to morph class.
     */
    protected function mapPartnerTypeToMorph(string $type): ?string
    {
        return match ($type) {
            'patient' => 'Modules\\Patients\\Models\\Patient',
            'supplier' => 'Modules\\Inventory\\Models\\Supplier',
            'staff' => 'Modules\\Staff\\Models\\StaffProfile',
            default => null,
        };
    }
}
