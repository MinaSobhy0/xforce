<?php

namespace Modules\GiftCards\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Modules\GiftCards\Models\GiftCard;
use Modules\GiftCards\Models\GiftCardPrintHistory;

class GiftCardPdfService
{
    /**
     * Generate and stream the gift card PDF.
     */
    public function stream(GiftCard $card): Response
    {
        $this->logPrint($card);

        $pdf = $this->generatePdf($card);

        return $pdf->stream("gift-card-{$card->code}.pdf");
    }

    /**
     * Generate and download the gift card PDF.
     */
    public function download(GiftCard $card): Response
    {
        $this->logPrint($card);

        $pdf = $this->generatePdf($card);

        return $pdf->download("gift-card-{$card->code}.pdf");
    }

    /**
     * Generate the PDF.
     */
    protected function generatePdf(GiftCard $card): \Barryvdh\DomPDF\PDF
    {
        $clinic = $this->getClinicData();

        return Pdf::loadView('giftcards::pdf.gift-card', [
            'card' => $card,
            'clinic' => $clinic,
        ])
            ->setPaper([0, 0, 450, 280], 'landscape') // Credit card size approximately
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);
    }

    /**
     * Log the print action.
     */
    protected function logPrint(GiftCard $card): void
    {
        GiftCardPrintHistory::create([
            'tenant_id' => tenant_id(),
            'gift_card_id' => $card->id,
            'printed_by' => auth()->id(),
            'format' => 'pdf',
            'metadata' => [
                'user_agent' => request()->userAgent(),
                'ip' => request()->ip(),
            ],
        ]);
    }

    /**
     * Get clinic data for the PDF header.
     */
    protected function getClinicData(): array
    {
        $tenant = tenant();

        return [
            'name' => $tenant?->name ?? config('app.name'),
            'address' => $tenant?->address ?? '',
            'phone' => $tenant?->contact_phone ?? '',
            'email' => $tenant?->contact_email ?? '',
            'logo' => $tenant?->getLogoUrl() ?? null,
        ];
    }
}
