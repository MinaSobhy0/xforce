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

        if (! $account) {
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
     * Optionally filter by a specific date.
     */
    public function getRecentTransactions(string $accountId, int $limit = 20, ?Carbon $filterDate = null): Collection
    {
        $lines = JournalEntryLine::with(['journalEntry', 'partner'])
            ->where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($filterDate) {
                $q->where('status', JournalEntry::STATUS_POSTED);
                if ($filterDate) {
                    $q->whereDate('date', $filterDate);
                }
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
     * Record a partner-based transaction with automatic account selection.
     * Uses configured default accounts from DefaultAccountsService.
     * - Suppliers: Uses Accounts Payable (configured per partner type)
     * - Patients/Staff: Uses Accounts Receivable (configured per partner type)
     */
    public function recordPartnerTransaction(
        string $cashAccountId,
        string $partnerKey,
        int $amountMinor,
        Carbon $date,
        bool $isCashIn,
        ?string $reference = null,
        ?string $description = null,
        ?string $journalId = null
    ): JournalEntry {
        // Parse partner key
        $partnerData = $this->parsePartnerKey($partnerKey);

        if (! $partnerData['type'] || ! $partnerData['id']) {
            throw new \Exception('Invalid partner selected');
        }

        // Get default accounts service
        $defaultAccounts = new DefaultAccountsService;

        // Determine partner type key for account lookup
        $partnerTypeKey = $this->getPartnerTypeKey($partnerData['type']);
        $isSupplier = $partnerTypeKey === 'supplier';

        // Get counter account from configured defaults
        // - For suppliers: use payable account
        // - For patients/staff: use receivable account
        $counterAccount = $isSupplier
            ? $defaultAccounts->getPayableAccountForPartner($partnerTypeKey)
            : $defaultAccounts->getReceivableAccountForPartner($partnerTypeKey);

        if (! $counterAccount) {
            throw new \Exception($isSupplier
                ? __('accounting::accounting.messages.payable_not_configured')
                : __('accounting::accounting.messages.receivable_not_configured'));
        }

        return $this->createCashTransaction(
            cashAccountId: $cashAccountId,
            counterAccountId: $counterAccount->id,
            amountMinor: $amountMinor,
            date: $date,
            isCashIn: $isCashIn,
            partnerId: $partnerData['id'],
            partnerType: $partnerData['type'],
            reference: $reference,
            description: $description,
            journalId: $journalId
        );
    }

    /**
     * Get partner type key from morph class.
     */
    protected function getPartnerTypeKey(string $morphClass): string
    {
        if (str_contains($morphClass, 'Supplier')) {
            return 'supplier';
        }
        if (str_contains($morphClass, 'Patient')) {
            return 'patient';
        }
        if (str_contains($morphClass, 'StaffProfile')) {
            return 'staff';
        }

        return 'patient'; // default
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
        ?string $description = null,
        ?string $journalId = null
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
            description: $description,
            journalId: $journalId
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
        ?string $description = null,
        ?string $journalId = null
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
            description: $description,
            journalId: $journalId
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
        ?string $description = null,
        ?string $journalId = null
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
            $tenantId,
            $journalId
        ) {
            // Use provided journal or determine from account
            $journal = null;
            if ($journalId) {
                $journal = Journal::find($journalId);
            }

            if (! $journal) {
                $cashAccount = ChartOfAccount::find($cashAccountId);
                $journal = $this->getJournalForAccount($cashAccount);
            }

            if (! $journal) {
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
        if (! $account) {
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
     * Record a transfer between two cash/bank accounts.
     * This creates a journal entry with:
     * - Debit: Destination Account
     * - Credit: Source Account
     */
    public function recordTransfer(
        string $sourceAccountId,
        string $destinationAccountId,
        int $amountMinor,
        \Carbon\Carbon $date,
        ?string $reference = null,
        ?string $description = null,
        ?string $sourceJournalId = null
    ): JournalEntry {
        $tenantId = $this->getTenantId();

        return DB::transaction(function () use (
            $sourceAccountId,
            $destinationAccountId,
            $amountMinor,
            $date,
            $reference,
            $description,
            $tenantId,
            $sourceJournalId
        ) {
            // Use source journal or determine from account
            $journal = null;
            if ($sourceJournalId) {
                $journal = Journal::find($sourceJournalId);
            }

            if (! $journal) {
                $sourceAccount = ChartOfAccount::find($sourceAccountId);
                $journal = $this->getJournalForAccount($sourceAccount);
            }

            if (! $journal) {
                throw new \Exception('No cash or bank journal found');
            }

            // Create journal entry
            $entry = JournalEntry::create([
                'tenant_id' => $tenantId,
                'journal_id' => $journal->id,
                'date' => $date,
                'reference' => $reference,
                'description' => $description ?? 'Transfer between accounts',
                'status' => JournalEntry::STATUS_DRAFT,
                'created_by_user_id' => auth()->id(),
            ]);

            // Create journal entry lines
            // Debit destination (money coming in)
            JournalEntryLine::create([
                'tenant_id' => $tenantId,
                'journal_entry_id' => $entry->id,
                'account_id' => $destinationAccountId,
                'debit_minor' => $amountMinor,
                'credit_minor' => 0,
                'description' => $description,
            ]);

            // Credit source (money going out)
            JournalEntryLine::create([
                'tenant_id' => $tenantId,
                'journal_entry_id' => $entry->id,
                'account_id' => $sourceAccountId,
                'debit_minor' => 0,
                'credit_minor' => $amountMinor,
                'description' => $description,
            ]);

            // Recalculate totals and post if balanced
            $entry->recalculateTotals();

            if ($entry->isBalanced()) {
                $entry->post();
            }

            Log::info('CashManagementService: Transfer recorded', [
                'entry_id' => $entry->id,
                'code' => $entry->code,
                'source_account' => $sourceAccountId,
                'destination_account' => $destinationAccountId,
                'amount' => $amountMinor,
                'status' => $entry->status,
            ]);

            return $entry;
        });
    }

    /**
     * Get partner options for dropdown (Patients, Suppliers, Staff).
     * Order depends on transaction type:
     * - Cash In: Patients first (receiving payments), then Suppliers (refunds)
     * - Cash Out: Suppliers first (making payments), then Patients (refunds)
     */
    public function getPartnerOptions(?string $transactionType = null): array
    {
        $patientOptions = [];
        $supplierOptions = [];
        $staffOptions = [];

        // Patients
        try {
            if (class_exists(\Modules\Patients\Models\Patient::class)) {
                $patients = \Modules\Patients\Models\Patient::query()
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->limit(100)
                    ->get();

                foreach ($patients as $patient) {
                    $patientOptions["patient:{$patient->id}"] = '['.__('accounting::accounting.patient')."] {$patient->full_name}";
                }
            }
        } catch (\Exception $e) {
            Log::debug('CashManagement: Patient module not available', ['error' => $e->getMessage()]);
        }

        // Suppliers
        try {
            if (class_exists(\Modules\Inventory\Models\Supplier::class)) {
                $suppliers = \Modules\Inventory\Models\Supplier::query()
                    ->where('is_active', true)
                    ->limit(100)
                    ->get();

                foreach ($suppliers as $supplier) {
                    $supplierName = $supplier->getTranslation('name', app()->getLocale())
                        ?? $supplier->getTranslation('name', 'en')
                        ?? $supplier->name ?? '';
                    if (is_array($supplierName)) {
                        $supplierName = $supplierName[app()->getLocale()] ?? $supplierName['en'] ?? '';
                    }
                    $supplierOptions["supplier:{$supplier->id}"] = '['.__('accounting::accounting.supplier')."] {$supplierName}";
                }
            }
        } catch (\Exception $e) {
            Log::debug('CashManagement: Supplier module not available', ['error' => $e->getMessage()]);
        }

        // Staff (using StaffProfile which links to User)
        try {
            if (class_exists(\Modules\Staff\Models\StaffProfile::class)) {
                $staffProfiles = \Modules\Staff\Models\StaffProfile::query()
                    ->with('user')
                    ->whereHas('user', fn ($q) => $q->where('is_active', true))
                    ->limit(100)
                    ->get();

                foreach ($staffProfiles as $profile) {
                    if ($profile->user) {
                        $staffOptions["staff:{$profile->id}"] = '['.__('accounting::accounting.staff')."] {$profile->user->name}";
                    }
                }
            }
        } catch (\Exception $e) {
            Log::debug('CashManagement: Staff module not available', ['error' => $e->getMessage()]);
        }

        // Order based on transaction type
        if ($transactionType === 'cash_out') {
            // Cash Out: Suppliers first (paying vendors), then others
            return array_merge($supplierOptions, $patientOptions, $staffOptions);
        }

        // Cash In: Patients first (receiving payments), then others
        return array_merge($patientOptions, $staffOptions, $supplierOptions);
    }

    /**
     * Parse a partner key (e.g., "patient:uuid") into type and id.
     */
    public function parsePartnerKey(?string $key): array
    {
        if (! $key || ! str_contains($key, ':')) {
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
