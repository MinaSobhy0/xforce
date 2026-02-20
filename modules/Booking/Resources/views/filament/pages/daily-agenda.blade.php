<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Date Navigation --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <x-filament::button
                    wire:click="today"
                    size="sm"
                    color="gray"
                >
                    {{ __('booking::agenda.today') }}
                </x-filament::button>

                <x-filament::button
                    wire:click="previousDay"
                    size="sm"
                    color="gray"
                    icon="heroicon-o-chevron-left"
                >
                </x-filament::button>

                <x-filament::button
                    wire:click="nextDay"
                    size="sm"
                    color="gray"
                    icon="heroicon-o-chevron-right"
                >
                </x-filament::button>

                <span class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ \Carbon\Carbon::parse($selectedDate)->format('l, F j, Y') }}
                </span>
            </div>

            <div>
                <a href="{{ route('filament.tenant.pages.calendar') }}" class="text-primary-600 hover:text-primary-500">
                    {{ __('booking::agenda.view_calendar') }}
                </a>
            </div>
        </div>

        {{-- Filters --}}
        <x-filament::section>
            {{ $this->form }}
        </x-filament::section>

        {{-- Statistics --}}
        @php $stats = $this->getStatistics(); @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4">
            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total'] }}</div>
                <div class="text-sm text-gray-500">{{ __('booking::agenda.stats.total') }}</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-blue-600">{{ $stats['scheduled'] }}</div>
                <div class="text-sm text-gray-500">{{ __('booking::agenda.stats.scheduled') }}</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-purple-600">{{ $stats['confirmed'] }}</div>
                <div class="text-sm text-gray-500">{{ __('booking::agenda.stats.confirmed') }}</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-amber-600">{{ $stats['checked_in'] }}</div>
                <div class="text-sm text-gray-500">{{ __('booking::agenda.stats.checked_in') }}</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-indigo-600">{{ $stats['in_progress'] }}</div>
                <div class="text-sm text-gray-500">{{ __('booking::agenda.stats.in_progress') }}</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-green-600">{{ $stats['completed'] }}</div>
                <div class="text-sm text-gray-500">{{ __('booking::agenda.stats.completed') }}</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-red-600">{{ $stats['cancelled'] }}</div>
                <div class="text-sm text-gray-500">{{ __('booking::agenda.stats.cancelled') }}</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-gray-600">{{ $stats['no_show'] }}</div>
                <div class="text-sm text-gray-500">{{ __('booking::agenda.stats.no_show') }}</div>
            </x-filament::section>
        </div>

        {{-- Appointments Table --}}
        {{ $this->table }}
    </div>
</x-filament-panels::page>
