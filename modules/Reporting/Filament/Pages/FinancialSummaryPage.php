<?php

namespace Modules\Reporting\Filament\Pages;

use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Payroll\Models\PayrollRun;
use Modules\Inventory\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class FinancialSummaryPage extends BaseReportPage
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('reporting::reporting.financial_summary');
    }

    public function getTitle(): string
    {
        return __('reporting::reporting.financial_summary');
    }

    protected function getReportTitle(): string
    {
        return __('reporting::reporting.financial_summary');
    }

    protected function loadReportData(): void
    {
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();
        $branchId = $this->getBranchId();

        // Revenue (payments received)
        $revenueQuery = Payment::query()
            ->whereBetween('paid_at', [$startDate, $endDate]);

        if ($branchId) {
            $revenueQuery->whereHas('invoice', fn ($q) => $q->where('branch_id', $branchId));
        }

        $totalRevenue = $revenueQuery->sum('amount_minor');

        // Receivables (outstanding invoices)
        $receivablesQuery = Invoice::query()
            ->whereIn('status', ['sent', 'partially_paid', 'overdue']);

        if ($branchId) {
            $receivablesQuery->where('branch_id', $branchId);
        }

        $totalReceivables = $receivablesQuery->sum(DB::raw('total_minor - paid_minor'));
        $overdueReceivables = (clone $receivablesQuery)
            ->where('due_date', '<', now())
            ->sum(DB::raw('total_minor - paid_minor'));

        // Expenses (not tracked in current schema)
        $totalExpenses = 0;

        // Payroll costs
        $payrollQuery = PayrollRun::query()
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->where('status', 'paid');

        $payrollCost = $payrollQuery->sum('total_net_salary_minor');

        // Purchase orders (inventory costs)
        $purchaseQuery = PurchaseOrder::query()
            ->whereBetween('order_date', [$startDate, $endDate])
            ->whereIn('status', ['received', 'partial']);

        if ($branchId) {
            $purchaseQuery->where('branch_id', $branchId);
        }

        $purchaseCost = $purchaseQuery->sum('total_amount_minor');

        // Calculate totals
        $totalCosts = $totalExpenses + $payrollCost + $purchaseCost;
        $grossProfit = $totalRevenue - $totalCosts;
        $profitMargin = $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0;

        // Stats cards
        $this->stats = [
            [
                'label' => __('reporting::reporting.total_revenue'),
                'value' => $this->formatCurrency($totalRevenue),
                'icon' => 'heroicon-o-arrow-trending-up',
                'color' => 'success',
            ],
            [
                'label' => __('reporting::reporting.total_costs'),
                'value' => $this->formatCurrency($totalCosts),
                'description' => __('reporting::reporting.payroll') . ': ' . $this->formatCurrency($payrollCost),
                'icon' => 'heroicon-o-arrow-trending-down',
                'color' => 'danger',
            ],
            [
                'label' => __('reporting::reporting.gross_profit'),
                'value' => $this->formatCurrency($grossProfit),
                'description' => $this->formatPercentage($profitMargin) . ' ' . __('reporting::reporting.margin'),
                'icon' => 'heroicon-o-banknotes',
                'color' => $grossProfit > 0 ? 'success' : 'danger',
            ],
            [
                'label' => __('reporting::reporting.receivables'),
                'value' => $this->formatCurrency($totalReceivables),
                'description' => $this->formatCurrency($overdueReceivables) . ' ' . __('reporting::reporting.overdue'),
                'icon' => 'heroicon-o-clock',
                'color' => 'warning',
            ],
        ];

        // Monthly P&L trend
        $monthlyRevenue = Payment::query()
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->when($branchId, function ($q) use ($branchId) {
                $q->whereHas('invoice', fn ($iq) => $iq->where('branch_id', $branchId));
            })
            ->groupBy(DB::raw("DATE_TRUNC('month', paid_at)"))
            ->select(
                DB::raw("DATE_TRUNC('month', paid_at) as month"),
                DB::raw('SUM(amount_minor) as total')
            )
            ->orderBy('month')
            ->get();

        // Expenses not tracked in current schema - using empty collection
        $monthlyExpenses = collect();

        // Chart data
        $this->chartData = [
            'labels' => $monthlyRevenue->map(fn ($item) => \Carbon\Carbon::parse($item->month)->format('M Y'))->toArray(),
            'datasets' => [
                [
                    'label' => __('reporting::reporting.revenue'),
                    'data' => $monthlyRevenue->pluck('total')->map(fn ($v) => $v / 100)->toArray(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.5)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 2,
                ],
                [
                    'label' => __('reporting::reporting.expenses'),
                    'data' => $monthlyRevenue->map(function ($item) use ($monthlyExpenses) {
                        $key = \Carbon\Carbon::parse($item->month)->format('Y-m');
                        return ($monthlyExpenses[$key]->total ?? 0) / 100;
                    })->toArray(),
                    'backgroundColor' => 'rgba(239, 68, 68, 0.5)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'borderWidth' => 2,
                ],
            ],
        ];

        // Table data - cost breakdown (payroll and inventory only)
        $this->tableData = [
            [
                'category' => __('reporting::reporting.payroll_costs'),
                'count' => '-',
                'total' => $this->formatCurrency($payrollCost),
                'percentage' => $totalCosts > 0
                    ? $this->formatPercentage(($payrollCost / $totalCosts) * 100)
                    : '0%',
            ],
            [
                'category' => __('reporting::reporting.inventory_purchases'),
                'count' => '-',
                'total' => $this->formatCurrency($purchaseCost),
                'percentage' => $totalCosts > 0
                    ? $this->formatPercentage(($purchaseCost / $totalCosts) * 100)
                    : '0%',
            ],
        ];
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
                    'title' => [
                        'display' => true,
                        'text' => __('reporting::reporting.monthly_pl'),
                    ],
                ],
                'scales' => [
                    'y' => ['beginAtZero' => true],
                ],
            ],
        ];
    }

    protected function getTableColumns(): array
    {
        return [
            ['key' => 'category', 'label' => __('reporting::reporting.category')],
            ['key' => 'count', 'label' => __('reporting::reporting.count')],
            ['key' => 'total', 'label' => __('reporting::reporting.total')],
            ['key' => 'percentage', 'label' => __('reporting::reporting.percentage')],
        ];
    }
}
