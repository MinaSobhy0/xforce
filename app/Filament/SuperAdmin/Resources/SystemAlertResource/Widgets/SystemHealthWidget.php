<?php

namespace App\Filament\SuperAdmin\Resources\SystemAlertResource\Widgets;

use App\Models\SystemAlert;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SystemHealthWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('PostgreSQL', 'Healthy')
                ->description('CPU: 12% | Connections: 45/200')
                ->color('success')
                ->icon('heroicon-o-circle-stack'),

            Stat::make('Redis', 'Healthy')
                ->description('Memory: 1.2/4 GB | Keys: 125K')
                ->color('success')
                ->icon('heroicon-o-bolt'),

            Stat::make('Queue (Horizon)', 'Running')
                ->description('Jobs/min: 85 | Pending: 12')
                ->color('success')
                ->icon('heroicon-o-queue-list'),

            Stat::make('Storage (S3)', '47% Used')
                ->description('234 / 500 GB')
                ->color('warning')
                ->icon('heroicon-o-server-stack'),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
