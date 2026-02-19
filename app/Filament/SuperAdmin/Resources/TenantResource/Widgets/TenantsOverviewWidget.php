<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\Widgets;

use Modules\Core\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TenantsOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $active = Tenant::where('subscription_status', 'active')
            ->orWhere('status', 'active')
            ->count();

        $trial = Tenant::where('subscription_status', 'trial')->count();

        $suspended = Tenant::where('subscription_status', 'suspended')
            ->orWhere('status', 'suspended')
            ->count();

        $trialExpiringSoon = Tenant::where('subscription_status', 'trial')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(3)])
            ->count();

        $overdue = Tenant::where('subscription_status', 'past_due')->count();

        return [
            Stat::make('Active Clinics', $active)
                ->description("+{$trial} on trial")
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color('success')
                ->chart([7, 9, 12, 15, 18, 22, $active]),

            Stat::make('Trials Expiring', $trialExpiringSoon)
                ->description('In next 3 days')
                ->descriptionIcon('heroicon-o-clock')
                ->color($trialExpiringSoon > 0 ? 'warning' : 'gray'),

            Stat::make('Overdue Payments', $overdue)
                ->description($suspended . ' suspended')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($overdue > 0 ? 'danger' : 'success'),

            Stat::make('Total Clinics', Tenant::count())
                ->description('All tenants')
                ->descriptionIcon('heroicon-o-building-office-2')
                ->color('info'),
        ];
    }
}
