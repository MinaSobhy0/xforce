<?php

namespace Modules\Accounting\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\JournalEntryLine;

class ProfitLossPdfService
{
    /**
     * Generate Profit & Loss PDF.
     */
    public function generate(string $startDate, string $endDate): \Barryvdh\DomPDF\PDF
    {
        $data = $this->prepareData(Carbon::parse($startDate), Carbon::parse($endDate));

        return Pdf::loadView('accounting::pdf.profit-loss', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true);
    }

    /**
     * Download the Profit & Loss PDF.
     */
    public function download(string $startDate, string $endDate)
    {
        $pdf = $this->generate($startDate, $endDate);
        $filename = "profit-loss-{$startDate}-to-{$endDate}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Stream the Profit & Loss PDF.
     */
    public function stream(string $startDate, string $endDate)
    {
        $pdf = $this->generate($startDate, $endDate);
        $filename = "profit-loss-{$startDate}-to-{$endDate}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * Prepare data for the PDF view.
     */
    protected function prepareData(Carbon $startDate, Carbon $endDate): array
    {
        $tenant = app('currentTenant');

        // Revenue accounts
        $revenueAccounts = ChartOfAccount::where('type', 'revenue')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $revenues = [];
        $totalRevenue = 0;

        foreach ($revenueAccounts as $account) {
            $balance = JournalEntryLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('date', [$startDate, $endDate])
                        ->where('status', 'posted');
                })
                ->sum(DB::raw('credit_minor - debit_minor'));

            if ($balance != 0) {
                $revenues[] = [
                    'code' => $account->code,
                    'name' => $this->getTranslatedName($account->name),
                    'amount' => $balance,
                ];
                $totalRevenue += $balance;
            }
        }

        // Expense accounts
        $expenseAccounts = ChartOfAccount::where('type', 'expense')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $expenses = [];
        $totalExpenses = 0;

        foreach ($expenseAccounts as $account) {
            $balance = JournalEntryLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('date', [$startDate, $endDate])
                        ->where('status', 'posted');
                })
                ->sum(DB::raw('debit_minor - credit_minor'));

            if ($balance != 0) {
                $expenses[] = [
                    'code' => $account->code,
                    'name' => $this->getTranslatedName($account->name),
                    'amount' => $balance,
                ];
                $totalExpenses += $balance;
            }
        }

        $netIncome = $totalRevenue - $totalExpenses;

        return [
            'clinic' => [
                'name' => $tenant?->name ?? config('app.name'),
                'address' => $tenant?->settings['address'] ?? '',
                'phone' => $tenant?->settings['phone'] ?? '',
            ],
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'revenues' => $revenues,
            'expenses' => $expenses,
            'totalRevenue' => $totalRevenue,
            'totalExpenses' => $totalExpenses,
            'netIncome' => $netIncome,
            'isProfit' => $netIncome >= 0,
            'generatedAt' => now(),
            'locale' => app()->getLocale(),
            'isRtl' => app()->getLocale() === 'ar',
        ];
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
