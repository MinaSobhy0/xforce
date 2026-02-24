<?php

namespace Modules\Booking\Filament\Widgets;

use App\Services\BranchContext;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;
use Modules\Booking\Services\ReceptionService;

class ReceptionStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '15s';

    protected int|string|array $columnSpan = 'full';

    public ?string $selectedDate = null;

    public function mount(?string $selectedDate = null): void
    {
        $this->selectedDate = $selectedDate ?? today()->format('Y-m-d');
    }

    #[On('dateChanged')]
    public function handleDateChange(string $date): void
    {
        $this->selectedDate = $date;
    }

    protected function getStats(): array
    {
        $receptionService = app(ReceptionService::class);
        $branchId = BranchContext::currentId();
        $date = Carbon::parse($this->selectedDate);

        $stats = $receptionService->getReceptionStats($branchId, $date);
        $avgWaitTime = $receptionService->getAverageWaitTime($branchId, $date);

        return [
            Stat::make(__('booking::reception.stats.total'), $stats['total'])
                ->description(__('booking::reception.stats.total_desc'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('gray'),

            Stat::make(__('booking::reception.stats.waiting'), $stats['waiting'])
                ->description(__('booking::reception.stats.waiting_desc'))
                ->descriptionIcon('heroicon-m-clock')
                ->color($stats['waiting'] > 5 ? 'danger' : ($stats['waiting'] > 2 ? 'warning' : 'info')),

            Stat::make(__('booking::reception.stats.in_rooms'), $stats['in_rooms'])
                ->description(__('booking::reception.stats.in_rooms_desc'))
                ->descriptionIcon('heroicon-m-building-office')
                ->color('warning'),

            Stat::make(__('booking::reception.stats.in_progress'), $stats['in_progress'])
                ->description(__('booking::reception.stats.in_progress_desc'))
                ->descriptionIcon('heroicon-m-play')
                ->color('primary'),

            Stat::make(__('booking::reception.stats.completed'), $stats['completed'])
                ->description(__('booking::reception.stats.completed_desc'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make(__('booking::reception.stats.no_shows'), $stats['no_shows'])
                ->description(__('booking::reception.stats.no_shows_desc'))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($stats['no_shows'] > 0 ? 'danger' : 'gray'),
        ];
    }
}
