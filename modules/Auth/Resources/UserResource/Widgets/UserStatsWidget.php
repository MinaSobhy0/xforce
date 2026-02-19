<?php

namespace Modules\Auth\Resources\UserResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Auth\Models\User;

class UserStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $verifiedUsers = User::whereNotNull('email_verified_at')->count();
        $twoFactorUsers = User::where('two_factor_enabled', true)->count();
        $recentLogins = User::where('last_login_at', '>=', now()->subDays(7))->count();

        $activePercentage = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) : 0;
        $verifiedPercentage = $totalUsers > 0 ? round(($verifiedUsers / $totalUsers) * 100, 1) : 0;
        $twoFactorPercentage = $totalUsers > 0 ? round(($twoFactorUsers / $totalUsers) * 100, 1) : 0;

        return [
            Stat::make(__('Total Users'), $totalUsers)
                ->description(__('All registered users'))
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make(__('Active Users'), $activeUsers)
                ->description($activePercentage . '% ' . __('of total'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart([7, 12, 18, 14, 21, 28, $activeUsers]),

            Stat::make(__('Email Verified'), $verifiedUsers)
                ->description($verifiedPercentage . '% ' . __('verified'))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('info'),

            Stat::make(__('2FA Enabled'), $twoFactorUsers)
                ->description($twoFactorPercentage . '% ' . __('secured'))
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('warning'),

            Stat::make(__('Recent Logins'), $recentLogins)
                ->description(__('Past 7 days'))
                ->descriptionIcon('heroicon-m-clock')
                ->color('success'),
        ];
    }
}