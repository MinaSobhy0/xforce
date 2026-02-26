<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filters --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            {{ $this->form }}
        </div>

        {{-- Primary Statistics Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
            {{-- Total Records --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <x-heroicon-o-clipboard-document-list class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('attendance::attendance.reports.stats.total_records') }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_records'] ?? 0) }}</p>
                    </div>
                </div>
            </div>

            {{-- Present --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                        <x-heroicon-o-check-circle class="w-6 h-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('attendance::attendance.reports.stats.present') }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['present_count'] ?? 0) }}</p>
                    </div>
                </div>
            </div>

            {{-- Absent --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-red-100 dark:bg-red-900/30 rounded-lg">
                        <x-heroicon-o-x-circle class="w-6 h-6 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('attendance::attendance.reports.stats.absent') }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['absent_count'] ?? 0) }}</p>
                    </div>
                </div>
            </div>

            {{-- Late --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                        <x-heroicon-o-clock class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('attendance::attendance.reports.stats.late') }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['late_count'] ?? 0) }}</p>
                    </div>
                </div>
            </div>

            {{-- Attendance Rate --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg">
                        <x-heroicon-o-chart-pie class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('attendance::attendance.reports.stats.attendance_rate') }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['attendance_rate'] ?? 0 }}%</p>
                    </div>
                </div>
            </div>

            {{-- Violations --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
                        <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-orange-600 dark:text-orange-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('attendance::attendance.reports.stats.violations') }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['violations_count'] ?? 0) }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Secondary Statistics Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Total Working Hours --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                        <x-heroicon-o-briefcase class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('attendance::attendance.reports.stats.total_working_hours') }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total_working_hours'] ?? 0 }} <span class="text-sm font-normal">hrs</span></p>
                    </div>
                </div>
            </div>

            {{-- Avg Working Hours --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-cyan-100 dark:bg-cyan-900/30 rounded-lg">
                        <x-heroicon-o-calculator class="w-6 h-6 text-cyan-600 dark:text-cyan-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('attendance::attendance.reports.stats.avg_working_hours') }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['avg_working_hours'] ?? 0 }} <span class="text-sm font-normal">hrs</span></p>
                    </div>
                </div>
            </div>

            {{-- Total Overtime --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-teal-100 dark:bg-teal-900/30 rounded-lg">
                        <x-heroicon-o-arrow-trending-up class="w-6 h-6 text-teal-600 dark:text-teal-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('attendance::attendance.reports.stats.total_overtime') }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total_overtime_hours'] ?? 0 }} <span class="text-sm font-normal">hrs</span></p>
                    </div>
                </div>
            </div>

            {{-- Total Late Minutes --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-rose-100 dark:bg-rose-900/30 rounded-lg">
                        <x-heroicon-o-arrow-right-circle class="w-6 h-6 text-rose-600 dark:text-rose-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('attendance::attendance.reports.stats.total_late_hours') }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total_late_hours'] ?? 0 }} <span class="text-sm font-normal">min</span></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Chart --}}
        @if(!empty($chartData))
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('attendance::attendance.reports.chart.title') }}
                </h3>
            </div>
            <div class="p-4">
                <div class="h-64" x-data="{
                    chartData: @js($chartData),
                    init() {
                        const ctx = this.$refs.chart.getContext('2d');
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: this.chartData.map(d => d.date),
                                datasets: [
                                    {
                                        label: '{{ __('attendance::attendance.reports.stats.present') }}',
                                        data: this.chartData.map(d => d.present),
                                        backgroundColor: 'rgba(34, 197, 94, 0.7)',
                                    },
                                    {
                                        label: '{{ __('attendance::attendance.reports.stats.late') }}',
                                        data: this.chartData.map(d => d.late),
                                        backgroundColor: 'rgba(234, 179, 8, 0.7)',
                                    },
                                    {
                                        label: '{{ __('attendance::attendance.reports.stats.absent') }}',
                                        data: this.chartData.map(d => d.absent),
                                        backgroundColor: 'rgba(239, 68, 68, 0.7)',
                                    },
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    x: { stacked: true },
                                    y: { stacked: true, beginAtZero: true }
                                }
                            }
                        });
                    }
                }">
                    <canvas x-ref="chart"></canvas>
                </div>
            </div>
        </div>
        @endif

        {{-- Data Table --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('attendance::attendance.reports.table.title') }}
                </h3>
            </div>
            <div class="p-4">
                {{ $this->table }}
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @endpush
</x-filament-panels::page>
