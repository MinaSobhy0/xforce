<?php

namespace Modules\Inventory\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\StockLevel;

class StockReportStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $branchId = current_branch_id();

        $query = StockLevel::query()
            ->whereHas('product', fn ($q) => $q->where('is_active', true));

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $totalProducts = (clone $query)->distinct('product_id')->count('product_id');
        $totalQuantity = (clone $query)->sum('quantity_on_hand');

        // Calculate total value from FIFO layers
        $totalValue = DB::table('stock_movements')
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->where('products.is_active', true)
            ->whereIn('stock_movements.movement_type', ['purchase_receive', 'in', 'return'])
            ->where('stock_movements.remaining_quantity', '>', 0)
            ->when($branchId, fn ($q) => $q->where('stock_movements.branch_id', $branchId))
            ->selectRaw('SUM(stock_movements.remaining_quantity * stock_movements.unit_cost_minor) as total')
            ->value('total') ?? 0;

        // Low stock count
        $lowStockCount = DB::table('stock_levels')
            ->join('products', 'stock_levels.product_id', '=', 'products.id')
            ->where('products.is_active', true)
            ->whereColumn('stock_levels.quantity_on_hand', '<=', 'products.reorder_point')
            ->where('stock_levels.quantity_on_hand', '>', 0)
            ->when($branchId, fn ($q) => $q->where('stock_levels.branch_id', $branchId))
            ->count();

        return [
            Stat::make(__('inventory::inventory.stock_report.total_products'), number_format($totalProducts))
                ->description(__('inventory::inventory.stock_report.active_products'))
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),

            Stat::make(__('inventory::inventory.stock_report.total_quantity'), number_format($totalQuantity))
                ->description(__('inventory::inventory.stock_report.units_in_stock'))
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('success'),

            Stat::make(__('inventory::inventory.stock_report.low_stock_items'), number_format($lowStockCount))
                ->description(__('inventory::inventory.stock_report.need_reorder'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'warning' : 'success'),

            Stat::make(__('inventory::inventory.stock_report.total_value'), number_format($totalValue / 100, 2) . ' EGP')
                ->description(__('inventory::inventory.stock_report.inventory_value'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),
        ];
    }
}
