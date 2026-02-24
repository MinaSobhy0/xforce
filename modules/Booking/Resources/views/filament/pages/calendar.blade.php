<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Room Calendar Link --}}
        <div class="flex justify-end">
            <x-filament::button
                tag="a"
                href="{{ route('filament.tenant.pages.room-calendar') }}"
                size="sm"
                color="gray"
                icon="heroicon-o-building-office"
            >
                {{ __('booking::calendar.view.rooms') }}
            </x-filament::button>
        </div>

        {{-- Legend --}}
        <div class="flex items-center gap-3 text-xs">
            <div class="flex items-center gap-1">
                <span class="w-3 h-3 rounded bg-blue-500"></span>
                <span class="text-gray-600 dark:text-gray-400">{{ __('booking::room_calendar.legend.scheduled') }}</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-3 h-3 rounded bg-amber-500"></span>
                <span class="text-gray-600 dark:text-gray-400">{{ __('booking::room_calendar.legend.checked_in') }}</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-3 h-3 rounded bg-indigo-500"></span>
                <span class="text-gray-600 dark:text-gray-400">{{ __('booking::room_calendar.legend.in_progress') }}</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-3 h-3 rounded bg-green-500"></span>
                <span class="text-gray-600 dark:text-gray-400">{{ __('booking::room_calendar.legend.completed') }}</span>
            </div>
        </div>
    </div>
</x-filament-panels::page>
