<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Date Filter --}}
        <form wire:submit.prevent>
            {{ $this->form }}
        </form>

        {{-- Patient Summary Cards (First Row - 3 boxes) --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- New Patients --}}
            <x-filament::section class="!p-4">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-success-500/10 p-2 shrink-0">
                        <x-heroicon-o-user-plus class="h-5 w-5 text-success-600 dark:text-success-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reporting::reporting.new_patients') }}</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white mt-1">{{ $this->stats['new_patients_count']['value'] ?? 0 }}</p>
                        <p class="text-sm font-semibold text-success-600">{{ format_money(collect($this->newPatients)->sum('total')) }}</p>
                    </div>
                </div>
            </x-filament::section>

            {{-- Returning Patients --}}
            <x-filament::section class="!p-4">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-info-500/10 p-2 shrink-0">
                        <x-heroicon-o-users class="h-5 w-5 text-info-600 dark:text-info-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reporting::reporting.returning_patients') }}</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white mt-1">{{ $this->stats['returning_patients_count']['value'] ?? 0 }}</p>
                        <p class="text-sm font-semibold text-info-600">{{ format_money(collect($this->returningPatients)->sum('total')) }}</p>
                    </div>
                </div>
            </x-filament::section>

            {{-- Follow-up Patients --}}
            <x-filament::section class="!p-4">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-warning-500/10 p-2 shrink-0">
                        <x-heroicon-o-arrow-path class="h-5 w-5 text-warning-600 dark:text-warning-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reporting::reporting.followup_patients') }}</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white mt-1">{{ $this->stats['followup_patients_count']['value'] ?? 0 }}</p>
                        <p class="text-sm font-semibold text-warning-600">{{ format_money(collect($this->followUpPatients)->sum('total')) }}</p>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Main Stats Cards (Second Row - 4 boxes) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Total Revenue --}}
            <x-filament::section class="!p-4">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-success-500/10 p-2 shrink-0">
                        <x-heroicon-o-currency-dollar class="h-5 w-5 text-success-600 dark:text-success-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reporting::reporting.total_revenue') }}</p>
                        <p class="text-xl font-semibold text-gray-900 dark:text-white truncate mt-1">{{ $this->stats['total_revenue']['value'] ?? '0' }}</p>
                    </div>
                </div>
            </x-filament::section>

            {{-- Total Sold Products --}}
            <x-filament::section class="!p-4">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-info-500/10 p-2 shrink-0">
                        <x-heroicon-o-shopping-bag class="h-5 w-5 text-info-600 dark:text-info-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reporting::reporting.total_sold_products') }}</p>
                        <p class="text-xl font-semibold text-gray-900 dark:text-white truncate mt-1">{{ $this->stats['total_products']['value'] ?? '0' }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $this->stats['total_products']['description'] ?? '' }}</p>
                    </div>
                </div>
            </x-filament::section>

            {{-- Highest Sales --}}
            <x-filament::section class="!p-4">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-warning-500/10 p-2 shrink-0">
                        <x-heroicon-o-arrow-trending-up class="h-5 w-5 text-warning-600 dark:text-warning-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reporting::reporting.highest_sales') }}</p>
                        <p class="text-base font-semibold text-gray-900 dark:text-white truncate mt-1">{{ $this->stats['highest_sales']['value'] ?? '-' }}</p>
                        @if(!empty($this->stats['highest_sales']['description']))
                            <p class="text-xs font-semibold text-warning-600 mt-0.5">{{ $this->stats['highest_sales']['description'] }}</p>
                        @endif
                    </div>
                </div>
            </x-filament::section>

            {{-- Most Booked Service --}}
            <x-filament::section class="!p-4">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-primary-500/10 p-2 shrink-0">
                        <x-heroicon-o-calendar class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reporting::reporting.highest_services') }}</p>
                        <p class="text-base font-semibold text-gray-900 dark:text-white truncate mt-1">{{ $this->stats['most_booked']['value'] ?? '-' }}</p>
                        @if(!empty($this->stats['most_booked']['description']))
                            <p class="text-xs font-semibold text-primary-600 mt-0.5">{{ $this->stats['most_booked']['description'] }}</p>
                        @endif
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Patient Details Lists --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- New Patients List --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-user-plus class="h-5 w-5 text-success-500" />
                        {{ __('reporting::reporting.new_patients') }}
                    </div>
                </x-slot>

                <div class="max-h-[350px] overflow-y-auto">
                    @forelse($this->newPatients as $categoryName => $category)
                        <div class="mb-4 last:mb-0">
                            <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-800 sticky top-0 bg-white dark:bg-gray-900">
                                <span class="font-medium text-sm text-gray-700 dark:text-gray-300">{{ $categoryName }}</span>
                                <span class="text-sm font-semibold text-success-600">{{ $category['total_formatted'] }}</span>
                            </div>
                            @foreach($category['patients'] as $patient)
                                <div class="flex justify-between items-start py-2 text-sm gap-2">
                                    <div class="min-w-0 flex-1">
                                        <span class="font-medium text-gray-900 dark:text-white block truncate">{{ $patient['patient_name'] }}</span>
                                        <span class="text-xs text-gray-500 block truncate">{{ $patient['service_name'] }}</span>
                                        <span class="text-xs text-gray-400 block truncate">{{ __('reporting::reporting.by') }}: {{ $patient['practitioner_name'] }}</span>
                                    </div>
                                    <span class="font-mono text-sm text-success-600 shrink-0">{{ $patient['price_formatted'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="text-center py-6">
                            <x-heroicon-o-user-plus class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                            <p class="text-gray-500 mt-2 text-sm">{{ __('reporting::reporting.no_data') }}</p>
                        </div>
                    @endforelse
                </div>
            </x-filament::section>

            {{-- Returning Patients List --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-users class="h-5 w-5 text-info-500" />
                        {{ __('reporting::reporting.returning_patients') }}
                    </div>
                </x-slot>

                <div class="max-h-[350px] overflow-y-auto">
                    @forelse($this->returningPatients as $categoryName => $category)
                        <div class="mb-4 last:mb-0">
                            <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-800 sticky top-0 bg-white dark:bg-gray-900">
                                <span class="font-medium text-sm text-gray-700 dark:text-gray-300">{{ $categoryName }}</span>
                                <span class="text-sm font-semibold text-info-600">{{ $category['total_formatted'] }}</span>
                            </div>
                            @foreach($category['patients'] as $patient)
                                <div class="flex justify-between items-start py-2 text-sm gap-2">
                                    <div class="min-w-0 flex-1">
                                        <span class="font-medium text-gray-900 dark:text-white block truncate">{{ $patient['patient_name'] }}</span>
                                        <span class="text-xs text-gray-500 block truncate">{{ $patient['service_name'] }}</span>
                                        <span class="text-xs text-gray-400 block truncate">{{ __('reporting::reporting.by') }}: {{ $patient['practitioner_name'] }}</span>
                                    </div>
                                    <span class="font-mono text-sm text-info-600 shrink-0">{{ $patient['price_formatted'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="text-center py-6">
                            <x-heroicon-o-users class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                            <p class="text-gray-500 mt-2 text-sm">{{ __('reporting::reporting.no_data') }}</p>
                        </div>
                    @endforelse
                </div>
            </x-filament::section>

            {{-- Follow-up Patients List --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-arrow-path class="h-5 w-5 text-warning-500" />
                        {{ __('reporting::reporting.followup_patients') }}
                    </div>
                </x-slot>

                <div class="max-h-[350px] overflow-y-auto">
                    @forelse($this->followUpPatients as $categoryName => $category)
                        <div class="mb-4 last:mb-0">
                            <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-800 sticky top-0 bg-white dark:bg-gray-900">
                                <span class="font-medium text-sm text-gray-700 dark:text-gray-300">{{ $categoryName }}</span>
                                <span class="text-sm font-semibold text-warning-600">{{ $category['total_formatted'] }}</span>
                            </div>
                            @foreach($category['patients'] as $patient)
                                <div class="flex justify-between items-start py-2 text-sm gap-2">
                                    <div class="min-w-0 flex-1">
                                        <span class="font-medium text-gray-900 dark:text-white block truncate">{{ $patient['patient_name'] }}</span>
                                        <span class="text-xs text-gray-500 block truncate">{{ $patient['service_name'] }}</span>
                                        <span class="text-xs text-gray-400 block truncate">{{ __('reporting::reporting.by') }}: {{ $patient['practitioner_name'] }}</span>
                                    </div>
                                    <span class="font-mono text-sm text-warning-600 shrink-0">{{ $patient['price_formatted'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="text-center py-6">
                            <x-heroicon-o-arrow-path class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                            <p class="text-gray-500 mt-2 text-sm">{{ __('reporting::reporting.no_data') }}</p>
                        </div>
                    @endforelse
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
