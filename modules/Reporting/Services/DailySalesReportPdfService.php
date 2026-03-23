<?php

namespace Modules\Reporting\Services;

use App\Traits\SanitizesPdfData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

class DailySalesReportPdfService
{
    use SanitizesPdfData;

    /**
     * Generate Daily Sales Report PDF.
     */
    public function generate(array $reportData): \Barryvdh\DomPDF\PDF
    {
        $data = $this->prepareData($reportData);

        return Pdf::loadView('reporting::pdf.daily-sales-report', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);
    }

    /**
     * Download the Daily Sales Report PDF.
     */
    public function download(array $reportData)
    {
        $pdf = $this->generate($reportData);
        $date = $reportData['report_date'] ?? now()->format('Y-m-d');
        $filename = "daily-sales-report-{$date}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Stream the Daily Sales Report PDF.
     */
    public function stream(array $reportData)
    {
        $pdf = $this->generate($reportData);
        $date = $reportData['report_date'] ?? now()->format('Y-m-d');
        $filename = "daily-sales-report-{$date}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * Prepare data for the PDF view.
     */
    protected function prepareData(array $reportData): array
    {
        $tenant = app('currentTenant');

        $clinic = $this->sanitizeUserDataForPdf([
            'name' => $tenant?->name ?? config('app.name'),
            'address' => $tenant?->settings['address'] ?? '',
            'phone' => $tenant?->settings['phone'] ?? '',
            'email' => $tenant?->settings['email'] ?? '',
            'logo' => $tenant?->getLogoUrl(),
        ]);

        // Sanitize patient names and service names
        $newPatients = $this->sanitizePatientData($reportData['newPatients'] ?? []);
        $returningPatients = $this->sanitizePatientData($reportData['returningPatients'] ?? []);
        $followUpPatients = $this->sanitizePatientData($reportData['followUpPatients'] ?? []);

        return [
            'clinic' => $clinic,
            'reportDate' => $reportData['report_date'] ?? now()->format('Y-m-d'),
            'branchName' => $reportData['branch_name'] ?? null,
            'stats' => $reportData['stats'] ?? [],
            'newPatients' => $newPatients,
            'returningPatients' => $returningPatients,
            'followUpPatients' => $followUpPatients,
            'totals' => [
                'new_patients_count' => $reportData['stats']['new_patients_count']['value'] ?? 0,
                'new_patients_total' => collect($newPatients)->sum('total'),
                'returning_patients_count' => $reportData['stats']['returning_patients_count']['value'] ?? 0,
                'returning_patients_total' => collect($returningPatients)->sum('total'),
                'followup_patients_count' => $reportData['stats']['followup_patients_count']['value'] ?? 0,
                'followup_patients_total' => collect($followUpPatients)->sum('total'),
                'total_revenue' => $reportData['stats']['total_revenue']['value'] ?? '0',
                'total_products' => $reportData['stats']['total_products']['value'] ?? '0',
                'total_products_count' => $reportData['stats']['total_products']['description'] ?? '',
                'highest_sales' => $reportData['stats']['highest_sales']['value'] ?? '-',
                'highest_sales_amount' => $reportData['stats']['highest_sales']['description'] ?? '',
                'most_booked' => $reportData['stats']['most_booked']['value'] ?? '-',
                'most_booked_count' => $reportData['stats']['most_booked']['description'] ?? '',
            ],
            'generatedAt' => now(),
            'locale' => app()->getLocale(),
            'isRtl' => app()->getLocale() === 'ar',
        ];
    }

    /**
     * Sanitize patient data for each category.
     */
    protected function sanitizePatientData(array $categories): array
    {
        $sanitized = [];

        foreach ($categories as $categoryName => $category) {
            $sanitizedPatients = [];

            foreach ($category['patients'] ?? [] as $patient) {
                $sanitizedPatients[] = $this->sanitizeUserDataForPdf([
                    'patient_id' => $patient['patient_id'] ?? null,
                    'patient_name' => $patient['patient_name'] ?? '-',
                    'service_name' => $patient['service_name'] ?? '-',
                    'practitioner_name' => $patient['practitioner_name'] ?? '-',
                    'price' => $patient['price'] ?? 0,
                    'price_formatted' => $patient['price_formatted'] ?? '0',
                ]);
            }

            $sanitized[$this->sanitizeForPdf($categoryName)] = [
                'patients' => $sanitizedPatients,
                'total' => $category['total'] ?? 0,
                'total_formatted' => $category['total_formatted'] ?? '0',
                'count' => $category['count'] ?? 0,
            ];
        }

        return $sanitized;
    }
}
