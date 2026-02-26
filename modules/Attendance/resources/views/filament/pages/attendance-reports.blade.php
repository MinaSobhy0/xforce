<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filters --}}
        <x-filament::section>
            {{ $this->form }}
        </x-filament::section>

        {{-- Statistics Cards --}}
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-6">
            {{-- Total Records --}}
            <x-filament::section class="col-span-1">
                <div class="text-center">
                    <div class="text-3xl font-bold text-primary-600 dark:text-primary-400">
                        {{ $stats['total_records'] ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('attendance::attendance.reports.stats.total_records') }}
                    </div>
                </div>
            </x-filament::section>

            {{-- Present --}}
            <x-filament::section class="col-span-1">
                <div class="text-center">
                    <div class="text-3xl font-bold text-success-600 dark:text-success-400">
                        {{ $stats['present_count'] ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('attendance::attendance.reports.stats.present') }}
                    </div>
                </div>
            </x-filament::section>

            {{-- Absent --}}
            <x-filament::section class="col-span-1">
                <div class="text-center">
                    <div class="text-3xl font-bold text-danger-600 dark:text-danger-400">
                        {{ $stats['absent_count'] ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('attendance::attendance.reports.stats.absent') }}
                    </div>
                </div>
            </x-filament::section>

            {{-- Late --}}
            <x-filament::section class="col-span-1">
                <div class="text-center">
                    <div class="text-3xl font-bold text-warning-600 dark:text-warning-400">
                        {{ $stats['late_count'] ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('attendance::attendance.reports.stats.late') }}
                    </div>
                </div>
            </x-filament::section>

            {{-- Attendance Rate --}}
            <x-filament::section class="col-span-1">
                <div class="text-center">
                    <div class="text-3xl font-bold text-info-600 dark:text-info-400">
                        {{ $stats['attendance_rate'] ?? 0 }}%
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('attendance::attendance.reports.stats.attendance_rate') }}
                    </div>
                </div>
            </x-filament::section>

            {{-- Violations --}}
            <x-filament::section class="col-span-1">
                <div class="text-center">
                    <div class="text-3xl font-bold text-danger-600 dark:text-danger-400">
                        {{ $stats['violations_count'] ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('attendance::attendance.reports.stats.violations') }}
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Secondary Stats --}}
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            {{-- Total Working Hours --}}
            <x-filament::section class="col-span-1">
                <div class="text-center">
                    <div class="text-2xl font-semibold text-gray-700 dark:text-gray-300">
                        {{ $stats['total_working_hours'] ?? 0 }} hrs
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('attendance::attendance.reports.stats.total_working_hours') }}
                    </div>
                </div>
            </x-filament::section>

            {{-- Avg Working Hours --}}
            <x-filament::section class="col-span-1">
                <div class="text-center">
                    <div class="text-2xl font-semibold text-gray-700 dark:text-gray-300">
                        {{ $stats['avg_working_hours'] ?? 0 }} hrs
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('attendance::attendance.reports.stats.avg_working_hours') }}
                    </div>
                </div>
            </x-filament::section>

            {{-- Total Overtime --}}
            <x-filament::section class="col-span-1">
                <div class="text-center">
                    <div class="text-2xl font-semibold text-gray-700 dark:text-gray-300">
                        {{ $stats['total_overtime_hours'] ?? 0 }} hrs
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('attendance::attendance.reports.stats.total_overtime') }}
                    </div>
                </div>
            </x-filament::section>

            {{-- Total Late Minutes --}}
            <x-filament::section class="col-span-1">
                <div class="text-center">
                    <div class="text-2xl font-semibold text-gray-700 dark:text-gray-300">
                        {{ $stats['total_late_hours'] ?? 0 }} min
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('attendance::attendance.reports.stats.total_late_hours') }}
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Chart --}}
        @if(!empty($chartData))
        <x-filament::section>
            <x-slot name="heading">
                {{ __('attendance::attendance.reports.chart.title') }}
            </x-slot>

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
        </x-filament::section>
        @endif

        {{-- Data Table --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('attendance::attendance.reports.table.title') }}
            </x-slot>

            {{ $this->table }}
        </x-filament::section>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @endpush
</x-filament-panels::page>
