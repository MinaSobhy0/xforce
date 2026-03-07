<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Modules\Core\Filament\Widgets\TenantOverviewWidget;
use Modules\Booking\Filament\Widgets\AppointmentStatsWidget;
use Modules\Staff\Filament\Widgets\CommissionPendingWidget;
use Modules\Inventory\Filament\Widgets\LowStockAlertWidget;
use Modules\Marketing\Filament\Widgets\NotificationStatsWidget;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = -2;

    protected static ?string $title = null;

    public static function canAccess(): bool
    {
        // Dashboard should always be accessible to authenticated users
        return auth()->check();
    }

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
