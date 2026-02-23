<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;

class AccountingIntegrationService
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

        // Try to resolve from database search_path
        try {
            $result = DB::select('SHOW search_path');
            $searchPath = $result[0]->search_path ?? 'public';

            if (preg_match('/tenant[_-]([^,\s"]+)/', $searchPath, $matches)) {
                $slug = str_replace('_', '-', $matches[1]);
                $tenant = DB::connection('pgsql')
                    ->table('tenants')
                    ->where('slug', $slug)
                    ->orWhere('slug', $matches[1])
                    ->first();

                if ($tenant) {
                    return $tenant->id;
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return null;
    }

    /**
     * Create a journal entry with lines.
     *
     * @param \DateTimeInterface|string $date
     * @param string $description
     * @param array $lines Array of ['account_code' => string, 'debit' => int, 'credit' => int]
     * @param string|null $referenceType
     * @param string|null $referenceId
     * @param bool $autoPost
     * @return JournalEntry|null
     */
    public function createJournalEntry(
        $date,
        string $description,
        array $lines,
        ?string $referenceType = null,
        ?string $referenceId = null,
        bool $autoPost = true
    ): ?JournalEntry {
        if (empty($lines)) {
            Log::warning('AccountingIntegrationService: No lines provided for journal entry');
            return null;
        }

        $tenantId = $this->getTenantId();

        try {
            return DB::transaction(function () use ($date, $description, $lines, $referenceType, $referenceId, $autoPost, $tenantId) {
                // Get or create the general journal
                $journal = Journal::withoutGlobalScopes()
                    ->where('code', 'GJ')
                    ->orWhere('type', 'general')
                    ->first();

                if (!$journal) {
                    $journal = Journal::create([
                        'tenant_id' => $tenantId,
                        'code' => 'GJ',
                        'name' => 'General Journal',
                        'type' => 'general',
                        'is_active' => true,
                    ]);
                }

                // Create the journal entry
                $entry = JournalEntry::create([
                    'tenant_id' => $tenantId,
                    'journal_id' => $journal->id,
                    'date' => $date,
                    'description' => $description,
                    'reference' => $referenceType ? "{$referenceType}:{$referenceId}" : null,
                    'source_type' => $referenceType,
                    'source_id' => $referenceId,
                    'status' => JournalEntry::STATUS_DRAFT,
                    'created_by_user_id' => auth()->id(),
                ]);

                $totalDebit = 0;
                $totalCredit = 0;

                // Create journal entry lines
                foreach ($lines as $lineData) {
                    $accountCode = $lineData['account_code'];
                    $debitMinor = (int) ($lineData['debit'] ?? 0);
                    $creditMinor = (int) ($lineData['credit'] ?? 0);

                    if ($debitMinor == 0 && $creditMinor == 0) {
                        continue;
                    }

                    // Find the account by code
                    $account = ChartOfAccount::withoutGlobalScopes()
                        ->where('code', $accountCode)
                        ->first();

                    if (!$account) {
                        Log::warning("AccountingIntegrationService: Account not found", [
                            'code' => $accountCode,
                        ]);
                        continue;
                    }

                    JournalEntryLine::create([
                        'tenant_id' => $tenantId,
                        'journal_entry_id' => $entry->id,
                        'account_id' => $account->id,
                        'debit_minor' => $debitMinor,
                        'credit_minor' => $creditMinor,
                        'description' => $lineData['description'] ?? null,
                    ]);

                    $totalDebit += $debitMinor;
                    $totalCredit += $creditMinor;
                }

                // Update totals
                $entry->total_debit_minor = $totalDebit;
                $entry->total_credit_minor = $totalCredit;
                $entry->save();

                // Auto-post if balanced and requested
                if ($autoPost && $entry->isBalanced()) {
                    $entry->post();
                }

                Log::info('AccountingIntegrationService: Journal entry created', [
                    'entry_id' => $entry->id,
                    'code' => $entry->code,
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'is_balanced' => $entry->isBalanced(),
                    'status' => $entry->status,
                ]);

                return $entry;
            });

        } catch (\Exception $e) {
            Log::error('AccountingIntegrationService: Failed to create journal entry', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if accounting module is properly configured.
     */
    public function isConfigured(): bool
    {
        try {
            // Check if we have at least some chart of accounts
            return ChartOfAccount::withoutGlobalScopes()->exists();
        } catch (\Exception $e) {
            return false;
        }
    }
}
