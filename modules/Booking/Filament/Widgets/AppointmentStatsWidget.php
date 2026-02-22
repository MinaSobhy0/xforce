<?php

namespace Modules\Booking\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Booking\Models\Appointment;
use Illuminate\Support\Facades\DB;

class AppointmentStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $today = Appointment::whereDate('date', today());

        $todayTotal = (clone $today)->count();
        $todayCompleted = (clone $today)->where('status', Appointment::STATUS_COMPLETED)->count();
        $todayInProgress = (clone $today)->where('status', Appointment::STATUS_IN_PROGRESS)->count();
        $todayCheckedIn = (clone $today)->where('status', Appointment::STATUS_CHECKED_IN)->count();
        $todayScheduled = (clone $today)->whereIn('status', [
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_CONFIRMED,
        ])->count();
        $todayCancelled = (clone $today)->where('status', Appointment::STATUS_CANCELLED)->count();
        $todayNoShow = (clone $today)->where('status', Appointment::STATUS_NO_SHOW)->count();

        // This week's trend
        $thisWeek = Appointment::whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $lastWeek = Appointment::whereBetween('date', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])->count();
        $weekTrend = $lastWeek > 0 ? round(($thisWeek - $lastWeek) / $lastWeek * 100, 1) : 0;

        // Revenue today
        $revenueToday = (clone $today)
            ->where('status', Appointment::STATUS_COMPLETED)
            ->sum('price_minor');

        return [
            Stat::make(
                __('booking::appointments.widgets.todays_appointments'),
                $todayTotal
            )
                ->description(
                    __('booking::appointments.widgets.completed') . ": {$todayCompleted} | " .
                    __('booking::appointments.widgets.remaining') . ": {$todayScheduled}"
                )
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary')
                ->chart($this->getWeeklyData()),

            Stat::make(
                __('booking::appointments.widgets.in_clinic'),
                $todayCheckedIn + $todayInProgress
            )
                ->description(
                    __('booking::appointments.widgets.checked_in') . ": {$todayCheckedIn} | " .
                    __('booking::appointments.widgets.in_progress') . ": {$todayInProgress}"
                )
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),

            Stat::make(
                __('booking::appointments.widgets.cancellations_no_shows'),
                $todayCancelled + $todayNoShow
            )
                ->description(
                    __('booking::appointments.widgets.cancelled') . ": {$todayCancelled} | " .
                    __('booking::appointments.widgets.no_show') . ": {$todayNoShow}"
                )
                ->descriptionIcon('heroicon-m-x-circle')
                ->color(($todayCancelled + $todayNoShow) > 0 ? 'danger' : 'gray'),

            Stat::make(
                __('booking::appointments.widgets.revenue_today'),
                $this->formatCurrency($revenueToday)
            )
                ->description(
                    ($weekTrend >= 0 ? '+' : '') . $weekTrend . '% ' . __('booking::appointments.widgets.vs_last_week')
                )
                ->descriptionIcon($weekTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($weekTrend >= 0 ? 'success' : 'danger'),
        ];
    }

    protected function getWeeklyData(): array
    {
        return Appointment::whereBetween('date', [now()->subDays(6), now()])
            ->groupBy('date')
            ->orderBy('date')
            ->pluck(DB::raw('COUNT(*)'))
            ->toArray();
    }

    protected function formatCurrency(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2) . ' EGP';
    }
}
