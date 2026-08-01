<?php

namespace Modules\Reporting\Filament\Pages;

use Modules\Patients\Models\Patient;
use Modules\Booking\Models\Appointment;
use Modules\Memberships\Models\MembershipSubscription;
use Illuminate\Support\Facades\DB;

class PatientReportPage extends BaseReportPage
{
    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 2;

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.rpt_clinical');
    }

    public static function getNavigationLabel(): string
    {
        return __('reporting::reporting.patient_report');
    }

    public function getTitle(): string
    {
        return __('reporting::reporting.patient_report');
    }

    protected function getReportTitle(): string
    {
        return __('reporting::reporting.patient_report');
    }

    protected function loadReportData(): void
    {
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();
        $branchId = $this->getBranchId();

        // New patients in period
        $newPatientsQuery = Patient::query()
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($branchId) {
            $newPatientsQuery->where('preferred_branch_id', $branchId);
        }

        $newPatients = $newPatientsQuery->count();

        // Total patients
        $totalPatients = Patient::count();

        // Returning patients (patients with >1 appointment)
        $returningPatientsQuery = DB::table('appointments')
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 'completed');

        if ($branchId) {
            $returningPatientsQuery->where('branch_id', $branchId);
        }

        $patientsWithAppointments = $returningPatientsQuery
            ->distinct('patient_id')
            ->count('patient_id');

        // Retention rate (patients who returned this period vs previous period)
        $periodDays = $startDate->diffInDays($endDate) + 1;
        $prevStartDate = $startDate->copy()->subDays($periodDays);
        $prevEndDate = $startDate->copy()->subDay();

        $prevPatientsQuery = DB::table('appointments')
            ->whereBetween('date', [$prevStartDate, $prevEndDate])
            ->where('status', 'completed')
            ->distinct('patient_id')
            ->pluck('patient_id');

        $returnedPatients = DB::table('appointments')
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 'completed')
            ->whereIn('patient_id', $prevPatientsQuery)
            ->distinct('patient_id')
            ->count('patient_id');

        $retentionRate = count($prevPatientsQuery) > 0
            ? ($returnedPatients / count($prevPatientsQuery)) * 100
            : 0;

        // Members (patients with active memberships)
        $vipPatients = MembershipSubscription::query()
            ->where('status', 'active')
            ->distinct('patient_id')
            ->count('patient_id');

        // Stats cards
        $this->stats = [
            [
                'label' => __('reporting::reporting.new_patients'),
                'value' => number_format($newPatients),
                'icon' => 'heroicon-o-user-plus',
                'color' => 'success',
            ],
            [
                'label' => __('reporting::reporting.returning_patients'),
                'value' => number_format($patientsWithAppointments),
                'icon' => 'heroicon-o-arrow-path',
                'color' => 'info',
            ],
            [
                'label' => __('reporting::reporting.retention_rate'),
                'value' => $this->formatPercentage($retentionRate),
                'icon' => 'heroicon-o-chart-bar',
                'color' => 'warning',
            ],
            [
                'label' => __('reporting::reporting.vip_patients'),
                'value' => number_format($vipPatients),
                'icon' => 'heroicon-o-star',
                'color' => 'primary',
            ],
        ];

        // Demographics - Gender distribution
        $genderDistribution = Patient::query()
            ->groupBy('gender')
            ->select('gender', DB::raw('COUNT(*) as count'))
            ->get()
            ->pluck('count', 'gender')
            ->toArray();

        // Demographics - Referral source
        $referralSources = Patient::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('referral_source')
            ->select('referral_source', DB::raw('COUNT(*) as count'))
            ->orderByDesc('count')
            ->get();

        // New patients by day
        $dailyNewPatients = Patient::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->orderBy('date')
            ->get();

        // Chart data
        $this->chartData = [
            'labels' => $dailyNewPatients->pluck('date')->map(fn ($d) => \Carbon\Carbon::parse($d)->format('M d'))->toArray(),
            'datasets' => [
                [
                    'label' => __('reporting::reporting.new_patients'),
                    'data' => $dailyNewPatients->pluck('count')->toArray(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 2,
                    'fill' => true,
                ],
            ],
        ];

        // Table data - Referral sources breakdown
        $this->tableData = $referralSources->map(fn ($item) => [
            'source' => __('patients::patients.referral_sources.' . ($item->referral_source ?? 'other')),
            'count' => $item->count,
            'percentage' => $newPatients > 0
                ? $this->formatPercentage(($item->count / $newPatients) * 100)
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
            'type' => 'line',
            'data' => $this->chartData,
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('reporting::reporting.new_patients_trend'),
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
            ['key' => 'source', 'label' => __('reporting::reporting.referral_source')],
            ['key' => 'count', 'label' => __('reporting::reporting.count')],
            ['key' => 'percentage', 'label' => __('reporting::reporting.percentage')],
        ];
    }
}
