<?php

namespace Modules\Reporting\Filament\Pages;

use Modules\Equipment\Models\Equipment;
use Modules\Equipment\Models\EquipmentShotLog;
use Modules\Equipment\Models\EquipmentMaintenanceLog;
use Illuminate\Support\Facades\DB;

class EquipmentReportPage extends BaseReportPage
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('reporting::reporting.equipment_report');
    }

    public function getTitle(): string
    {
        return __('reporting::reporting.equipment_report');
    }

    protected function getReportTitle(): string
    {
        return __('reporting::reporting.equipment_report');
    }

    protected function loadReportData(): void
    {
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();
        $branchId = $this->getBranchId();

        // Equipment counts
        $equipmentQuery = Equipment::query();
        if ($branchId) {
            $equipmentQuery->where('branch_id', $branchId);
        }

        $totalEquipment = (clone $equipmentQuery)->count();
        $activeEquipment = (clone $equipmentQuery)->where('status', 'active')->count();
        $maintenanceEquipment = (clone $equipmentQuery)->where('status', 'maintenance')->count();

        // Total shots in period
        $shotsQuery = EquipmentShotLog::query()
            ->whereBetween('logged_at', [$startDate, $endDate]);

        if ($branchId) {
            $shotsQuery->whereHas('equipment', fn ($q) => $q->where('branch_id', $branchId));
        }

        $totalShots = $shotsQuery->sum('shots_count');

        // Maintenance costs in period
        $maintenanceQuery = EquipmentMaintenanceLog::query()
            ->whereBetween('performed_at', [$startDate, $endDate]);

        if ($branchId) {
            $maintenanceQuery->whereHas('equipment', fn ($q) => $q->where('branch_id', $branchId));
        }

        $maintenanceCost = $maintenanceQuery->sum('cost_minor');
        $maintenanceCount = $maintenanceQuery->count();

        // Utilization rate (equipment with shots vs total active)
        $equipmentWithShots = EquipmentShotLog::query()
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->when($branchId, function ($q) use ($branchId) {
                $q->whereHas('equipment', fn ($eq) => $eq->where('branch_id', $branchId));
            })
            ->distinct('equipment_id')
            ->count('equipment_id');

        $utilizationRate = $activeEquipment > 0
            ? ($equipmentWithShots / $activeEquipment) * 100
            : 0;

        // Stats cards
        $this->stats = [
            [
                'label' => __('reporting::reporting.total_equipment'),
                'value' => number_format($totalEquipment),
                'description' => $activeEquipment . ' ' . __('reporting::reporting.active'),
                'icon' => 'heroicon-o-cog-6-tooth',
                'color' => 'primary',
            ],
            [
                'label' => __('reporting::reporting.total_shots'),
                'value' => number_format($totalShots),
                'icon' => 'heroicon-o-bolt',
                'color' => 'success',
            ],
            [
                'label' => __('reporting::reporting.maintenance_cost'),
                'value' => $this->formatCurrency($maintenanceCost),
                'description' => $maintenanceCount . ' ' . __('reporting::reporting.maintenance_events'),
                'icon' => 'heroicon-o-wrench-screwdriver',
                'color' => 'warning',
            ],
            [
                'label' => __('reporting::reporting.utilization_rate'),
                'value' => $this->formatPercentage($utilizationRate),
                'icon' => 'heroicon-o-chart-pie',
                'color' => 'info',
            ],
        ];

        // Shots by equipment
        $shotsByEquipment = DB::table('equipment_shot_logs')
            ->join('equipment', 'equipment_shot_logs.equipment_id', '=', 'equipment.id')
            ->whereBetween('equipment_shot_logs.logged_at', [$startDate, $endDate])
            ->when($branchId, fn ($q) => $q->where('equipment.branch_id', $branchId))
            ->groupBy('equipment.id', 'equipment.name')
            ->select(
                'equipment.id',
                'equipment.name',
                DB::raw('SUM(equipment_shot_logs.shots_count) as total_shots'),
                DB::raw('COUNT(*) as session_count')
            )
            ->orderByDesc('total_shots')
            ->limit(10)
            ->get();

        // Daily shots for chart
        $dailyShots = DB::table('equipment_shot_logs')
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->when($branchId, function ($q) use ($branchId) {
                $q->whereIn('equipment_id', Equipment::where('branch_id', $branchId)->pluck('id'));
            })
            ->groupBy(DB::raw('DATE(logged_at)'))
            ->select(
                DB::raw('DATE(logged_at) as date'),
                DB::raw('SUM(shots_count) as total')
            )
            ->orderBy('date')
            ->get();

        // Chart data
        $this->chartData = [
            'labels' => $dailyShots->pluck('date')->map(fn ($d) => \Carbon\Carbon::parse($d)->format('M d'))->toArray(),
            'datasets' => [
                [
                    'label' => __('reporting::reporting.shots_fired'),
                    'data' => $dailyShots->pluck('total')->toArray(),
                    'backgroundColor' => 'rgba(245, 158, 11, 0.5)',
                    'borderColor' => 'rgb(245, 158, 11)',
                    'borderWidth' => 2,
                ],
            ],
        ];

        // Table data
        $this->tableData = $shotsByEquipment->map(fn ($item) => [
            'equipment' => $item->name,
            'shots' => number_format($item->total_shots),
            'sessions' => $item->session_count,
            'avg_per_session' => $item->session_count > 0
                ? number_format($item->total_shots / $item->session_count, 0)
                : 0,
        ])->toArray();
    }

    protected function getStatsCards(): array
    {
        return $this->stats;
    }

    protected function getChartConfig(): array
    {
        return [
            'type' => 'line',
            'data' => $this->chartData,
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('reporting::reporting.daily_shots'),
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
            ['key' => 'equipment', 'label' => __('reporting::reporting.equipment')],
            ['key' => 'shots', 'label' => __('reporting::reporting.total_shots')],
            ['key' => 'sessions', 'label' => __('reporting::reporting.sessions')],
            ['key' => 'avg_per_session', 'label' => __('reporting::reporting.avg_per_session')],
        ];
    }
}
