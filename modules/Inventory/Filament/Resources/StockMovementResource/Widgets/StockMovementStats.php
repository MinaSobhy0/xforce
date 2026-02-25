<?php

namespace Modules\Inventory\Filament\Resources\StockMovementResource\Widgets;

use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Inventory\Models\StockMovement;

class StockMovementStats extends BaseWidget
{
    protected function getStats(): array
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();

        // Today's stats
        $todayIncoming = StockMovement::incoming()
            ->whereDate('created_at', $today)
            ->sum('quantity');

        $todayOutgoing = StockMovement::outgoing()
            ->whereDate('created_at', $today)
            ->sum('quantity');

        $todayMovements = StockMovement::whereDate('created_at', $today)->count();

        // This month's stats
        $monthIncoming = StockMovement::incoming()
            ->where('created_at', '>=', $thisMonth)
            ->sum('quantity');

        $monthOutgoing = StockMovement::outgoing()
            ->where('created_at', '>=', $thisMonth)
            ->sum('quantity');

        return [
            Stat::make(__('inventory::inventory.stats.today_movements'), $todayMovements)
                ->description(__('inventory::inventory.stats.movements_today'))
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color('primary'),

            Stat::make(__('inventory::inventory.stats.today_incoming'), '+' . number_format($todayIncoming))
                ->description(__('inventory::inventory.stats.units_received'))
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('success'),

            Stat::make(__('inventory::inventory.stats.today_outgoing'), '-' . number_format(abs($todayOutgoing)))
                ->description(__('inventory::inventory.stats.units_issued'))
                ->descriptionIcon('heroicon-m-arrow-up-tray')
                ->color('danger'),

            Stat::make(__('inventory::inventory.stats.month_net'), number_format($monthIncoming - abs($monthOutgoing)))
                ->description(__('inventory::inventory.stats.net_change_this_month'))
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($monthIncoming >= abs($monthOutgoing) ? 'success' : 'warning'),
        ];
    }
}
