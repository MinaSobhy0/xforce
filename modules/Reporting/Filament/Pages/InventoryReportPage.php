<?php

namespace Modules\Reporting\Filament\Pages;

use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockLevel;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class InventoryReportPage extends BaseReportPage
{
    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('reporting::reporting.inventory_report');
    }

    public function getTitle(): string
    {
        return __('reporting::reporting.inventory_report');
    }

    protected function getReportTitle(): string
    {
        return __('reporting::reporting.inventory_report');
    }

    protected function loadReportData(): void
    {
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();
        $branchId = $this->getBranchId();

        // Total products
        $totalProducts = Product::where('is_active', true)->count();

        // Stock valuation
        $stockValuationQuery = DB::table('stock_levels')
            ->join('products', 'stock_levels.product_id', '=', 'products.id');

        if ($branchId) {
            $stockValuationQuery->where('stock_levels.branch_id', $branchId);
        }

        $stockValuation = $stockValuationQuery
            ->select(DB::raw('SUM(stock_levels.quantity_on_hand * products.cost_price_minor) as total'))
            ->value('total') ?? 0;

        // Low stock products
        $lowStockQuery = DB::table('stock_levels')
            ->join('products', 'stock_levels.product_id', '=', 'products.id')
            ->whereColumn('stock_levels.quantity_on_hand', '<=', 'products.reorder_point');

        if ($branchId) {
            $lowStockQuery->where('stock_levels.branch_id', $branchId);
        }

        $lowStockCount = $lowStockQuery->count();

        // Stock movements in period
        $movementsQuery = StockMovement::query()
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($branchId) {
            $movementsQuery->where(function ($q) use ($branchId) {
                $q->where('source_branch_id', $branchId)
                    ->orWhere('destination_branch_id', $branchId)
                    ->orWhere('branch_id', $branchId);
            });
        }

        $totalMovements = $movementsQuery->count();
        $movementsIn = (clone $movementsQuery)->whereIn('movement_type', ['in', 'purchase_receive', 'transfer_in', 'return'])->sum('quantity');
        $movementsOut = (clone $movementsQuery)->whereIn('movement_type', ['out', 'appointment_consume', 'transfer_out', 'waste'])->sum('quantity');

        // Purchase orders in period
        $poQuery = PurchaseOrder::query()
            ->whereBetween('order_date', [$startDate, $endDate]);

        if ($branchId) {
            $poQuery->where('branch_id', $branchId);
        }

        $purchaseOrdersValue = $poQuery->sum('subtotal_minor');

        // Stats cards
        $this->stats = [
            [
                'label' => __('reporting::reporting.stock_valuation'),
                'value' => $this->formatCurrency($stockValuation),
                'description' => $totalProducts . ' ' . __('reporting::reporting.products'),
                'icon' => 'heroicon-o-cube',
                'color' => 'primary',
            ],
            [
                'label' => __('reporting::reporting.low_stock_items'),
                'value' => number_format($lowStockCount),
                'icon' => 'heroicon-o-exclamation-triangle',
                'color' => $lowStockCount > 0 ? 'danger' : 'success',
            ],
            [
                'label' => __('reporting::reporting.stock_movements'),
                'value' => number_format($totalMovements),
                'description' => '+' . $movementsIn . ' / -' . $movementsOut,
                'icon' => 'heroicon-o-arrows-right-left',
                'color' => 'info',
            ],
            [
                'label' => __('reporting::reporting.purchase_orders'),
                'value' => $this->formatCurrency($purchaseOrdersValue),
                'icon' => 'heroicon-o-shopping-cart',
                'color' => 'warning',
            ],
        ];

        // Top consumed products
        $topConsumed = DB::table('stock_movements')
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->whereBetween('stock_movements.created_at', [$startDate, $endDate])
            ->whereIn('stock_movements.movement_type', ['out', 'appointment_consume', 'waste'])
            ->when($branchId, fn ($q) => $q->where('stock_movements.branch_id', $branchId))
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.cost_price_minor')
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(stock_movements.quantity) as consumed'),
                DB::raw('SUM(stock_movements.quantity * products.cost_price_minor) as total_cost')
            )
            ->orderByDesc('consumed')
            ->limit(10)
            ->get();

        // Movements by type for chart
        $movementsByType = StockMovement::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($branchId, function ($q) use ($branchId) {
                $q->where(function ($sq) use ($branchId) {
                    $sq->where('source_branch_id', $branchId)
                        ->orWhere('destination_branch_id', $branchId)
                        ->orWhere('branch_id', $branchId);
                });
            })
            ->groupBy('movement_type')
            ->select('movement_type', DB::raw('SUM(quantity) as total'))
            ->get();

        // Chart data
        $this->chartData = [
            'labels' => $movementsByType->pluck('movement_type')->map(fn ($t) => ucfirst(str_replace('_', ' ', $t)))->toArray(),
            'datasets' => [
                [
                    'data' => $movementsByType->pluck('total')->toArray(),
                    'backgroundColor' => [
                        'rgba(34, 197, 94, 0.7)',  // in - green
                        'rgba(239, 68, 68, 0.7)', // out - red
                        'rgba(59, 130, 246, 0.7)', // transfer - blue
                        'rgba(245, 158, 11, 0.7)', // adjustment - yellow
                    ],
                ],
            ],
        ];

        // Table data
        $this->tableData = $topConsumed->map(fn ($item) => [
            'product' => is_array(json_decode($item->name, true))
                ? (json_decode($item->name, true)['en'] ?? $item->name)
                : $item->name,
            'sku' => $item->sku,
            'consumed' => number_format($item->consumed),
            'cost' => $this->formatCurrency($item->total_cost),
        ])->toArray();
    }

    protected function getStatsCards(): array
    {
        return $this->stats;
    }

    protected function getChartConfig(): array
    {
        return [
            'type' => 'pie',
            'data' => $this->chartData,
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'legend' => ['position' => 'bottom'],
                    'title' => [
                        'display' => true,
                        'text' => __('reporting::reporting.movements_by_type'),
                    ],
                ],
            ],
        ];
    }

    protected function getTableColumns(): array
    {
        return [
            ['key' => 'product', 'label' => __('reporting::reporting.product')],
            ['key' => 'sku', 'label' => __('reporting::reporting.sku')],
            ['key' => 'consumed', 'label' => __('reporting::reporting.consumed')],
            ['key' => 'cost', 'label' => __('reporting::reporting.total_cost')],
        ];
    }
}
