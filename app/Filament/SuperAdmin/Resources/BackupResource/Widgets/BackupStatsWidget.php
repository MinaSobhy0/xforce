<?php

namespace App\Filament\SuperAdmin\Resources\BackupResource\Widgets;

use App\Models\Backup;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BackupStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalBackups = Backup::count();
        $completedBackups = Backup::where('status', 'completed')->count();
        $lastBackup = Backup::where('status', 'completed')->latest()->first();
        $totalSize = Backup::where('status', 'completed')->sum('size');

        // Format total size
        if ($totalSize >= 1073741824) {
            $formattedSize = number_format($totalSize / 1073741824, 2) . ' GB';
        } elseif ($totalSize >= 1048576) {
            $formattedSize = number_format($totalSize / 1048576, 2) . ' MB';
        } else {
            $formattedSize = number_format($totalSize / 1024, 2) . ' KB';
        }

        return [
            Stat::make('Total Backups', $totalBackups)
                ->description('All time')
                ->icon('heroicon-o-archive-box')
                ->color('primary'),

            Stat::make('Successful', $completedBackups)
                ->description($totalBackups > 0 ? round(($completedBackups / $totalBackups) * 100, 1) . '% success rate' : 'No backups')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Last Backup', $lastBackup ? $lastBackup->created_at->diffForHumans() : 'Never')
                ->description($lastBackup ? $lastBackup->name : 'Create your first backup')
                ->icon('heroicon-o-clock')
                ->color($lastBackup && $lastBackup->created_at->diffInDays() > 7 ? 'warning' : 'success'),

            Stat::make('Total Storage', $formattedSize)
                ->description('Used by backups')
                ->icon('heroicon-o-circle-stack')
                ->color('info'),
        ];
    }
}
