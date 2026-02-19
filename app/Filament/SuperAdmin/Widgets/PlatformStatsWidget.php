<?php

namespace App\Filament\SuperAdmin\Widgets;

use Modules\Core\Models\Tenant;
use App\Models\SubscriptionPlan;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Calculate MRR from active subscriptions
        $activeTenants = Tenant::where('subscription_status', 'active')
            ->orWhere('status', 'active')
            ->with('plan')
            ->get();

        $mrr = $activeTenants->sum(function ($tenant) {
            return $tenant->plan?->price_monthly_minor ?? 0;
        });

        $activeTenantCount = Tenant::where('subscription_status', 'active')
            ->orWhere('status', 'active')
            ->count();

        $trialTenants = Tenant::where('subscription_status', 'trial')->count();

        $churnedThisMonth = Tenant::where('subscription_status', 'cancelled')
            ->where('updated_at', '>=', now()->startOfMonth())
            ->count();

        $churnRate = $activeTenantCount > 0
            ? round($churnedThisMonth / $activeTenantCount * 100, 1)
            : 0;

        return [
            Stat::make('MRR', 'EGP ' . number_format($mrr / 100))
                ->description('Monthly Recurring Revenue')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success')
                ->chart([85, 90, 95, 100, 108, 115, $mrr / 100 / 1000]),

            Stat::make('Active Clinics', $activeTenantCount)
                ->description("+{$trialTenants} on trial")
                ->descriptionIcon('heroicon-o-building-office-2')
                ->color('success'),

            Stat::make('Churn Rate', "{$churnRate}%")
                ->description("{$churnedThisMonth} cancelled this month")
                ->descriptionIcon('heroicon-o-arrow-trending-down')
                ->color($churnRate > 5 ? 'danger' : 'success'),

            Stat::make('ARR', 'EGP ' . number_format(($mrr * 12) / 100))
                ->description('Annual Recurring Revenue')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('info'),
        ];
    }
}
