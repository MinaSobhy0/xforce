<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Needs Attention
        </x-slot>

        <div class="space-y-2">
            @forelse ($this->getAlerts() as $alert)
                <div
                    class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm
                        {{ $alert['color'] === 'danger' ? 'bg-danger-50 text-danger-700 dark:bg-danger-900/20 dark:text-danger-400' : '' }}
                        {{ $alert['color'] === 'warning' ? 'bg-warning-50 text-warning-700 dark:bg-warning-900/20 dark:text-warning-400' : '' }}"
                >
                    @if ($alert['icon'] === 'warning')
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                    @elseif ($alert['icon'] === 'clock')
                        <x-heroicon-o-clock class="h-5 w-5" />
                    @elseif ($alert['icon'] === 'danger')
                        <x-heroicon-o-x-circle class="h-5 w-5" />
                    @elseif ($alert['icon'] === 'ticket')
                        <x-heroicon-o-ticket class="h-5 w-5" />
                    @endif
                    <span>{{ $alert['text'] }}</span>
                </div>
            @empty
                <div class="text-sm text-gray-500 dark:text-gray-400 py-4 text-center">
                    All clear - nothing needs attention!
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
