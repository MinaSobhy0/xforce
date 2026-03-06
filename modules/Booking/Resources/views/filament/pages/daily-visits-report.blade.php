<x-filament-panels::page>
    {{-- Date Navigation --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-2">
            <x-filament::icon-button
                icon="heroicon-o-chevron-left"
                wire:click="previousDay"
                label="Previous Day"
                color="gray"
            />

            <div class="w-48">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="date"
                        wire:model.live="selectedDate"
                        class="text-center"
                    />
                </x-filament::input.wrapper>
            </div>

            <x-filament::icon-button
                icon="heroicon-o-chevron-right"
                wire:click="nextDay"
                label="Next Day"
                color="gray"
            />

            @unless($this->isToday())
                <x-filament::button
                    wire:click="goToToday"
                    color="primary"
                    size="sm"
                >
                    {{ __('booking::reception.filters.today') }}
                </x-filament::button>
            @endunless
        </div>

        @if($this->isToday())
            <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-400">
                <x-heroicon-s-clock class="w-3 h-3" />
                {{ __('booking::reception.filters.viewing_today') }}
            </span>
        @endif
    </div>

    @php $stats = $this->getSummaryStats(); @endphp

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
        {{-- Total Visits --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                    <x-heroicon-o-ticket class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total_visits'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('booking::reports.daily_visits.stats.total_visits') }}</div>
                </div>
            </div>
            <div class="mt-2 flex gap-2 text-xs">
                <span class="text-green-600">{{ $stats['completed_visits'] }} {{ __('booking::reports.daily_visits.stats.completed') }}</span>
                <span class="text-amber-600">{{ $stats['open_visits'] }} {{ __('booking::reports.daily_visits.stats.open') }}</span>
            </div>
        </div>

        {{-- New Sessions --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
                    <x-heroicon-o-plus-circle class="w-6 h-6 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['new_sessions'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('booking::reports.daily_visits.stats.new_sessions') }}</div>
                </div>
            </div>
        </div>

        {{-- Package Sessions --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <x-heroicon-o-gift class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['package_sessions'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('booking::reports.daily_visits.stats.package_sessions') }}</div>
                </div>
            </div>
        </div>

        {{-- Treatment Plan Continuations --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                    <x-heroicon-o-arrow-path class="w-6 h-6 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['treatment_plan_continuations'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('booking::reports.daily_visits.stats.continuations') }}</div>
                </div>
            </div>
            @if($stats['treatment_plan_first'] > 0)
                <div class="mt-2 text-xs text-gray-500">
                    +{{ $stats['treatment_plan_first'] }} {{ __('booking::reports.daily_visits.stats.first_sessions') }}
                </div>
            @endif
        </div>

        {{-- Total Revenue --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg">
                    <x-heroicon-o-banknotes class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_revenue'] / 100, 0) }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('booking::reports.daily_visits.stats.revenue') }}</div>
                </div>
            </div>
            <div class="mt-2 flex gap-2 text-xs">
                <span class="text-blue-600">{{ number_format($stats['services_revenue'] / 100, 0) }} {{ __('booking::reports.daily_visits.stats.services') }}</span>
                <span class="text-purple-600">{{ number_format($stats['products_revenue'] / 100, 0) }} {{ __('booking::reports.daily_visits.stats.products') }}</span>
            </div>
        </div>

        {{-- Average Duration --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-cyan-100 dark:bg-cyan-900/30 rounded-lg">
                    <x-heroicon-o-clock class="w-6 h-6 text-cyan-600 dark:text-cyan-400" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['avg_duration_minutes'] }}<span class="text-sm font-normal">min</span></div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('booking::reports.daily_visits.stats.avg_duration') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Session Types Breakdown --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-6">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">{{ __('booking::reports.daily_visits.breakdown_title') }}</h3>

        @php
            $totalSessions = $stats['new_sessions'] + $stats['package_sessions'] + $stats['treatment_plan_continuations'] + $stats['treatment_plan_first'];
            $newPercent = $totalSessions > 0 ? round($stats['new_sessions'] / $totalSessions * 100) : 0;
            $packagePercent = $totalSessions > 0 ? round($stats['package_sessions'] / $totalSessions * 100) : 0;
            $continuationPercent = $totalSessions > 0 ? round($stats['treatment_plan_continuations'] / $totalSessions * 100) : 0;
            $firstPercent = $totalSessions > 0 ? round($stats['treatment_plan_first'] / $totalSessions * 100) : 0;
        @endphp

        <div class="space-y-3">
            {{-- New Sessions --}}
            <div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-600 dark:text-gray-400">{{ __('booking::reports.daily_visits.types.new') }}</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $stats['new_sessions'] }} ({{ $newPercent }}%)</span>
                </div>
                <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div class="h-full bg-green-500 rounded-full" style="width: {{ $newPercent }}%"></div>
                </div>
            </div>

            {{-- Package Sessions --}}
            <div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-600 dark:text-gray-400">{{ __('booking::reports.daily_visits.types.package') }}</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $stats['package_sessions'] }} ({{ $packagePercent }}%)</span>
                </div>
                <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div class="h-full bg-blue-500 rounded-full" style="width: {{ $packagePercent }}%"></div>
                </div>
            </div>

            {{-- Treatment Plan Continuations --}}
            <div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-600 dark:text-gray-400">{{ __('booking::reports.daily_visits.types.continuation') }}</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $stats['treatment_plan_continuations'] }} ({{ $continuationPercent }}%)</span>
                </div>
                <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div class="h-full bg-amber-500 rounded-full" style="width: {{ $continuationPercent }}%"></div>
                </div>
            </div>

            {{-- First Treatment Plan Sessions --}}
            <div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-600 dark:text-gray-400">{{ __('booking::reports.daily_visits.types.plan_first') }}</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $stats['treatment_plan_first'] }} ({{ $firstPercent }}%)</span>
                </div>
                <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div class="h-full bg-indigo-500 rounded-full" style="width: {{ $firstPercent }}%"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Visits Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
