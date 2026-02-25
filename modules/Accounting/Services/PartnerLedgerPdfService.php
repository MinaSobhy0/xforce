<?php

namespace Modules\Accounting\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PartnerLedgerPdfService
{
    protected PartnerLedgerService $service;

    public function __construct(PartnerLedgerService $service)
    {
        $this->service = $service;
    }

    /**
     * Generate Partner Ledger PDF.
     */
    public function generate(
        string $startDate,
        string $endDate,
        ?string $partnerType = null,
        ?string $partnerId = null,
        ?string $accountId = null,
        bool $showZeroBalances = false
    ): \Barryvdh\DomPDF\PDF {
        $data = $this->prepareData(
            Carbon::parse($startDate),
            Carbon::parse($endDate),
            $partnerType,
            $partnerId,
            $accountId,
            $showZeroBalances
        );

        return Pdf::loadView('accounting::pdf.partner-ledger', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true);
    }

    /**
     * Download the Partner Ledger PDF.
     */
    public function download(
        string $startDate,
        string $endDate,
        ?string $partnerType = null,
        ?string $partnerId = null,
        ?string $accountId = null,
        bool $showZeroBalances = false
    ) {
        $pdf = $this->generate($startDate, $endDate, $partnerType, $partnerId, $accountId, $showZeroBalances);
        $filename = "partner-ledger-{$startDate}-to-{$endDate}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Stream the Partner Ledger PDF.
     */
    public function stream(
        string $startDate,
        string $endDate,
        ?string $partnerType = null,
        ?string $partnerId = null,
        ?string $accountId = null,
        bool $showZeroBalances = false
    ) {
        $pdf = $this->generate($startDate, $endDate, $partnerType, $partnerId, $accountId, $showZeroBalances);
        $filename = "partner-ledger-{$startDate}-to-{$endDate}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * Prepare data for the PDF view.
     */
    protected function prepareData(
        Carbon $startDate,
        Carbon $endDate,
        ?string $partnerType,
        ?string $partnerId,
        ?string $accountId,
        bool $showZeroBalances
    ): array {
        $tenant = app('currentTenant');

        $partnerData = $this->service->getPartnerLedger(
            $startDate,
            $endDate,
            $partnerType,
            $partnerId,
            $accountId,
            $showZeroBalances
        );

        $stats = $this->service->getStats($partnerData);

        return [
            'clinic' => [
                'name' => $tenant?->name ?? config('app.name'),
                'address' => $tenant?->settings['address'] ?? '',
                'phone' => $tenant?->settings['phone'] ?? '',
            ],
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'partnerType' => $partnerType,
            'partnerData' => $partnerData,
            'stats' => $stats,
            'generatedAt' => now(),
            'locale' => app()->getLocale(),
            'isRtl' => app()->getLocale() === 'ar',
        ];
    }

    /**
     * Format currency amount.
     */
    public function formatCurrency(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2) . ' EGP';
    }
}
