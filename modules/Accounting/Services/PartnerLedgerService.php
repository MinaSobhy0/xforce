<?php

namespace Modules\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;
use Modules\Inventory\Models\Supplier;
use Modules\Patients\Models\Patient;

class PartnerLedgerService
{
    /**
     * Get partner ledger data grouped by partner.
     */
    public function getPartnerLedger(
        Carbon $startDate,
        Carbon $endDate,
        ?string $partnerType = null,
        ?string $partnerId = null,
        ?string $accountId = null,
        bool $showZeroBalances = false
    ): Collection {
        // Get all unique partners with transactions
        $partners = $this->getPartnersWithTransactions($startDate, $endDate, $partnerType, $partnerId, $accountId);

        $result = collect();

        foreach ($partners as $partner) {
            $openingBalance = $this->getOpeningBalance(
                $partner['partner_type'],
                $partner['partner_id'],
                $startDate,
                $accountId
            );

            $transactions = $this->getTransactions(
                $partner['partner_type'],
                $partner['partner_id'],
                $startDate,
                $endDate,
                $accountId
            );

            $closingBalance = $openingBalance;
            $totalDebit = 0;
            $totalCredit = 0;
            $transactionData = [];

            foreach ($transactions as $line) {
                $closingBalance += ($line->debit_minor - $line->credit_minor);
                $totalDebit += $line->debit_minor;
                $totalCredit += $line->credit_minor;

                $transactionData[] = [
                    'date' => $line->journalEntry->date->format('Y-m-d'),
                    'reference' => $line->journalEntry->code,
                    'description' => $line->description ?? $line->journalEntry->description,
                    'debit' => $line->debit_minor,
                    'credit' => $line->credit_minor,
                    'balance' => $closingBalance,
                ];
            }

            // Skip if no activity and not showing zero balances
            if (!$showZeroBalances && $openingBalance === 0 && count($transactionData) === 0) {
                continue;
            }

            $result->push([
                'partner_id' => $partner['partner_id'],
                'partner_type' => $partner['partner_type'],
                'partner_name' => $partner['partner_name'],
                'partner_type_label' => $partner['partner_type_label'],
                'opening_balance' => $openingBalance,
                'transactions' => $transactionData,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'closing_balance' => $closingBalance,
            ]);
        }

        return $result;
    }

