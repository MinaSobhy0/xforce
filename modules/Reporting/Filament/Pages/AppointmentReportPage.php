<?php

namespace Modules\Reporting\Filament\Pages;

use Modules\Booking\Models\Appointment;
use Illuminate\Support\Facades\DB;

class AppointmentReportPage extends BaseReportPage
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('reporting::reporting.appointment_report');
    }

    public function getTitle(): string
    {
        return __('reporting::reporting.appointment_report');
    }

    protected function getReportTitle(): string
    {
        return __('reporting::reporting.appointment_report');
    }

    protected function loadReportData(): void
    {
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();
        $branchId = $this->getBranchId();

        // Base query
        $query = Appointment::query()
            ->whereBetween('date', [$startDate, $endDate]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        // Total appointments
        $totalAppointments = (clone $query)->count();

        // Completed appointments
        $completedAppointments = (clone $query)->where('status', 'completed')->count();

        // Cancelled appointments
        $cancelledAppointments = (clone $query)->where('status', 'cancelled')->count();

        // No-shows
        $noShows = (clone $query)->where('status', 'no_show')->count();

        // Completion rate
        $completionRate = $totalAppointments > 0
            ? ($completedAppointments / $totalAppointments) * 100
            : 0;

        // No-show rate
        $noShowRate = $totalAppointments > 0
            ? ($noShows / $totalAppointments) * 100
            : 0;

        // Average duration
        $avgDuration = (clone $query)
            ->where('status', 'completed')
            ->avg('duration_minutes') ?? 0;

        // Stats cards
        $this->stats = [
            [
                'label' => __('reporting::reporting.total_appointments'),
                'value' => number_format($totalAppointments),
                'icon' => 'heroicon-o-calendar-days',
                'color' => 'primary',
            ],
            [
                'label' => __('reporting::reporting.completion_rate'),
                'value' => $this->formatPercentage($completionRate),
                'description' => $completedAppointments . ' ' . __('reporting::reporting.completed'),
                'icon' => 'heroicon-o-check-circle',
                'color' => 'success',
            ],
            [
                'label' => __('reporting::reporting.no_show_rate'),
                'value' => $this->formatPercentage($noShowRate),
                'description' => $noShows . ' ' . __('reporting::reporting.no_shows'),
                'icon' => 'heroicon-o-x-circle',
                'color' => 'danger',
            ],
            [
                'label' => __('reporting::reporting.avg_duration'),
                'value' => round($avgDuration) . ' ' . __('core::core.minutes'),
                'icon' => 'heroicon-o-clock',
                'color' => 'info',
            ],
        ];

        // Appointments by status
        $byStatus = Appointment::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy('status')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->get();

        // Peak hours analysis
        $peakHours = Appointment::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy(DB::raw('EXTRACT(HOUR FROM start_time)'))
            ->select(
                DB::raw('EXTRACT(HOUR FROM start_time) as hour'),
                DB::raw('COUNT(*) as count')
            )
            ->orderBy('hour')
            ->get();

        // Appointments by day of week
        $byDayOfWeek = Appointment::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy(DB::raw('EXTRACT(DOW FROM date)'))
            ->select(
                DB::raw('EXTRACT(DOW FROM date) as day'),
                DB::raw('COUNT(*) as count')
            )
            ->orderBy('day')
            ->get();

        $dayNames = [
            0 => __('core::core.days_of_week.sunday'),
            1 => __('core::core.days_of_week.monday'),
            2 => __('core::core.days_of_week.tuesday'),
            3 => __('core::core.days_of_week.wednesday'),
            4 => __('core::core.days_of_week.thursday'),
            5 => __('core::core.days_of_week.friday'),
            6 => __('core::core.days_of_week.saturday'),
        ];

        // Chart data - Peak hours
        $this->chartData = [
            'labels' => $peakHours->pluck('hour')->map(fn ($h) => sprintf('%02d:00', $h))->toArray(),
            'datasets' => [
                [
                    'label' => __('reporting::reporting.appointments'),
                    'data' => $peakHours->pluck('count')->toArray(),
                    'backgroundColor' => 'rgba(139, 92, 246, 0.5)',
                    'borderColor' => 'rgb(139, 92, 246)',
                    'borderWidth' => 2,
                ],
            ],
        ];

        // Table data - by day of week
        $this->tableData = $byDayOfWeek->map(fn ($item) => [
            'day' => $dayNames[(int)$item->day] ?? 'Day ' . $item->day,
            'appointments' => $item->count,
            'percentage' => $totalAppointments > 0
                ? $this->formatPercentage(($item->count / $totalAppointments) * 100)
                : '0%',
        ])->toArray();
    }

    protected function getStatsCards(): array
    {
        return $this->stats;
    }

    protected function getChartConfig(): array
    {
        return [
            'type' => 'bar',
            'data' => $this->chartData,
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('reporting::reporting.peak_hours'),
                    ],
                ],
                'scales' => [
                    'y' => ['beginAtZero' => true],
                ],
            ],
        ];
    }

    protected function getTableColumns(): array
    {
        return [
            ['key' => 'day', 'label' => __('core::core.day')],
            ['key' => 'appointments', 'label' => __('reporting::reporting.appointments')],
            ['key' => 'percentage', 'label' => __('reporting::reporting.percentage')],
        ];
    }
}
