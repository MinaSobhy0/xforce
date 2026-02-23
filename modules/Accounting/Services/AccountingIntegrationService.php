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

        try {
            return DB::transaction(function () use ($date, $description, $lines, $referenceType, $referenceId, $autoPost) {
                // Get or create the general journal
                $journal = Journal::withoutGlobalScopes()
                    ->where('code', 'GJ')
                    ->orWhere('type', 'general')
                    ->first();

                if (!$journal) {
                    $journal = Journal::create([
                        'code' => 'GJ',
                        'name' => 'General Journal',
                        'type' => 'general',
                        'is_active' => true,
                    ]);
                }

                // Create the journal entry
                $entry = JournalEntry::create([
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
