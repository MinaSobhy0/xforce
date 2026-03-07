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
        $user = auth()->user();
        if (!$user) return false;

        if (method_exists($user, 'hasRole') && $user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner', 'admin'])) {
            return true;
        }

        return $user->can('reports.view') || !\Spatie\Permission\Models\Permission::where('name', 'reports.view')->where('guard_name', 'web')->exists();
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
