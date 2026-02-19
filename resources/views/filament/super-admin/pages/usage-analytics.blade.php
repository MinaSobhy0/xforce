<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Period Filter --}}
        <div class="flex justify-end">
            {{ $this->form }}
        </div>

        {{-- Aggregate Metrics --}}
        <x-filament::section>
            <x-slot name="heading">Aggregate Metrics</x-slot>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                @foreach ($this->getAggregateMetrics() as $metric)
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
                        <x-dynamic-component :component="$metric['icon']"
                            class="w-8 h-8 mx-auto text-primary-500 mb-2" />
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $metric['value'] }}</div>
                        <div class="text-sm text-gray-500">{{ $metric['label'] }}</div>
                        <div class="text-xs text-gray-400">{{ $metric['description'] }}</div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- Usage Trend Chart --}}
        <x-filament::section>
            <x-slot name="heading">Usage Trend (3 Months)</x-slot>

            <div class="h-80">
                <canvas id="usageTrendChart"></canvas>
            </div>
        </x-filament::section>

        {{-- Top Tenants by Usage --}}
        <x-filament::section>
            <x-slot name="heading">Top Tenants by Usage</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="text-left py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Clinic</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Appointments</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Patients</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">WhatsApp</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Storage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->getTopTenantsByUsage() as $tenant)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-3 px-4 font-medium text-gray-900 dark:text-white">{{ $tenant['name'] }}</td>
                                <td class="text-right py-3 px-4 font-mono">{{ number_format($tenant['appointments']) }}</td>
                                <td class="text-right py-3 px-4 font-mono">{{ number_format($tenant['patients']) }}</td>
                                <td class="text-right py-3 px-4 font-mono">{{ number_format($tenant['whatsapp']) }}</td>
                                <td class="text-right py-3 px-4 font-mono">{{ $tenant['storage'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Quota Warnings --}}
        <x-filament::section>
            <x-slot name="heading">Quota Warnings</x-slot>
            <x-slot name="description">
                These are upsell opportunities! Clinics approaching limits are ready for plan upgrades.
            </x-slot>

            <div class="space-y-3">
                @foreach ($this->getQuotaWarnings() as $warning)
                    <div class="flex items-center justify-between p-3 rounded-lg
                        {{ $warning['severity'] === 'critical' ? 'bg-red-50 dark:bg-red-900/20' : 'bg-yellow-50 dark:bg-yellow-900/20' }}">
                        <div class="flex items-center gap-3">
                            @if ($warning['severity'] === 'critical')
                                <x-heroicon-o-exclamation-circle class="w-5 h-5 text-red-500" />
                            @else
                                <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-yellow-500" />
                            @endif
                            <div>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $warning['tenant'] }}</span>
                                <span class="text-gray-600 dark:text-gray-400"> — {{ $warning['warning'] }}</span>
                            </div>
                        </div>
                        <x-filament::button size="sm" color="gray">
                            Contact
                        </x-filament::button>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 pt-4 border-t dark:border-gray-700">
                <x-filament::button wire:click="sendUpgradeNudge" icon="heroicon-o-envelope">
                    Send Upgrade Nudge to All Warning Clinics
                </x-filament::button>
            </div>
        </x-filament::section>
    </div>
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('usageTrendChart');
                if (ctx) {
                    const chartData = @json($this->getUsageTrendData());
                    new Chart(ctx, {
                        type: 'line',
                        data: chartData,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return value.toLocaleString();
                                        }
                                    }
                                }
                            },
                            interaction: {
                                intersect: false,
                                mode: 'index',
                            },
                        }
                    });
                }
            });
        </script>
    @endpush
</x-filament-panels::page>
