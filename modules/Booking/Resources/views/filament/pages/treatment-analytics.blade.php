<x-filament-panels::page>
    {{-- Filters --}}
    <div class="mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Date Range --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ __('booking::analytics.filters.date_range') }}
                    </label>
                    <select wire:model.live="dateRange" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        @foreach($this->getDateRangeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Service Filter --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ __('booking::analytics.filters.service') }}
                    </label>
                    <select wire:model.live="serviceId" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">{{ __('booking::analytics.filters.all_services') }}</option>
                        @foreach($this->getAvailableServices() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Refresh Button --}}
                <div class="flex items-end">
                    <button wire:click="loadAnalytics" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                        <x-heroicon-m-arrow-path class="w-5 h-5 inline-block mr-1" />
                        {{ __('booking::analytics.actions.refresh') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Total Sessions --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <x-heroicon-o-calendar-days class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::analytics.metrics.total_sessions') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($sessionMetrics['total_sessions'] ?? 0) }}</p>
                </div>
            </div>
        </div>

        {{-- Completion Rate --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                    <x-heroicon-o-check-circle class="w-6 h-6 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::analytics.metrics.completion_rate') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->formatPercentage($sessionMetrics['completion_rate'] ?? 0) }}</p>
                </div>
            </div>
        </div>

        {{-- Avg Duration --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                    <x-heroicon-o-clock class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::analytics.metrics.avg_duration') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $sessionMetrics['duration']['avg'] ?? 0 }} {{ __('booking::analytics.metrics.minutes') }}</p>
                </div>
            </div>
        </div>

        {{-- Avg Pain Level --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-3 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
                    <x-heroicon-o-heart class="w-6 h-6 text-orange-600 dark:text-orange-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::analytics.metrics.avg_pain_level') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $sessionMetrics['pain_level']['avg'] ?? 0 }}/10</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Top Services --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('booking::analytics.sections.top_services') }}
                </h3>
            </div>
            <div class="p-4">
                @if(count($topServices) > 0)
                    <div class="space-y-3">
                        @foreach($topServices as $service)
                            <div class="flex items-center justify-between">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $service['service_name'] }}</p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs text-gray-500">{{ $service['session_count'] }} {{ __('booking::analytics.labels.sessions') }}</span>
                                        <span class="text-xs text-green-600">{{ $this->formatPercentage($service['completion_rate']) }}</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $this->formatCurrency($service['total_revenue']) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">{{ __('booking::analytics.no_data') }}</p>
                @endif
            </div>
        </div>

        {{-- Practitioner Performance --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('booking::analytics.sections.practitioner_performance') }}
                </h3>
            </div>
            <div class="p-4">
                @if(count($practitionerPerformance) > 0)
                    <div class="space-y-3">
                        @foreach($practitionerPerformance as $practitioner)
                            <div class="flex items-center justify-between">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $practitioner['practitioner_name'] }}</p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs text-gray-500">{{ $practitioner['total_sessions'] }} {{ __('booking::analytics.labels.sessions') }}</span>
                                        <span class="text-xs text-green-600">{{ $this->formatPercentage($practitioner['completion_rate']) }}</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $this->formatCurrency($practitioner['total_revenue']) }}</p>
                                    <p class="text-xs text-gray-500">{{ $practitioner['avg_duration'] }} min avg</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">{{ __('booking::analytics.no_data') }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Equipment Utilization --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('booking::analytics.sections.equipment_utilization') }}
                </h3>
            </div>
            <div class="p-4 overflow-x-auto">
                @if(count($equipmentUtilization) > 0)
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="pb-2">{{ __('booking::analytics.equipment.name') }}</th>
                                <th class="pb-2 text-right">{{ __('booking::analytics.equipment.sessions') }}</th>
                                <th class="pb-2 text-right">{{ __('booking::analytics.equipment.total_shots') }}</th>
                                <th class="pb-2 text-right">{{ __('booking::analytics.equipment.avg_shots') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($equipmentUtilization as $equipment)
                                <tr>
                                    <td class="py-2">
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $equipment['name'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $equipment['code'] }}</p>
                                    </td>
                                    <td class="py-2 text-right text-gray-900 dark:text-white">{{ $equipment['session_count'] }}</td>
                                    <td class="py-2 text-right text-gray-900 dark:text-white">{{ number_format($equipment['total_shots']) }}</td>
                                    <td class="py-2 text-right text-gray-900 dark:text-white">{{ $equipment['avg_shots_per_session'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">{{ __('booking::analytics.no_data') }}</p>
                @endif
            </div>
        </div>

        {{-- Consumables Usage --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('booking::analytics.sections.consumables_usage') }}
                </h3>
            </div>
            <div class="p-4 overflow-x-auto">
                @if(count($consumablesUsage) > 0)
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="pb-2">{{ __('booking::analytics.consumables.product') }}</th>
                                <th class="pb-2 text-right">{{ __('booking::analytics.consumables.used') }}</th>
                                <th class="pb-2 text-right">{{ __('booking::analytics.consumables.total_cost') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach(array_slice($consumablesUsage, 0, 10) as $item)
                                <tr>
                                    <td class="py-2">
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $item['product_name'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $item['usage_count'] }} {{ __('booking::analytics.consumables.times') }}</p>
                                    </td>
                                    <td class="py-2 text-right text-gray-900 dark:text-white">{{ $item['total_quantity'] }} {{ $item['unit'] }}</td>
                                    <td class="py-2 text-right text-gray-900 dark:text-white">{{ $this->formatCurrency($item['total_cost']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">{{ __('booking::analytics.no_data') }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Skin Reactions Distribution --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ __('booking::analytics.sections.skin_reactions') }}
            </h3>
        </div>
        <div class="p-4">
            @if(!empty($sessionMetrics['skin_reactions']))
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    @foreach($sessionMetrics['skin_reactions'] as $reaction => $count)
                        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $count }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $this->getSkinReactionLabel($reaction) }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">{{ __('booking::analytics.no_data') }}</p>
            @endif
        </div>
    </div>

    {{-- Parameter Statistics (only shown when service is selected) --}}
    @if($serviceId && count($parameterStats) > 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('booking::analytics.sections.parameter_statistics') }}
                </h3>
            </div>
            <div class="p-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="pb-2">{{ __('booking::analytics.parameters.name') }}</th>
                            <th class="pb-2 text-right">{{ __('booking::analytics.parameters.count') }}</th>
                            <th class="pb-2 text-right">{{ __('booking::analytics.parameters.min') }}</th>
                            <th class="pb-2 text-right">{{ __('booking::analytics.parameters.max') }}</th>
                            <th class="pb-2 text-right">{{ __('booking::analytics.parameters.avg') }}</th>
                            <th class="pb-2 text-right">{{ __('booking::analytics.parameters.median') }}</th>
                            <th class="pb-2 text-right">{{ __('booking::analytics.parameters.std_dev') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($parameterStats as $key => $stats)
                            <tr>
                                <td class="py-2 font-medium text-gray-900 dark:text-white">{{ $key }}</td>
                                <td class="py-2 text-right text-gray-900 dark:text-white">{{ $stats['count'] }}</td>
                                <td class="py-2 text-right text-gray-900 dark:text-white">{{ $stats['min'] }}</td>
                                <td class="py-2 text-right text-gray-900 dark:text-white">{{ $stats['max'] }}</td>
                                <td class="py-2 text-right text-gray-900 dark:text-white">{{ $stats['avg'] }}</td>
                                <td class="py-2 text-right text-gray-900 dark:text-white">{{ $stats['median'] }}</td>
                                <td class="py-2 text-right text-gray-500">{{ $stats['std_dev'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Products Usage --}}
    @if(count($productsUsage) > 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('booking::analytics.sections.products_usage') }}
                </h3>
            </div>
            <div class="p-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="pb-2">{{ __('booking::analytics.products.name') }}</th>
                            <th class="pb-2">{{ __('booking::analytics.products.type') }}</th>
                            <th class="pb-2 text-right">{{ __('booking::analytics.products.quantity') }}</th>
                            <th class="pb-2 text-right">{{ __('booking::analytics.products.revenue') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($productsUsage as $item)
                            <tr>
                                <td class="py-2">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $item['product_name'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $item['product_sku'] }}</p>
                                </td>
                                <td class="py-2">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $item['usage_type'] === 'sold' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">
                                        {{ $item['usage_type'] === 'sold' ? __('booking::analytics.products.sold') : __('booking::analytics.products.applied') }}
                                    </span>
                                </td>
                                <td class="py-2 text-right text-gray-900 dark:text-white">{{ $item['total_quantity'] }}</td>
                                <td class="py-2 text-right text-gray-900 dark:text-white">{{ $this->formatCurrency($item['total_revenue']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
