<?php

namespace Modules\Accounting\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\JournalEntryLine;

class BalanceSheetPdfService
{
    /**
     * Generate Balance Sheet PDF.
     */
    public function generate(string $asOfDate): \Barryvdh\DomPDF\PDF
    {
        $data = $this->prepareData(Carbon::parse($asOfDate));

        return Pdf::loadView('accounting::pdf.balance-sheet', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true);
    }

    /**
     * Download the Balance Sheet PDF.
     */
    public function download(string $asOfDate)
    {
        $pdf = $this->generate($asOfDate);
        $filename = "balance-sheet-{$asOfDate}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Stream the Balance Sheet PDF.
     */
    public function stream(string $asOfDate)
    {
        $pdf = $this->generate($asOfDate);
        $filename = "balance-sheet-{$asOfDate}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * Prepare data for the PDF view.
     */
    protected function prepareData(Carbon $asOfDate): array
    {
        $tenant = app('currentTenant');

        // Assets
        $assets = $this->getAccountBalances('asset', $asOfDate);
        $totalAssets = array_sum(array_column($assets, 'amount'));

        // Liabilities
        $liabilities = $this->getAccountBalances('liability', $asOfDate);
        $totalLiabilities = array_sum(array_column($liabilities, 'amount'));

        // Equity
        $equity = $this->getAccountBalances('equity', $asOfDate);
        $totalEquity = array_sum(array_column($equity, 'amount'));

        // Add retained earnings
        $retainedEarnings = $this->calculateRetainedEarnings($asOfDate);
        if ($retainedEarnings != 0) {
            $equity[] = [
                'code' => 'RE',
                'name' => __('accounting::accounting.retained_earnings'),
                'amount' => $retainedEarnings,
            ];
            $totalEquity += $retainedEarnings;
        }

        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity;
        $isBalanced = $totalAssets === $totalLiabilitiesAndEquity;

        return [
            'clinic' => [
                'name' => $tenant?->name ?? config('app.name'),
                'address' => $tenant?->settings['address'] ?? '',
                'phone' => $tenant?->settings['phone'] ?? '',
            ],
            'asOfDate' => $asOfDate->format('Y-m-d'),
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'totalAssets' => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'totalEquity' => $totalEquity,
            'totalLiabilitiesAndEquity' => $totalLiabilitiesAndEquity,
            'isBalanced' => $isBalanced,
            'generatedAt' => now(),
            'locale' => app()->getLocale(),
            'isRtl' => app()->getLocale() === 'ar',
        ];
    }

    /**
     * Get account balances for a specific type.
     */
    protected function getAccountBalances(string $type, Carbon $asOfDate): array
    {
        $accounts = ChartOfAccount::where('type', $type)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $balances = [];

        foreach ($accounts as $account) {
            $balance = JournalEntryLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                    $q->where('date', '<=', $asOfDate)
                        ->where('status', 'posted');
                })
                ->sum(DB::raw('debit_minor - credit_minor'));

            // Liabilities and equity have credit balances
            if (in_array($type, ['liability', 'equity'])) {
                $balance = -$balance;
            }

            if ($balance != 0) {
                $balances[] = [
                    'code' => $account->code,
                    'name' => $this->getTranslatedName($account->name),
                    'amount' => abs($balance),
                ];
            }
        }

        return $balances;
    }

    /**
     * Calculate retained earnings (revenues - expenses).
     */
    protected function calculateRetainedEarnings(Carbon $asOfDate): int
    {
        $revenues = JournalEntryLine::whereHas('account', fn ($q) => $q->where('type', 'revenue'))
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('date', '<=', $asOfDate)
                    ->where('status', 'posted');
            })
            ->sum(DB::raw('credit_minor - debit_minor'));

        $expenses = JournalEntryLine::whereHas('account', fn ($q) => $q->where('type', 'expense'))
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('date', '<=', $asOfDate)
                    ->where('status', 'posted');
            })
            ->sum(DB::raw('debit_minor - credit_minor'));

        return $revenues - $expenses;
    }

    /**
     * Get translated name from JSONB field.
     */
    protected function getTranslatedName($name): string
    {
        if (is_array($name)) {
            return $name[app()->getLocale()] ?? $name['en'] ?? '';
        }

        return $name ?? '';
    }

    /**
     * Format currency amount.
     */
    public function formatCurrency(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2) . ' EGP';
    }
}
