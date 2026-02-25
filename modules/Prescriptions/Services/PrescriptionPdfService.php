<?php

namespace Modules\Prescriptions\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Prescriptions\Models\Prescription;
use Illuminate\Support\Facades\Storage;

class PrescriptionPdfService
{
    /**
     * Generate PDF for a prescription.
     */
    public function generate(Prescription $prescription): \Barryvdh\DomPDF\PDF
    {
        $prescription->loadMissing(['patient', 'prescriber', 'branch', 'items']);

        $data = $this->prepareData($prescription);

        $paperSize = config('prescriptions.pdf_paper_size', 'a5');
        $orientation = config('prescriptions.pdf_orientation', 'portrait');

        return Pdf::loadView('prescriptions::pdf.prescription', $data)
            ->setPaper($paperSize, $orientation);
    }

    /**
     * Download the prescription PDF.
     */
    public function download(Prescription $prescription)
    {
        $pdf = $this->generate($prescription);
        $filename = "prescription-{$prescription->prescription_number}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Stream the prescription PDF (for viewing in browser).
     */
    public function stream(Prescription $prescription)
    {
        $pdf = $this->generate($prescription);
        $filename = "prescription-{$prescription->prescription_number}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * Save the prescription PDF to storage.
     */
    public function save(Prescription $prescription, string $path = null): string
    {
        $pdf = $this->generate($prescription);
        $path = $path ?? "prescriptions/{$prescription->prescription_number}.pdf";

        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Prepare data for the prescription PDF view.
     */
    protected function prepareData(Prescription $prescription): array
    {
        $tenant = app('currentTenant');
        $locale = app()->getLocale();
        $isRtl = $locale === 'ar';

        return [
            'prescription' => $prescription,
            'clinic' => [
                'name' => $tenant?->name ?? config('app.name'),
                'address' => $tenant?->settings['address'] ?? '',
                'phone' => $tenant?->settings['phone'] ?? '',
                'email' => $tenant?->settings['email'] ?? '',
                'license' => $tenant?->settings['medical_license'] ?? '',
                'logo' => $tenant?->settings['logo'] ?? null,
            ],
            'patient' => [
                'name' => $prescription->patient?->full_name ?? 'N/A',
                'code' => $prescription->patient?->code ?? '',
                'phone' => $prescription->patient?->phone ?? '',
                'age' => $prescription->patient?->age ?? '',
                'gender' => $prescription->patient?->gender ?? '',
                'address' => $prescription->patient?->address ?? '',
            ],
            'prescriber' => [
                'name' => $prescription->prescriber?->name ?? 'N/A',
                'license' => $prescription->prescriber?->license_number ?? '',
                'specialty' => $prescription->prescriber?->specialty ?? '',
                'title' => $prescription->prescriber?->title ?? 'Dr.',
            ],
            'branch' => [
                'name' => $this->getTranslatedName($prescription->branch?->name),
                'address' => $prescription->branch?->address ?? '',
                'phone' => $prescription->branch?->phone ?? '',
            ],
            'medications' => $prescription->items->map(fn ($item) => [
                'number' => $item->sort_order + 1,
                'medication_name' => $item->medication_name,
                'generic_name' => $item->generic_name,
                'form' => $item->form_label,
                'dosage' => $item->full_dosage,
                'frequency' => $item->frequency_label,
                'duration' => $item->full_duration,
                'route' => $item->route_label,
                'quantity' => $item->quantity,
                'instructions' => $item->instructions_label,
                'special_instructions' => $item->special_instructions,
                'refills' => $item->refills_allowed,
            ]),
            'meta' => [
                'prescription_number' => $prescription->prescription_number,
                'diagnosis' => $prescription->diagnosis,
                'notes' => $prescription->notes,
                'issued_at' => $prescription->issued_at?->format('d/m/Y H:i'),
                'issued_date' => $prescription->issued_at?->format('d/m/Y'),
                'valid_until' => $prescription->valid_until?->format('d/m/Y'),
                'status' => $prescription->status,
                'is_expired' => $prescription->is_expired,
            ],
            'locale' => $locale,
            'isRtl' => $isRtl,
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
}
