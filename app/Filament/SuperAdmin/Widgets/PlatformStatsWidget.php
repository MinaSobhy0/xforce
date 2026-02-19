<?php

namespace App\Filament\SuperAdmin\Widgets;

use Modules\Core\Models\Tenant;
use Modules\Auth\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\PlatformInvoice;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

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

        $trialsExpiringSoon = Tenant::where('subscription_status', 'trial')
            ->where('trial_ends_at', '<=', now()->addDays(3))
            ->where('trial_ends_at', '>', now())
            ->count();

        $churnedThisMonth = Tenant::where('subscription_status', 'cancelled')
            ->where('updated_at', '>=', now()->startOfMonth())
            ->count();

        $churnRate = $activeTenantCount > 0
            ? round($churnedThisMonth / $activeTenantCount * 100, 1)
            : 0;

        // Total users across all tenants (platform users without tenant_id)
        $totalUsers = User::whereNull('tenant_id')->count();
        $newUsersThisMonth = User::whereNull('tenant_id')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        // Overdue payments
        $overdueInvoices = PlatformInvoice::where('status', 'overdue')->get();
        $overdueCount = $overdueInvoices->count();
        $overdueAmount = $overdueInvoices->sum('amount') / 100;

        // Storage used (estimate based on tenant count * avg storage)
        $totalStorageMB = Tenant::sum('max_storage_mb') ?: 500 * $activeTenantCount;
        $usedStoragePercent = $totalStorageMB > 0 ? min(round(($activeTenantCount * 50) / $totalStorageMB * 100), 100) : 0;

        return [
            // Row 1
            Stat::make('MRR', 'EGP ' . number_format($mrr / 100))
                ->description('Monthly Recurring Revenue')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success')
                ->chart([85, 90, 95, 100, 108, 115, $mrr / 100 / 1000]),

            Stat::make('Active Clinics', $activeTenantCount)
                ->description("+{$trialTenants} on trial")
                ->descriptionIcon('heroicon-o-building-office-2')
                ->color('success'),

            Stat::make('Total Users', $totalUsers)
                ->description("+{$newUsersThisMonth} this month")
                ->descriptionIcon('heroicon-o-users')
                ->color('info'),

            Stat::make('Churn Rate', "{$churnRate}%")
                ->description("{$churnedThisMonth} cancelled this month")
                ->descriptionIcon('heroicon-o-arrow-trending-down')
                ->color($churnRate > 5 ? 'danger' : 'success'),

            // Row 2
            Stat::make('ARR', 'EGP ' . number_format(($mrr * 12) / 100))
                ->description('Annual Recurring Revenue')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('info'),

            Stat::make('Active Trials', $trialTenants)
                ->description($trialsExpiringSoon > 0 ? "{$trialsExpiringSoon} expiring in 3 days" : 'All healthy')
                ->descriptionIcon('heroicon-o-clock')
                ->color($trialsExpiringSoon > 0 ? 'warning' : 'success'),

            Stat::make('Overdue Payments', $overdueCount)
                ->description($overdueCount > 0 ? 'EGP ' . number_format($overdueAmount) . ' total' : 'None')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($overdueCount > 0 ? 'danger' : 'success'),

            Stat::make('Storage Used', "{$usedStoragePercent}%")
                ->description('Platform storage')
                ->descriptionIcon('heroicon-o-server')
                ->color($usedStoragePercent > 80 ? 'warning' : 'success'),
        ];
    }
}
