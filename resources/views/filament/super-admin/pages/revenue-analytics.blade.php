<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Period Filter --}}
        <div class="flex justify-end">
            {{ $this->form }}
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            @foreach ($this->getKpis() as $kpi)
                <x-filament::section class="text-center">
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $kpi['value'] }}</div>
                    @if ($kpi['change'])
                        <div class="text-sm {{ $kpi['trend'] === 'up' ? 'text-green-500' : 'text-red-500' }} mt-1">
                            {{ $kpi['change'] }} {{ $kpi['trend'] === 'up' ? '▲' : '▼' }}
                        </div>
                    @endif
                </x-filament::section>
            @endforeach
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            {{-- MRR Breakdown --}}
            <x-filament::section>
                <x-slot name="heading">MRR Breakdown</x-slot>

                <div class="space-y-3">
                    <div class="font-medium text-gray-900 dark:text-white">Plan Revenue</div>
                    @php $breakdown = $this->getMrrBreakdown(); @endphp
                    @foreach ($breakdown as $item)
                        <div class="flex justify-between items-center py-2 border-b dark:border-gray-700">
                            <div>
                                <span class="font-medium">{{ $item['plan'] }}</span>
                                @if ($item['tenants'])
                                    <span class="text-sm text-gray-500">({{ $item['tenants'] }} clinics)</span>
                                @endif
                            </div>
                            <div class="font-mono">EGP {{ number_format($item['revenue']) }}</div>
                        </div>
                    @endforeach

                    <div class="flex justify-between items-center pt-3 font-bold">
                        <span>Total MRR</span>
                        <span class="font-mono">EGP {{ number_format(collect($breakdown)->sum('revenue')) }}</span>
                    </div>
                </div>
            </x-filament::section>

            {{-- Revenue by Plan Chart --}}
            <x-filament::section>
                <x-slot name="heading">Revenue by Plan</x-slot>

                <div class="space-y-4">
                    @foreach ($this->getRevenueByPlan() as $plan)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span>{{ $plan['name'] }} ({{ $plan['count'] }} clinics)</span>
                                <span>EGP {{ number_format($plan['mrr']) }}</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                                <div class="bg-primary-500 h-3 rounded-full"
                                     style="width: {{ $plan['percentage'] }}%"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">{{ $plan['percentage'] }}%</div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            {{-- Churn Analysis --}}
            <x-filament::section>
                <x-slot name="heading">Churn Analysis</x-slot>

                @php $churn = $this->getChurnAnalysis(); @endphp
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-red-600 dark:text-red-400">
                                {{ $churn['churned_this_month'] }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Churned this month</div>
                        </div>
                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-red-600 dark:text-red-400">
                                {{ $churn['churn_rate'] }}%
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Churn rate</div>
                        </div>
                    </div>

                    <div>
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Top Reasons</div>
                        @foreach ($churn['reasons'] as $reason)
                            <div class="flex justify-between text-sm py-1">
                                <span>{{ $reason['reason'] }}</span>
                                <span class="text-gray-500">{{ $reason['percentage'] }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-filament::section>

            {{-- Expansion Revenue --}}
            <x-filament::section>
                <x-slot name="heading">Expansion Revenue</x-slot>

                @php $expansion = $this->getExpansionRevenue(); @endphp
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                {{ $expansion['upgrades'] }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Upgrades (Starter→Pro)</div>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                {{ $expansion['new_addons'] }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">New add-ons</div>
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Expansion Revenue</span>
                            <span class="font-bold">+EGP {{ number_format($expansion['expansion_revenue']) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Net Revenue Retention</span>
                            <span class="font-bold text-green-600">{{ $expansion['net_revenue_retention'] }}%</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-2">
                            > 100% = growing from existing customers
                        </div>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Export Actions --}}
        <div class="flex gap-2 justify-end">
            <x-filament::button color="gray" icon="heroicon-o-document-arrow-down">
                Export PDF
            </x-filament::button>
            <x-filament::button color="gray" icon="heroicon-o-table-cells">
                Export Excel
            </x-filament::button>
            <x-filament::button color="gray" icon="heroicon-o-envelope">
                Email Report
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
