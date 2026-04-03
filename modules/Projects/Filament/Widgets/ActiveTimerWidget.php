<?php

namespace Modules\Projects\Filament\Widgets;

use Filament\Widgets\Widget;
use Modules\Projects\Models\ProjectTimeEntry;
use Modules\Projects\Services\TimeTrackingService;

class ActiveTimerWidget extends Widget
{
    protected static string $view = 'projects::filament.widgets.active-timer';

    protected static ?int $sort = 0;

    protected int | string | array $columnSpan = 'full';

    public ?ProjectTimeEntry $activeTimer = null;

    public function mount(): void
    {
        $this->loadActiveTimer();
    }

    public function loadActiveTimer(): void
    {
        $service = app(TimeTrackingService::class);
        $this->activeTimer = $service->getActiveTimer(auth()->id());
    }

    public function stopTimer(): void
    {
        $service = app(TimeTrackingService::class);
        $service->stopActiveTimer(auth()->id());
        $this->loadActiveTimer();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('projects::projects.messages.timer_stopped'),
        ]);
    }

    public function getTimerDuration(): string
    {
        if (!$this->activeTimer || !$this->activeTimer->timer_started_at) {
            return '00:00:00';
        }

        $seconds = now()->diffInSeconds($this->activeTimer->timer_started_at);
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    public static function canView(): bool
    {
        return config('projects.enable_time_tracking', true);
    }
}
