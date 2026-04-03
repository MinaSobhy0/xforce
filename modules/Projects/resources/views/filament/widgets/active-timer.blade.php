<x-filament-widgets::widget>
    @if($activeTimer)
        <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                        </span>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ __('projects::projects.timer_running') }}
                        </span>
                    </div>

                    <div class="text-lg font-mono font-bold text-gray-900 dark:text-white"
                         x-data="{
                            startTime: new Date('{{ $activeTimer->timer_started_at->toIso8601String() }}'),
                            duration: '{{ $this->getTimerDuration() }}'
                         }"
                         x-init="setInterval(() => {
                            const diff = Math.floor((new Date() - startTime) / 1000);
                            const hours = Math.floor(diff / 3600).toString().padStart(2, '0');
                            const minutes = Math.floor((diff % 3600) / 60).toString().padStart(2, '0');
                            const seconds = (diff % 60).toString().padStart(2, '0');
                            duration = `${hours}:${minutes}:${seconds}`;
                         }, 1000)"
                         x-text="duration"
                    >
                        {{ $this->getTimerDuration() }}
                    </div>

                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        <span class="font-medium">{{ $activeTimer->project->display_name }}</span>
                        @if($activeTimer->task)
                            <span class="mx-1">/</span>
                            <span>{{ $activeTimer->task->display_name }}</span>
                        @endif
                    </div>
                </div>

                <x-filament::button
                    wire:click="stopTimer"
                    color="danger"
                    size="sm"
                    icon="heroicon-o-stop"
                >
                    {{ __('projects::projects.actions.stop_timer') }}
                </x-filament::button>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
