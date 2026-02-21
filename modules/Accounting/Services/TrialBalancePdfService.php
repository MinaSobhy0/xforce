<?php

namespace Modules\Accounting\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\JournalEntryLine;

class TrialBalancePdfService
{
    /**
     * Generate Trial Balance PDF.
     */
    public function generate(string $asOfDate): \Barryvdh\DomPDF\PDF
    {
        $data = $this->prepareData(Carbon::parse($asOfDate));

        return Pdf::loadView('accounting::pdf.trial-balance', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true);
    }

    /**
     * Download the Trial Balance PDF.
     */
    public function download(string $asOfDate)
    {
        $pdf = $this->generate($asOfDate);
        $filename = "trial-balance-{$asOfDate}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Stream the Trial Balance PDF.
     */
    public function stream(string $asOfDate)
    {
        $pdf = $this->generate($asOfDate);
        $filename = "trial-balance-{$asOfDate}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * Prepare data for the PDF view.
     */
    protected function prepareData(Carbon $asOfDate): array
    {
        $tenant = app('currentTenant');

        $accounts = ChartOfAccount::where('is_active', true)
            ->orderBy('code')
            ->get();

        $trialBalance = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($accounts as $account) {
            $balance = JournalEntryLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                    $q->where('date', '<=', $asOfDate)
                        ->where('status', 'posted');
                })
                ->sum(DB::raw('debit_minor - credit_minor'));

            if ($balance != 0) {
                $debit = $balance > 0 ? $balance : 0;
                $credit = $balance < 0 ? abs($balance) : 0;

                $trialBalance[] = [
                    'code' => $account->code,
                    'name' => $this->getTranslatedName($account->name),
                    'type' => $account->type,
                    'debit' => $debit,
                    'credit' => $credit,
                ];

                $totalDebit += $debit;
                $totalCredit += $credit;
            }
        }

        return [
            'clinic' => [
                'name' => $tenant?->name ?? config('app.name'),
                'address' => $tenant?->settings['address'] ?? '',
                'phone' => $tenant?->settings['phone'] ?? '',
            ],
            'asOfDate' => $asOfDate->format('Y-m-d'),
            'trialBalance' => $trialBalance,
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
            'isBalanced' => $totalDebit === $totalCredit,
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
