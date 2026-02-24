<?php

namespace Modules\Patients\Filament\Resources\PatientResource\Widgets;

use Modules\Patients\Models\Patient;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PatientStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalPatients = Patient::count();
        $newThisMonth = Patient::newThisMonth()->count();
        $activePatients = Patient::where('status', 'active')->count();
        $recentVisitors = Patient::recentVisitors(30)->count();

        // Calculate growth
        $lastMonthNew = Patient::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        $growth = $lastMonthNew > 0
            ? round((($newThisMonth - $lastMonthNew) / $lastMonthNew) * 100, 1)
            : ($newThisMonth > 0 ? 100 : 0);

        $activePercentage = round(($activePatients / max($totalPatients, 1)) * 100, 1);

        return [
            Stat::make(__('patients::patients.labels.patients'), number_format($totalPatients))
                ->description(__('patients::patients.stats.total_registered'))
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),

            Stat::make(__('patients::patients.filters.new_this_month'), number_format($newThisMonth))
                ->description(__('patients::patients.stats.growth_from_last_month', ['growth' => ($growth >= 0 ? '+' : '') . $growth]))
                ->descriptionIcon($growth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($growth >= 0 ? 'success' : 'danger'),

            Stat::make(__('patients::patients.filters.active'), number_format($activePatients))
                ->description(__('patients::patients.stats.of_total', ['percent' => $activePercentage]))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make(__('patients::patients.stats.recent_visitors'), number_format($recentVisitors))
                ->description(__('patients::patients.stats.visited_last_days', ['days' => 30]))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),
        ];
    }
}
