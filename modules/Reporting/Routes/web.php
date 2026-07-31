<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Core\Models\Branch;
use Modules\Reporting\Services\DailySalesReportPdfService;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Reporting module web routes.
| Report pages are registered through Filament Panel provider.
|
*/

Route::prefix('reports')->middleware(['web', 'auth'])->group(function () {

    // Daily Sales Report PDF download
    Route::get('/daily-sales/pdf', function () {
        if (! auth()->check()) {
            abort(403, 'Unauthorized');
        }

        $date = request('date', now()->format('Y-m-d'));
        $branchId = request('branch_id');

        // Get report data
        $reportData = generateDailySalesReportData($date, $branchId);

        $service = app(DailySalesReportPdfService::class);

        return $service->download($reportData);
    })->name('daily-sales.pdf');
});

/**
 * Generate daily sales report data
 */
if (! function_exists('generateDailySalesReportData')) {
    function generateDailySalesReportData(string $date, ?string $branchId): array
    {
        $dateCarbon = Carbon::parse($date);

        // Get confirmed invoices
        $invoices = Invoice::with([
            'patient',
            'lines.service.category',
            'lines.product',
            'lines.appointment.practitioner',
            'appointment.practitioner',
            'treatmentPlan',
        ])
            ->whereDate('issued_at', $dateCarbon)
            ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get();

        // Categorize patients
        $newPatients = [];
        $returningPatients = [];
        $followUpPatients = [];

        foreach ($invoices as $invoice) {
            $patient = $invoice->patient;
            if (! $patient) {
                continue;
            }

            $serviceLines = $invoice->lines->where('line_type', InvoiceLine::LINE_TYPE_SERVICE);

            foreach ($serviceLines as $line) {
                $practitioner = $line->appointment?->practitioner ?? $invoice->appointment?->practitioner;

                $patientData = [
                    'patient_id' => $patient->id,
                    'patient_name' => sanitizeForReport($patient->full_name),
                    'service_name' => sanitizeForReport(getTranslatedNameForReport($line->service?->name) ?? $line->description ?? '-'),
                    'category_id' => $line->service?->category_id,
                    'category_name' => sanitizeForReport(getTranslatedNameForReport($line->service?->category?->name) ?? __('reporting::reporting.uncategorized')),
                    'price' => $line->total_minor ?? 0,
                    'price_formatted' => format_money($line->total_minor ?? 0),
                    'invoice_id' => $invoice->id,
                    'practitioner_name' => sanitizeForReport($practitioner?->name ?? '-'),
                ];

                if ($invoice->treatment_plan_id || $line->package_subscription_id) {
                    $followUpPatients[] = $patientData;
                } elseif (isNewPatientForReport($patient->id, $invoice->id, $dateCarbon)) {
                    $newPatients[] = $patientData;
                } else {
                    $returningPatients[] = $patientData;
                }
            }
        }

        // Group by category
        $newPatientsGrouped = groupByCategoryForReport($newPatients);
        $returningPatientsGrouped = groupByCategoryForReport($returningPatients);
        $followUpPatientsGrouped = groupByCategoryForReport($followUpPatients);

        // Calculate stats
        $totalRevenue = $invoices->sum('total_minor');
        $totalProducts = 0;
        $productCount = 0;
        $serviceSales = [];

        foreach ($invoices as $invoice) {
            foreach ($invoice->lines as $line) {
                if ($line->line_type === InvoiceLine::LINE_TYPE_SERVICE) {
                    $serviceId = $line->service_id ?? 'other';
                    $serviceName = sanitizeForReport(getTranslatedNameForReport($line->service?->name) ?? $line->description ?? '-');

                    if (! isset($serviceSales[$serviceId])) {
                        $serviceSales[$serviceId] = ['name' => $serviceName, 'total' => 0, 'count' => 0];
                    }
                    $serviceSales[$serviceId]['total'] += $line->total_minor;
                    $serviceSales[$serviceId]['count']++;
                } elseif ($line->line_type === InvoiceLine::LINE_TYPE_PRODUCT) {
                    $totalProducts += $line->total_minor;
                    $productCount++;
                }
            }
        }

        $topService = collect($serviceSales)->sortByDesc('total')->first();
        $mostBooked = collect($serviceSales)->sortByDesc('count')->first();

        $newPatientIds = collect($newPatientsGrouped)->flatMap(fn ($cat) => collect($cat['patients'])->pluck('patient_id'))->unique();
        $returningPatientIds = collect($returningPatientsGrouped)->flatMap(fn ($cat) => collect($cat['patients'])->pluck('patient_id'))->unique();
        $followUpPatientIds = collect($followUpPatientsGrouped)->flatMap(fn ($cat) => collect($cat['patients'])->pluck('patient_id'))->unique();

        $branchName = $branchId ? Branch::find($branchId)?->name : null;

        return [
            'report_date' => $date,
            'branch_name' => $branchName,
            'newPatients' => $newPatientsGrouped,
            'returningPatients' => $returningPatientsGrouped,
            'followUpPatients' => $followUpPatientsGrouped,
            'stats' => [
                'total_revenue' => ['value' => format_money($totalRevenue)],
                'total_products' => ['value' => format_money($totalProducts), 'description' => $productCount.' '.__('reporting::reporting.items')],
                'highest_sales' => ['value' => $topService['name'] ?? '-', 'description' => $topService ? format_money($topService['total']) : ''],
                'most_booked' => ['value' => $mostBooked['name'] ?? '-', 'description' => $mostBooked ? $mostBooked['count'].' '.__('reporting::reporting.bookings') : ''],
                'new_patients_count' => ['value' => $newPatientIds->count()],
                'returning_patients_count' => ['value' => $returningPatientIds->count()],
                'followup_patients_count' => ['value' => $followUpPatientIds->count()],
            ],
        ];
    }
}

if (! function_exists('sanitizeForReport')) {
    function sanitizeForReport(?string $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        $value = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return $value ?: '-';
    }
}

if (! function_exists('getTranslatedNameForReport')) {
    function getTranslatedNameForReport(?string $name): ?string
    {
        if (! $name) {
            return null;
        }
        $decoded = json_decode($name, true);
        if (is_array($decoded)) {
            return $decoded[app()->getLocale()] ?? $decoded['en'] ?? $name;
        }

        return $name;
    }
}

if (! function_exists('isNewPatientForReport')) {
    function isNewPatientForReport(int $patientId, int $currentInvoiceId, Carbon $date): bool
    {
        return ! Invoice::where('patient_id', $patientId)
            ->where('id', '!=', $currentInvoiceId)
            ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED])
            ->where(function ($q) use ($date) {
                $q->whereDate('issued_at', '<', $date)
                    ->orWhere(function ($q2) use ($date) {
                        $q2->whereNull('issued_at')
                            ->whereDate('created_at', '<', $date);
                    });
            })
            ->exists();
    }
}

if (! function_exists('groupByCategoryForReport')) {
    function groupByCategoryForReport(array $items): array
    {
        return collect($items)
            ->groupBy('category_name')
            ->map(fn ($group) => [
                'patients' => $group->values()->all(),
                'total' => $group->sum('price'),
                'total_formatted' => format_money($group->sum('price')),
                'count' => $group->unique('patient_id')->count(),
            ])
            ->all();
    }
}
