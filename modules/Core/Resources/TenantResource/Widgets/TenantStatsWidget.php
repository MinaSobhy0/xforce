<?php

namespace Modules\Core\Resources\TenantResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantStatus;

class TenantStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', TenantStatus::ACTIVE)->count();
        $suspendedTenants = Tenant::where('status', TenantStatus::SUSPENDED)->count();
        $pendingTenants = Tenant::where('status', TenantStatus::PENDING)->count();
        $expiredTenants = Tenant::where('subscription_expires_at', '<', now())->count();

        $activePercentage = $totalTenants > 0 ? round(($activeTenants / $totalTenants) * 100, 1) : 0;

        return [
            Stat::make(__('Total Tenants'), $totalTenants)
                ->description(__('All registered tenants'))
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary'),

            Stat::make(__('Active Tenants'), $activeTenants)
                ->description($activePercentage . '% ' . __('of total'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart([7, 12, 18, 14, 21, 28, $activeTenants]),

            Stat::make(__('Suspended'), $suspendedTenants)
                ->description(__('Temporarily suspended'))
                ->descriptionIcon('heroicon-m-pause-circle')
                ->color('warning'),

            Stat::make(__('Pending'), $pendingTenants)
                ->description(__('Awaiting setup'))
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),

            Stat::make(__('Expired'), $expiredTenants)
                ->description(__('Subscription ended'))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}