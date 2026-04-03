<?php

namespace Modules\OdooIntegration\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\OdooIntegration\Models\OdooConnection;
use Modules\OdooIntegration\Models\OdooSyncLog;
use Modules\OdooIntegration\Models\OdooSyncRecord;
use Modules\OdooIntegration\Models\OdooSyncConflict;

class SyncStatusWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $activeConnections = OdooConnection::where('is_active', true)->count();

        $todayLogs = OdooSyncLog::whereDate('started_at', today());
        $syncedToday = $todayLogs->sum('records_processed');
        $failedToday = $todayLogs->sum('records_failed');

        $totalSynced = OdooSyncRecord::where('sync_status', 'synced')->count();
        $pendingConflicts = OdooSyncConflict::where('status', 'pending')->count();

        $lastSync = OdooSyncLog::where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->first();

        return [
            Stat::make(__('odoo-integration::odoo.stats.active_connections'), $activeConnections)
                ->description(__('odoo-integration::odoo.stats.connections_description'))
                ->descriptionIcon('heroicon-m-server-stack')
                ->color('success'),

            Stat::make(__('odoo-integration::odoo.stats.synced_today'), number_format($syncedToday))
                ->description(__('odoo-integration::odoo.stats.synced_description'))
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info'),

            Stat::make(__('odoo-integration::odoo.stats.total_synced'), number_format($totalSynced))
                ->description(__('odoo-integration::odoo.stats.total_description'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make(__('odoo-integration::odoo.stats.pending_conflicts'), $pendingConflicts)
                ->description(__('odoo-integration::odoo.stats.conflicts_description'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($pendingConflicts > 0 ? 'danger' : 'gray'),

            Stat::make(__('odoo-integration::odoo.stats.failed_today'), number_format($failedToday))
                ->description(__('odoo-integration::odoo.stats.failed_description'))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($failedToday > 0 ? 'danger' : 'gray'),

            Stat::make(__('odoo-integration::odoo.stats.last_sync'), $lastSync?->completed_at?->diffForHumans() ?? '-')
                ->description($lastSync?->entityMapping?->name ?? __('odoo-integration::odoo.stats.no_sync_yet'))
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),
        ];
    }
}
