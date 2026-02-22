<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Widgets\TenantOverviewWidget;
use App\Filament\Widgets\AppointmentStatsWidget;
use App\Filament\Widgets\CommissionPendingWidget;
use App\Filament\Widgets\LowStockAlertWidget;
use App\Filament\Widgets\NotificationStatsWidget;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = -2;

    protected static ?string $title = null;

    public static function getNavigationLabel(): string
    {
        return __('filament-panels::pages/dashboard.title');
    }

    public function getTitle(): string
    {
        return __('filament-panels::pages/dashboard.title');
    }

    public function getWidgets(): array
    {
        return [
            TenantOverviewWidget::class,
            AppointmentStatsWidget::class,
            CommissionPendingWidget::class,
            LowStockAlertWidget::class,
            NotificationStatsWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }
}
