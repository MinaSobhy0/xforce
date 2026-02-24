<?php

namespace Modules\Auth\Filament\Resources\UserResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Auth\Models\User;
use Modules\Auth\Models\UserStatus;

class UserStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalUsers = User::count();
        $activeUsers = User::where('status', UserStatus::ACTIVE)->count();
        $verifiedUsers = User::whereNotNull('email_verified_at')->count();
        $twoFactorUsers = User::where('two_factor_enabled', true)->count();
        $recentLogins = User::where('last_login_at', '>=', now()->subDays(7))->count();

        $activePercentage = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) : 0;
        $verifiedPercentage = $totalUsers > 0 ? round(($verifiedUsers / $totalUsers) * 100, 1) : 0;
        $twoFactorPercentage = $totalUsers > 0 ? round(($twoFactorUsers / $totalUsers) * 100, 1) : 0;

        return [
            Stat::make(__('auth::auth.stats.total_users'), $totalUsers)
                ->description(__('auth::auth.stats.all_registered'))
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make(__('auth::auth.stats.active_users'), $activeUsers)
                ->description($activePercentage . '% ' . __('auth::auth.stats.of_total'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart([7, 12, 18, 14, 21, 28, $activeUsers]),

            Stat::make(__('auth::auth.stats.email_verified'), $verifiedUsers)
                ->description($verifiedPercentage . '% ' . __('auth::auth.stats.verified'))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('info'),

            Stat::make(__('auth::auth.stats.2fa_enabled'), $twoFactorUsers)
                ->description($twoFactorPercentage . '% ' . __('auth::auth.stats.secured'))
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('warning'),

            Stat::make(__('auth::auth.stats.recent_logins'), $recentLogins)
                ->description(__('auth::auth.stats.past_days', ['days' => 7]))
                ->descriptionIcon('heroicon-m-clock')
                ->color('success'),
        ];
    }
}