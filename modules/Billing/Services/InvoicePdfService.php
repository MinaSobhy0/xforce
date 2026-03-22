<?php

namespace Modules\Billing\Services;

use App\Traits\SanitizesPdfData;
use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Billing\Models\Invoice;
use Illuminate\Support\Facades\View;

class InvoicePdfService
{
    use SanitizesPdfData;
    /**
     * Generate PDF for an invoice.
     */
    public function generate(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $invoice->loadMissing(['patient', 'lines.treatment', 'payments', 'branch']);

        $data = $this->prepareData($invoice);

        return Pdf::loadView('billing::pdf.invoice', $data)
            ->setPaper('a4', 'portrait');
    }

    /**
     * Download the invoice PDF.
     */
    public function download(Invoice $invoice)
    {
        $pdf = $this->generate($invoice);
        $filename = "invoice-{$invoice->code}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Stream the invoice PDF (for viewing in browser).
     */
    public function stream(Invoice $invoice)
    {
        $pdf = $this->generate($invoice);
        $filename = "invoice-{$invoice->code}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * Save the invoice PDF to storage.
     */
    public function save(Invoice $invoice, string $path = null): string
    {
        $pdf = $this->generate($invoice);
        $path = $path ?? "invoices/{$invoice->code}.pdf";

        \Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Prepare data for the invoice PDF view.
     * SECURITY: All user-controlled data is sanitized to prevent HTML injection.
     */
    protected function prepareData(Invoice $invoice): array
    {
        $tenant = app('currentTenant');

        // SECURITY: Sanitize all user-controlled string data
        $clinic = $this->sanitizeUserDataForPdf([
            'name' => $tenant?->name ?? config('app.name'),
            'address' => $tenant?->settings['address'] ?? '',
            'phone' => $tenant?->settings['phone'] ?? '',
            'email' => $tenant?->settings['email'] ?? '',
            'tax_number' => $tenant?->settings['tax_number'] ?? '',
            'logo' => $tenant?->settings['logo'] ?? null,
        ]);

        $patient = $this->sanitizeUserDataForPdf([
            'name' => $invoice->patient?->name ?? 'N/A',
            'phone' => $invoice->patient?->phone ?? '',
            'email' => $invoice->patient?->email ?? '',
            'address' => $invoice->patient?->address ?? '',
        ]);

        $branch = $this->sanitizeUserDataForPdf([
            'name' => $this->getTranslatedName($invoice->branch?->name),
            'address' => $invoice->branch?->address ?? '',
            'phone' => $invoice->branch?->phone ?? '',
        ]);

        $meta = $this->sanitizeUserDataForPdf([
            'code' => $invoice->code,
            'date' => $invoice->invoice_date?->format('Y-m-d'),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'status' => $invoice->status,
            'notes' => $invoice->notes,
            'footer' => $tenant?->settings['invoice_footer'] ?? '',
        ]);

        return [
            'invoice' => $invoice,
            'clinic' => $clinic,
            'patient' => $patient,
            'branch' => $branch,
            'lines' => $invoice->lines->map(fn ($line) => $this->sanitizeUserDataForPdf([
                'description' => $line->description ?? $this->getTranslatedName($line->treatment?->name),
                'quantity' => $line->quantity,
                'unit_price' => $this->formatCurrency($line->unit_price_minor),
                'discount' => $this->formatCurrency($line->discount_minor),
                'tax' => $this->formatCurrency($line->tax_minor),
                'total' => $this->formatCurrency($line->total_minor),
            ])),
            'totals' => [
                'subtotal' => $this->formatCurrency($invoice->subtotal_minor),
                'discount' => $this->formatCurrency($invoice->discount_minor),
                'tax' => $this->formatCurrency($invoice->tax_minor),
                'total' => $this->formatCurrency($invoice->total_minor),
                'paid' => $this->formatCurrency($invoice->paid_minor),
                'remaining' => $this->formatCurrency($invoice->remaining_minor),
            ],
            'payments' => $invoice->payments->map(fn ($payment) => $this->sanitizeUserDataForPdf([
                'date' => $payment->paid_at?->format('Y-m-d'),
                'method' => $payment->method,
                'amount' => $this->formatCurrency($payment->amount_minor),
                'reference' => $payment->reference,
            ])),
            'meta' => $meta,
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
    protected function formatCurrency(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2) . ' EGP';
    }
}