    /**
     * Get all partners with transactions in the date range or with opening balance.
     */
    protected function getPartnersWithTransactions(
        Carbon $startDate,
        Carbon $endDate,
        ?string $partnerType = null,
        ?string $partnerId = null,
        ?string $accountId = null
    ): Collection {
        // Build query for partners with transactions in period
        $periodQuery = JournalEntryLine::query()
            ->select('partner_type', 'partner_id')
            ->distinct()
            ->whereNotNull('partner_type')
            ->whereNotNull('partner_id')
            ->when($accountId, fn($q) => $q->where('account_id', $accountId))
            ->when($partnerId, fn($q) => $q->where('partner_id', $partnerId))
            ->when($partnerType, function ($q) use ($partnerType) {
                $modelClass = $this->getPartnerModelClass($partnerType);
                return $q->where('partner_type', $modelClass);
            })
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate, $endDate])
                    ->where('status', 'posted');
            });

        // Build query for partners with opening balance (transactions before start date)
        $openingQuery = JournalEntryLine::query()
            ->select('partner_type', 'partner_id')
            ->distinct()
            ->whereNotNull('partner_type')
            ->whereNotNull('partner_id')
            ->when($accountId, fn($q) => $q->where('account_id', $accountId))
            ->when($partnerId, fn($q) => $q->where('partner_id', $partnerId))
            ->when($partnerType, function ($q) use ($partnerType) {
                $modelClass = $this->getPartnerModelClass($partnerType);
                return $q->where('partner_type', $modelClass);
            })
            ->whereHas('journalEntry', function ($q) use ($startDate) {
                $q->where('date', '<', $startDate)
                    ->where('status', 'posted');
            });

        // Combine both queries
        $partnerRecords = $periodQuery->union($openingQuery)->get();

        // Get unique partner combinations
        $uniquePartners = $partnerRecords->unique(function ($item) {
            return $item->partner_type . '_' . $item->partner_id;
        });

        // Resolve partner names
        return $uniquePartners->map(function ($record) {
            $partner = $this->resolvePartner($record->partner_type, $record->partner_id);

            return [
                'partner_type' => $record->partner_type,
                'partner_id' => $record->partner_id,
                'partner_name' => $partner['name'],
                'partner_type_label' => $partner['type_label'],
            ];
        })->sortBy('partner_name')->values();
    }

    /**
     * Get opening balance for a partner before a date.
     */
    public function getOpeningBalance(
        string $partnerType,
        string $partnerId,
        Carbon $asOfDate,
        ?string $accountId = null
    ): int {
        return JournalEntryLine::where('partner_type', $partnerType)
            ->where('partner_id', $partnerId)
            ->when($accountId, fn($q) => $q->where('account_id', $accountId))
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('date', '<', $asOfDate)
                    ->where('status', 'posted');
            })
            ->sum(DB::raw('debit_minor - credit_minor'));
    }

    /**
     * Get transactions for a partner within a date range.
     */
    public function getTransactions(
        string $partnerType,
        string $partnerId,
        Carbon $startDate,
        Carbon $endDate,
        ?string $accountId = null
    ): Collection {
        return JournalEntryLine::where('partner_type', $partnerType)
            ->where('partner_id', $partnerId)
            ->when($accountId, fn($q) => $q->where('account_id', $accountId))
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate, $endDate])
                    ->where('status', 'posted');
            })
            ->with(['journalEntry', 'account'])
            ->orderBy(
                JournalEntry::select('date')
                    ->whereColumn('journal_entries.id', 'journal_entry_lines.journal_entry_id')
            )
            ->orderBy(
                JournalEntry::select('code')
                    ->whereColumn('journal_entries.id', 'journal_entry_lines.journal_entry_id')
            )
            ->get();
    }

    /**
     * Get statistics for the report.
     */
    public function getStats(Collection $partnerData): array
    {
        return [
            'total_partners' => $partnerData->count(),
            'total_debit' => $partnerData->sum('total_debit'),
            'total_credit' => $partnerData->sum('total_credit'),
            'net_balance' => $partnerData->sum('closing_balance'),
        ];
    }

    /**
     * Resolve partner name and type label from polymorphic data.
     */
    protected function resolvePartner(string $partnerType, string $partnerId): array
    {
        $name = __('accounting::accounting.unknown_partner');
        $typeLabel = __('accounting::accounting.partner');

        if ($partnerType === Patient::class || str_contains($partnerType, 'Patient')) {
            $patient = Patient::find($partnerId);
            if ($patient) {
                $name = $patient->full_name;
            }
            $typeLabel = __('accounting::accounting.customer');
        } elseif ($partnerType === Supplier::class || str_contains($partnerType, 'Supplier')) {
            $supplier = Supplier::find($partnerId);
            if ($supplier) {
                $name = $supplier->getTranslation('name', app()->getLocale())
                    ?? $supplier->getTranslation('name', 'en')
                    ?? $supplier->name;
                if (is_array($name)) {
                    $name = $name[app()->getLocale()] ?? $name['en'] ?? '';
                }
            }
            $typeLabel = __('accounting::accounting.supplier');
        }

        return [
            'name' => $name,
            'type_label' => $typeLabel,
        ];
    }

    /**
     * Get the model class for a partner type string.
     */
    protected function getPartnerModelClass(string $partnerType): string
    {
        return match ($partnerType) {
            'customer' => Patient::class,
            'supplier' => Supplier::class,
            default => $partnerType,
        };
    }

    /**
     * Get options for partner dropdown based on type.
     */
    public function getPartnerOptions(?string $partnerType): array
    {
        if ($partnerType === 'customer') {
            return Patient::orderBy('first_name')
                ->orderBy('last_name')
                ->get()
                ->mapWithKeys(fn($p) => [$p->id => $p->full_name])
                ->toArray();
        }

        if ($partnerType === 'supplier') {
            return Supplier::orderBy('name')
                ->get()
                ->mapWithKeys(function ($s) {
                    $name = $s->getTranslation('name', app()->getLocale())
                        ?? $s->getTranslation('name', 'en')
                        ?? $s->name;
                    if (is_array($name)) {
                        $name = $name[app()->getLocale()] ?? $name['en'] ?? '';
                    }
                    return [$s->id => $name];
                })
                ->toArray();
        }

        return [];
    }
}
