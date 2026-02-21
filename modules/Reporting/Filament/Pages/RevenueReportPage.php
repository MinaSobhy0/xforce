<?php

namespace Modules\Reporting\Filament\Pages;

use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Services\Models\Service;
use Modules\Core\Models\Branch;
use Illuminate\Support\Facades\DB;

class RevenueReportPage extends BaseReportPage
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('reporting::reporting.revenue_report');
    }

    public function getTitle(): string
    {
        return __('reporting::reporting.revenue_report');
    }

    protected function getReportTitle(): string
    {
        return __('reporting::reporting.revenue_report');
    }

    protected function loadReportData(): void
    {
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();
        $branchId = $this->getBranchId();

        // Base query for payments
        $paymentsQuery = Payment::query()
            ->whereBetween('paid_at', [$startDate, $endDate]);

        if ($branchId) {
            $paymentsQuery->whereHas('invoice', fn ($q) => $q->where('branch_id', $branchId));
        }

        // Total revenue
        $totalRevenue = (clone $paymentsQuery)->sum('amount_minor');

        // Previous period for comparison
        $periodDays = $startDate->diffInDays($endDate) + 1;
        $prevStartDate = $startDate->copy()->subDays($periodDays);
        $prevEndDate = $startDate->copy()->subDay();

        $prevPaymentsQuery = Payment::query()
            ->whereBetween('paid_at', [$prevStartDate, $prevEndDate]);

        if ($branchId) {
            $prevPaymentsQuery->whereHas('invoice', fn ($q) => $q->where('branch_id', $branchId));
        }

        $previousRevenue = $prevPaymentsQuery->sum('amount_minor');

        // Invoices stats
        $invoicesQuery = Invoice::query()
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($branchId) {
            $invoicesQuery->where('branch_id', $branchId);
        }

        $totalInvoices = (clone $invoicesQuery)->count();
        $paidInvoices = (clone $invoicesQuery)->where('status', 'paid')->count();
        $outstandingAmount = (clone $invoicesQuery)
            ->whereIn('status', ['issued', 'partially_paid'])
            ->selectRaw('COALESCE(SUM(total_minor - paid_minor), 0) as outstanding')
            ->value('outstanding') ?? 0;

        // Stats cards data
        $this->stats = [
            [
                'label' => __('reporting::reporting.total_revenue'),
                'value' => $this->formatCurrency($totalRevenue),
                'change' => $this->calculatePercentageChange($totalRevenue, $previousRevenue),
                'icon' => 'heroicon-o-banknotes',
                'color' => 'success',
            ],
            [
                'label' => __('reporting::reporting.total_invoices'),
                'value' => number_format($totalInvoices),
                'description' => $paidInvoices . ' ' . __('reporting::reporting.paid'),
                'icon' => 'heroicon-o-document-text',
                'color' => 'primary',
            ],
            [
                'label' => __('reporting::reporting.outstanding'),
                'value' => $this->formatCurrency($outstandingAmount),
                'icon' => 'heroicon-o-clock',
                'color' => 'warning',
            ],
            [
                'label' => __('reporting::reporting.average_invoice'),
                'value' => $totalInvoices > 0 ? $this->formatCurrency($totalRevenue / $totalInvoices) : '0 EGP',
                'icon' => 'heroicon-o-calculator',
                'color' => 'info',
            ],
        ];

        // Revenue by service
        $revenueByService = DB::table('payments')
            ->join('invoices', 'payments.invoice_id', '=', 'invoices.id')
            ->join('invoice_lines', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->join('services', 'invoice_lines.service_id', '=', 'services.id')
            ->whereBetween('payments.paid_at', [$startDate, $endDate])
            ->when($branchId, fn ($q) => $q->where('invoices.branch_id', $branchId))
            ->groupBy('services.id', 'services.name')
            ->select(
                'services.id',
                'services.name',
                DB::raw('SUM(payments.amount_minor) as total_revenue'),
                DB::raw('COUNT(DISTINCT payments.id) as payment_count')
            )
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        // Revenue by branch
        $revenueByBranch = DB::table('payments')
            ->join('invoices', 'payments.invoice_id', '=', 'invoices.id')
            ->join('branches', 'invoices.branch_id', '=', 'branches.id')
            ->whereBetween('payments.paid_at', [$startDate, $endDate])
            ->groupBy('branches.id', 'branches.name')
            ->select(
                'branches.id',
                'branches.name',
                DB::raw('SUM(payments.amount_minor) as total_revenue')
            )
            ->orderByDesc('total_revenue')
            ->get();

        // Daily revenue for chart
        $dailyRevenue = DB::table('payments')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->when($branchId, function ($q) use ($branchId) {
                $q->whereIn('invoice_id', Invoice::where('branch_id', $branchId)->pluck('id'));
            })
            ->groupBy(DB::raw('DATE(paid_at)'))
            ->select(
                DB::raw('DATE(paid_at) as date'),
                DB::raw('SUM(amount_minor) as total')
            )
            ->orderBy('date')
            ->get();

        // Chart data
        $this->chartData = [
            'labels' => $dailyRevenue->pluck('date')->map(fn ($d) => \Carbon\Carbon::parse($d)->format('M d'))->toArray(),
            'datasets' => [
                [
                    'label' => __('reporting::reporting.daily_revenue'),
                    'data' => $dailyRevenue->pluck('total')->map(fn ($v) => $v / 100)->toArray(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.5)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 2,
                ],
            ],
        ];

        // Table data
        $this->tableData = $revenueByService->map(fn ($item) => [
            'service' => is_array(json_decode($item->name, true))
                ? (json_decode($item->name, true)['en'] ?? $item->name)
                : $item->name,
            'revenue' => $this->formatCurrency($item->total_revenue),
            'payments' => $item->payment_count,
            'percentage' => $totalRevenue > 0
                ? $this->formatPercentage(($item->total_revenue / $totalRevenue) * 100)
                : '0%',
        ])->toArray();
    }

    protected function getStatsCards(): array
    {
        return $this->stats;
    }

    protected function getChartConfig(): array
    {
        return [
            'type' => 'bar',
            'data' => $this->chartData,
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'legend' => ['display' => false],
                    'title' => [
                        'display' => true,
                        'text' => __('reporting::reporting.daily_revenue'),
                    ],
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'ticks' => [
                            'callback' => 'function(value) { return value + " EGP"; }',
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function getTableColumns(): array
    {
        return [
            ['key' => 'service', 'label' => __('reporting::reporting.service')],
            ['key' => 'revenue', 'label' => __('reporting::reporting.revenue')],
            ['key' => 'payments', 'label' => __('reporting::reporting.payments')],
            ['key' => 'percentage', 'label' => __('reporting::reporting.percentage')],
        ];
    }
}
