<x-filament-panels::page>
    {{-- Filters Form --}}
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
        @foreach($this->stats as $stat)
            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-lg bg-{{ $stat['color'] ?? 'primary' }}-500/10">
                        <x-dynamic-component
                            :component="$stat['icon'] ?? 'heroicon-o-chart-bar'"
                            class="w-6 h-6 text-{{ $stat['color'] ?? 'primary' }}-500"
                        />
                    </div>
                    <div class="flex-1">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stat['value'] }}</p>
                        @if(isset($stat['description']))
                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $stat['description'] }}</p>
                        @endif
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>

    {{-- Chart and Table Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        {{-- Chart Section --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('reporting::reporting.chart') }}
            </x-slot>

            <div class="h-80">
                <canvas id="reportChart" wire:ignore></canvas>
            </div>
        </x-filament::section>

        {{-- Table Section --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('reporting::reporting.details') }}
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            @foreach($this->getTableColumns() as $column)
                                <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-medium">
                                    {{ $column['label'] }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($this->tableData as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                @foreach($this->getTableColumns() as $column)
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                        {{ $row[$column['key']] ?? '-' }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($this->getTableColumns()) }}" class="px-4 py-8 text-center text-gray-500">
                                    {{ __('reporting::reporting.no_data') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initChart();
        });

        document.addEventListener('livewire:navigated', function() {
            initChart();
        });

        Livewire.on('chartDataUpdated', function() {
            initChart();
        });

        function initChart() {
            const ctx = document.getElementById('reportChart');
            if (!ctx) return;

            // Destroy existing chart if any
            if (window.reportChartInstance) {
                window.reportChartInstance.destroy();
            }

            const chartConfig = @json($this->getChartConfig());

            if (chartConfig && chartConfig.data) {
                window.reportChartInstance = new Chart(ctx, chartConfig);
            }
        }
    </script>
    @endpush
</x-filament-panels::page>
