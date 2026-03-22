<?php

namespace Modules\GiftCards\Services;

use App\Traits\SanitizesExportData;
use Modules\GiftCards\Models\GiftCard;
use Modules\GiftCards\Models\GiftCardPrintHistory;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Barryvdh\DomPDF\Facade\Pdf;

class GiftCardExportService
{
    use SanitizesExportData;

    protected GiftCardGeneratorService $generator;

    public function __construct(GiftCardGeneratorService $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Export cards to CSV (streaming)
     */
    public function exportToCsv(Collection $cards): StreamedResponse
    {
        return response()->streamDownload(function () use ($cards) {
            $handle = fopen('php://output', 'w');

            // Header row
            fputcsv($handle, [
                'Code',
                'Value',
                'Status',
                'Template',
                'Expires At',
                'PIN',
                'Purchaser',
                'Recipient',
            ]);

            foreach ($cards as $card) {
                // SECURITY: Sanitize user-provided data to prevent formula injection
                fputcsv($handle, $this->sanitizeExportRow([
                    $card->code,
                    $card->initial_value_minor / 100,
                    $card->status_label,
                    $card->template?->name ?? '-',
                    $card->expires_at?->format('Y-m-d'),
                    $card->pin_code ?? '-',
                    $card->purchaser?->full_name ?? '-',
                    $card->recipient?->full_name ?? '-',
                ]));
            }

            fclose($handle);
        }, 'gift-cards-' . now()->format('Y-m-d-His') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Export cards to Excel format (CSV with semicolon for Excel compatibility)
     */
    public function exportToExcel(Collection $cards): StreamedResponse
    {
        return response()->streamDownload(function () use ($cards) {
            $handle = fopen('php://output', 'w');

            // BOM for Excel UTF-8 support
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header row
            fputcsv($handle, [
                'Code',
                'Value',
                'Remaining',
                'Status',
                'Template',
                'Expires At',
                'Activated At',
                'PIN',
                'Purchaser',
                'Recipient',
            ], ';');

            foreach ($cards as $card) {
                // SECURITY: Sanitize user-provided data to prevent formula injection
                fputcsv($handle, $this->sanitizeExportRow([
                    $card->code,
                    number_format($card->initial_value_minor / 100, 2),
                    number_format($card->remaining_value_minor / 100, 2),
                    $card->status_label,
                    $card->template?->name ?? '-',
                    $card->expires_at?->format('Y-m-d'),
                    $card->activated_at?->format('Y-m-d H:i'),
                    $card->pin_code ?? '-',
                    $card->purchaser?->full_name ?? '-',
                    $card->recipient?->full_name ?? '-',
                ]), ';');
            }

            fclose($handle);
        }, 'gift-cards-' . now()->format('Y-m-d-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Generate single card PDF
     */
    public function generateCardPdf(GiftCard $card): string
    {
        $pdf = Pdf::loadView('giftcards::pdf.gift-card', [
            'card' => $card,
            'template' => $card->template,
            'clinic' => $this->getClinicInfo(),
        ]);

        $pdf->setPaper('a6', 'landscape');

        return $pdf->output();
    }

    /**
     * Generate batch PDF with multiple cards
     */
    public function generateBatchPdf(Collection $cards): string
    {
        $pdf = Pdf::loadView('giftcards::pdf.gift-cards-batch', [
            'cards' => $cards,
            'clinic' => $this->getClinicInfo(),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    /**
     * Stream single card PDF
     */
    public function streamCardPdf(GiftCard $card): StreamedResponse
    {
        return response()->streamDownload(function () use ($card) {
            echo $this->generateCardPdf($card);
        }, "gift-card-{$card->code}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Stream batch PDF
     */
    public function streamBatchPdf(Collection $cards): StreamedResponse
    {
        return response()->streamDownload(function () use ($cards) {
            echo $this->generateBatchPdf($cards);
        }, 'gift-cards-batch-' . now()->format('Y-m-d-His') . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Record print history
     */
    public function recordPrint(
        GiftCard $card,
        string $format,
        ?string $printerName = null
    ): GiftCardPrintHistory {
        return GiftCardPrintHistory::create([
            'tenant_id' => $card->tenant_id,
            'gift_card_id' => $card->id,
            'print_format' => $format,
            'printer_name' => $printerName,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'printed_by' => auth()->id(),
        ]);
    }

    /**
     * Get clinic info for PDF
     */
    protected function getClinicInfo(): array
    {
        $tenant = app(\XLinic\Framework\Core\Tenancy\TenantManager::class)->current();

        return [
            'name' => $tenant?->name ?? config('app.name'),
            'address' => $tenant?->address ?? '',
            'phone' => $tenant?->phone ?? '',
            'logo' => $tenant?->logo_url ?? null,
        ];
    }
}
