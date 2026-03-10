<?php

namespace Modules\Booking\Filament\Widgets;

use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;
use Modules\Booking\Models\Appointment;

class DoctorDashboardStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';

    #[Reactive]
    public ?string $selectedPractitionerId = null;

    #[Reactive]
    public ?string $selectedDate = null;

    protected function getStats(): array
    {
        // Use selected practitioner if provided, otherwise current user
        $practitionerId = $this->selectedPractitionerId ?? auth()->id();
        $date = $this->selectedDate ? Carbon::parse($this->selectedDate) : today();

        $appointments = Appointment::query()
            ->forDate($date)
            ->forPractitioner($practitionerId)
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
