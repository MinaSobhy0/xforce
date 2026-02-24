<?php

namespace Modules\Booking\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Booking\Models\Appointment;

class DoctorDashboardStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $user = auth()->user();
        $today = today();

        $appointments = Appointment::query()
            ->forDate($today)
            ->forPractitioner($user->id)
            ->get();

        $waiting = $appointments->where('status', Appointment::STATUS_CHECKED_IN)->count();
        $inProgress = $appointments->where('status', Appointment::STATUS_IN_PROGRESS)->count();
        $completed = $appointments->where('status', Appointment::STATUS_COMPLETED)->count();
        $upcoming = $appointments->whereIn('status', [
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_CONFIRMED,
        ])->count();

        return [
            Stat::make(__('booking::dashboard.stats.waiting'), $waiting)
                ->description(__('booking::dashboard.stats.checked_in_desc'))
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make(__('booking::dashboard.stats.in_progress'), $inProgress)
                ->description(__('booking::dashboard.stats.in_progress_desc'))
                ->descriptionIcon('heroicon-m-play')
                ->color('info'),

            Stat::make(__('booking::dashboard.stats.completed'), $completed)
                ->description(__('booking::dashboard.stats.completed_desc'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make(__('booking::dashboard.stats.upcoming'), $upcoming)
                ->description(__('booking::dashboard.stats.upcoming_desc'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('gray'),
        ];
    }
}
