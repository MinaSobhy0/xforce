<?php

namespace Modules\Reporting\Filament\Pages;

use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Core\Models\Branch;

class DailySalesReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-bar';

    protected static string $view = 'reporting::filament.pages.daily-sales-report';

    protected static ?string $navigationGroup = 'Reports';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.rpt_finance');
    }

    protected static ?int $navigationSort = 10;

    protected ?string $maxContentWidth = 'full';

    public ?string $report_date = null;

    public ?string $branch_id = null;

    // Report data
    public array $newPatients = [];

    public array $returningPatients = [];

    public array $followUpPatients = [];

    public array $stats = [];

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public static function getNavigationLabel(): string
    {
        return __('reporting::reporting.daily_sales_report');
    }

    public function getTitle(): string
    {
        return __('reporting::reporting.daily_sales_report');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_pdf')
                ->label(__('reporting::reporting.export_pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->url(fn () => $this->getPdfDownloadUrl())
                ->openUrlInNewTab(),
        ];
    }

    public function getPdfDownloadUrl(): string
    {
        $params = ['date' => $this->report_date];
        if ($this->branch_id) {
            $params['branch_id'] = $this->branch_id;
        }

        return route('daily-sales.pdf', $params);
    }

    public function mount(): void
    {
        $this->report_date = now()->format('Y-m-d');
        $this->loadReportData();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            DatePicker::make('report_date')
                ->label(__('reporting::reporting.date'))
                ->default(now())
                ->live()
                ->afterStateUpdated(fn () => $this->loadReportData()),

            Select::make('branch_id')
                ->label(__('reporting::reporting.branch'))
                ->options($this->getBranchOptions())
                ->placeholder(__('reporting::reporting.all_branches'))
                ->live()
                ->afterStateUpdated(fn () => $this->loadReportData()),
        ])->columns(2);
    }

    /**
     * Get branch options with sanitized names
     */
    protected function getBranchOptions(): array
    {
        return Branch::where('is_active', true)
            ->pluck('name', 'id')
            ->map(fn ($name) => $this->sanitizeUtf8($name))
            ->toArray();
    }

    protected function loadReportData(): void
    {
        $date = Carbon::parse($this->report_date);
        $branchId = $this->branch_id;

        // Get confirmed invoices for the date
        $invoices = $this->getConfirmedInvoices($date, $branchId);

        // Categorize patients from invoice data
        $this->categorizePatients($invoices, $date);

        // Calculate stats
        $this->calculateStats($invoices, $date, $branchId);
    }

    /**
     * Validate that all public properties are JSON-encodable
     */
    protected function validateJsonEncodable(): void
    {
        // Force clean encoding by encoding and decoding with substitution
        $cleanData = function ($data) {
            $encoded = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if ($encoded === false) {
                \Log::error('JSON encode failed: '.json_last_error_msg());

                return [];
            }

            return json_decode($encoded, true) ?? [];
        };

        $this->newPatients = $cleanData($this->newPatients);
        $this->returningPatients = $cleanData($this->returningPatients);
        $this->followUpPatients = $cleanData($this->followUpPatients);
        $this->stats = $cleanData($this->stats);

        // Also clean the report_date and branch_id
        $this->report_date = $this->report_date ? (string) $this->report_date : null;
        $this->branch_id = $this->branch_id ? (string) $this->branch_id : null;
    }

    /**
     * Get confirmed invoices (issued, partially_paid, paid) for the date
     */
    protected function getConfirmedInvoices(Carbon $date, ?string $branchId): Collection
    {
        return Invoice::with([
            'patient',
            'lines.service.category',
            'lines.product',
            'lines.appointment.practitioner',
            'appointment.practitioner',
            'treatmentPlan',
        ])
            ->whereDate('issued_at', $date)
            ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get();
    }

    /**
     * Categorize patients from invoices into new, returning, and follow-up
     */
    protected function categorizePatients(Collection $invoices, Carbon $date): void
    {
        $newPatients = [];
        $returningPatients = [];
        $followUpPatients = [];

        foreach ($invoices as $invoice) {
            $patient = $invoice->patient;
            if (! $patient) {
                continue;
            }

            // Get service lines from this invoice
            $serviceLines = $invoice->lines->where('line_type', InvoiceLine::LINE_TYPE_SERVICE);

            foreach ($serviceLines as $line) {
                // Get practitioner from line's appointment or invoice's appointment
                $practitioner = $line->appointment?->practitioner ?? $invoice->appointment?->practitioner;

                $patientData = [
                    'patient_id' => $patient->id,
                    'patient_name' => $this->sanitizeUtf8($patient->full_name),
                    'service_name' => $this->sanitizeUtf8($this->getTranslatedName($line->service?->name) ?? $line->description ?? '-'),
                    'category_id' => $line->service?->category_id,
                    'category_name' => $this->sanitizeUtf8($this->getTranslatedName($line->service?->category?->name) ?? __('reporting::reporting.uncategorized')),
                    'price' => $line->total_minor ?? 0,
                    'price_formatted' => format_money($line->total_minor ?? 0),
                    'invoice_id' => $invoice->id,
                    'practitioner_name' => $this->sanitizeUtf8($practitioner?->name ?? '-'),
                ];

                // Check if follow-up (linked to treatment plan or has package subscription)
                if ($invoice->treatment_plan_id || $line->package_subscription_id) {
                    $followUpPatients[] = $patientData;
                }
                // Check if new patient (no previous confirmed invoices before this date)
                elseif ($this->isNewPatient($patient->id, $invoice->id, $date)) {
                    $newPatients[] = $patientData;
                }
                // Otherwise returning patient
                else {
                    $returningPatients[] = $patientData;
                }
            }
        }

        // Group by category and sanitize for JSON
        $this->newPatients = $this->sanitizeArrayForJson($this->groupByCategory($newPatients));
        $this->returningPatients = $this->sanitizeArrayForJson($this->groupByCategory($returningPatients));
        $this->followUpPatients = $this->sanitizeArrayForJson($this->groupByCategory($followUpPatients));
    }

    /**
     * Check if patient is new (no confirmed invoices before this date)
     */
    protected function isNewPatient(int $patientId, int $currentInvoiceId, Carbon $date): bool
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

    /**
     * Group patient data by service category
     */
    protected function groupByCategory(array $items): array
    {
        return collect($items)
            ->groupBy('category_name')
            ->map(fn ($group) => [
                'patients' => $group->values()->all(),
                'total' => $group->sum('price'),
                'total_formatted' => format_money($group->sum('price')),
                'count' => $group->unique('patient_id')->count(), // Unique patients
            ])
            ->all();
    }

    /**
     * Calculate summary statistics from invoices
     */
    protected function calculateStats(Collection $invoices, Carbon $date, ?string $branchId): void
    {
        // Total revenue from invoices
        $totalRevenue = $invoices->sum('total_minor');

        // Total services revenue
        $totalServices = 0;
        $serviceCount = 0;

        // Total products revenue
        $totalProducts = 0;
        $productCount = 0;

        // Track service sales for finding top service
        $serviceSales = [];

        foreach ($invoices as $invoice) {
            foreach ($invoice->lines as $line) {
                if ($line->line_type === InvoiceLine::LINE_TYPE_SERVICE) {
                    $totalServices += $line->total_minor;
                    $serviceCount++;

                    // Track by service for top seller
                    $serviceId = $line->service_id ?? 'other';
                    $serviceName = $this->sanitizeUtf8($this->getTranslatedName($line->service?->name) ?? $line->description ?? '-');

                    if (! isset($serviceSales[$serviceId])) {
                        $serviceSales[$serviceId] = [
                            'name' => $serviceName,
                            'total' => 0,
                            'count' => 0,
                        ];
                    }
                    $serviceSales[$serviceId]['total'] += $line->total_minor;
                    $serviceSales[$serviceId]['count']++;
                } elseif ($line->line_type === InvoiceLine::LINE_TYPE_PRODUCT) {
                    $totalProducts += $line->total_minor;
                    $productCount++;
                }
            }
        }

        // Find highest sales service (by revenue)
        $topService = collect($serviceSales)->sortByDesc('total')->first();

        // Find most booked service (by count)
        $mostBooked = collect($serviceSales)->sortByDesc('count')->first();

        $this->stats = [
            'total_revenue' => [
                'label' => __('reporting::reporting.total_revenue'),
                'value' => format_money($totalRevenue),
                'icon' => 'heroicon-o-currency-dollar',
                'color' => 'success',
            ],
            'total_products' => [
                'label' => __('reporting::reporting.total_sold_products'),
                'value' => format_money($totalProducts),
                'description' => $productCount.' '.__('reporting::reporting.items'),
                'icon' => 'heroicon-o-shopping-bag',
                'color' => 'info',
            ],
            'highest_sales' => [
                'label' => __('reporting::reporting.highest_sales'),
                'value' => $topService['name'] ?? '-',
                'description' => $topService ? format_money($topService['total']) : '',
                'icon' => 'heroicon-o-arrow-trending-up',
                'color' => 'warning',
            ],
            'most_booked' => [
                'label' => __('reporting::reporting.highest_services'),
                'value' => $mostBooked['name'] ?? '-',
                'description' => $mostBooked ? $mostBooked['count'].' '.__('reporting::reporting.bookings') : '',
                'icon' => 'heroicon-o-calendar',
                'color' => 'primary',
            ],
        ];

        // Patient counts (unique patients per category)
        $newPatientIds = collect($this->newPatients)->flatMap(fn ($cat) => collect($cat['patients'])->pluck('patient_id'))->unique();
        $returningPatientIds = collect($this->returningPatients)->flatMap(fn ($cat) => collect($cat['patients'])->pluck('patient_id'))->unique();
        $followUpPatientIds = collect($this->followUpPatients)->flatMap(fn ($cat) => collect($cat['patients'])->pluck('patient_id'))->unique();

        $this->stats['new_patients_count'] = [
            'label' => __('reporting::reporting.new_patients'),
            'value' => $newPatientIds->count(),
            'icon' => 'heroicon-o-user-plus',
            'color' => 'success',
        ];
        $this->stats['returning_patients_count'] = [
            'label' => __('reporting::reporting.returning_patients'),
            'value' => $returningPatientIds->count(),
            'icon' => 'heroicon-o-users',
            'color' => 'info',
        ];
        $this->stats['followup_patients_count'] = [
            'label' => __('reporting::reporting.followup_patients'),
            'value' => $followUpPatientIds->count(),
            'icon' => 'heroicon-o-arrow-path',
            'color' => 'warning',
        ];

        // Sanitize all stats for JSON encoding
        $this->stats = $this->sanitizeArrayForJson($this->stats);
    }

    /**
     * Get translated name from JSON column or return as-is
     */
    protected function getTranslatedName(?string $name): ?string
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

    /**
     * Sanitize string to ensure valid UTF-8 encoding
     */
    protected function sanitizeUtf8(?string $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        // Force conversion to UTF-8, ignoring invalid sequences
        $value = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        if ($value === false || $value === '') {
            return '-';
        }

        // Remove control characters except newlines and tabs
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        return $value ?: '-';
    }

    /**
     * Recursively sanitize an array for JSON encoding
     */
    protected function sanitizeArrayForJson(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            // Sanitize the key if it's a string
            $safeKey = is_string($key) ? $this->sanitizeUtf8($key) : $key;

            if (is_array($value)) {
                $result[$safeKey] = $this->sanitizeArrayForJson($value);
            } elseif (is_string($value)) {
                $result[$safeKey] = $this->sanitizeUtf8($value);
            } else {
                $result[$safeKey] = $value;
            }
        }

        return $result;
    }
}
