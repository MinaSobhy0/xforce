<?php

namespace Modules\Evaluations\Filament\Pages;

use Illuminate\Support\Facades\DB;
use Modules\Booking\Models\Visit;
use Modules\Evaluations\Models\Evaluation;
use Modules\Reporting\Filament\Pages\BaseReportPage;

class EvaluationReportPage extends BaseReportPage
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?int $navigationSort = 25;

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.rpt_clinical');
    }

    protected static ?string $slug = 'evaluation-report';

    public static function getNavigationLabel(): string
    {
        return __('evaluations::evaluations.report.title');
    }

    public function getTitle(): string
    {
        return __('evaluations::evaluations.report.title');
    }

    protected function getReportTitle(): string
    {
        return __('evaluations::evaluations.report.title');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner'])) {
            return true;
        }

        if ($user->can('evaluations.report')) {
            return true;
        }

        return false;
    }

    protected function loadReportData(): void
    {
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();
        $branchId = $this->getBranchId();

        // Base query with filters
        $baseQuery = Evaluation::query()
            ->whereBetween('created_at', [$startDate, $endDate->endOfDay()]);

        if ($branchId) {
            $baseQuery->where('branch_id', $branchId);
        }

        // Total evaluations in period
        $totalEvaluations = (clone $baseQuery)->count();

        // Average rating
        $averageRating = (clone $baseQuery)->avg('overall_rating') ?? 0;

        // Calculate response rate (evaluations / completed visits)
        $completedVisitsQuery = Visit::query()
            ->whereIn('status', [Visit::STATUS_COMPLETED, Visit::STATUS_INVOICED])
            ->whereBetween('check_in_at', [$startDate, $endDate->endOfDay()]);

        if ($branchId) {
            $completedVisitsQuery->where('branch_id', $branchId);
        }

        $completedVisits = $completedVisitsQuery->count();
        $responseRate = $completedVisits > 0 ? ($totalEvaluations / $completedVisits) * 100 : 0;

        // NPS calculation
        $promoters = (clone $baseQuery)->whereNotNull('satisfaction_score')
            ->where('satisfaction_score', '>', config('evaluations.nps.passive_max', 8))
            ->count();

        $detractors = (clone $baseQuery)->whereNotNull('satisfaction_score')
            ->where('satisfaction_score', '<=', config('evaluations.nps.detractor_max', 6))
            ->count();

        $totalNps = (clone $baseQuery)->whereNotNull('satisfaction_score')->count();

        $npsScore = $totalNps > 0
            ? (($promoters - $detractors) / $totalNps) * 100
            : 0;

        // Positive percentage
        $positiveCount = (clone $baseQuery)->where('overall_rating', '>=', 4)->count();
        $positivePercentage = $totalEvaluations > 0 ? ($positiveCount / $totalEvaluations) * 100 : 0;

        // Stats cards
        $this->stats = [
            [
                'label' => __('evaluations::evaluations.report.average_rating'),
                'value' => number_format($averageRating, 1).' / 5',
                'icon' => 'heroicon-o-star',
                'color' => $averageRating >= 4 ? 'success' : ($averageRating <= 2 ? 'danger' : 'warning'),
                'description' => $this->formatPercentage($positivePercentage).' '.__('evaluations::evaluations.report.positive'),
            ],
            [
                'label' => __('evaluations::evaluations.report.total_evaluations'),
                'value' => number_format($totalEvaluations),
                'icon' => 'heroicon-o-clipboard-document-check',
                'color' => 'info',
            ],
            [
                'label' => __('evaluations::evaluations.report.response_rate'),
                'value' => $this->formatPercentage($responseRate),
                'icon' => 'heroicon-o-chart-bar',
                'color' => $responseRate >= 50 ? 'success' : ($responseRate >= 20 ? 'warning' : 'danger'),
                'description' => $totalEvaluations.' / '.$completedVisits.' '.__('evaluations::evaluations.report.visits'),
            ],
            [
                'label' => __('evaluations::evaluations.report.nps_score'),
                'value' => number_format($npsScore, 0),
                'icon' => 'heroicon-o-hand-thumb-up',
                'color' => $npsScore >= 50 ? 'success' : ($npsScore >= 0 ? 'warning' : 'danger'),
                'description' => $promoters.' '.__('evaluations::evaluations.nps.promoter').' / '.$detractors.' '.__('evaluations::evaluations.nps.detractor'),
            ],
        ];

        // Rating distribution for pie chart
        $ratingDistribution = (clone $baseQuery)
            ->groupBy('overall_rating')
            ->select('overall_rating', DB::raw('COUNT(*) as count'))
            ->orderBy('overall_rating')
            ->get()
            ->keyBy('overall_rating');

        $ratingLabels = [];
        $ratingData = [];
        $ratingColors = [
            1 => 'rgba(239, 68, 68, 0.8)',   // danger/red
            2 => 'rgba(249, 115, 22, 0.8)',  // orange
            3 => 'rgba(234, 179, 8, 0.8)',   // warning/yellow
            4 => 'rgba(59, 130, 246, 0.8)',  // info/blue
            5 => 'rgba(34, 197, 94, 0.8)',   // success/green
        ];

        for ($i = 1; $i <= 5; $i++) {
            $ratingLabels[] = str_repeat('★', $i).' ('.$i.')';
            $ratingData[] = $ratingDistribution->get($i)?->count ?? 0;
        }

        $this->chartData = [
            'labels' => $ratingLabels,
            'datasets' => [
                [
                    'data' => $ratingData,
                    'backgroundColor' => array_values($ratingColors),
                    'borderColor' => array_values($ratingColors),
                    'borderWidth' => 1,
                ],
            ],
        ];

        // Table data - Recent low-rating evaluations for follow-up
        $lowRatingEvaluations = Evaluation::query()
            ->with(['patient', 'visit', 'branch'])
            ->whereBetween('created_at', [$startDate, $endDate->endOfDay()])
            ->where('overall_rating', '<=', 2)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $this->tableData = $lowRatingEvaluations->map(fn (Evaluation $eval) => [
            'date' => $eval->created_at->format('M d, Y'),
            'patient' => $eval->patient?->full_name ?? '-',
            'rating' => str_repeat('★', $eval->overall_rating).' ('.$eval->overall_rating.'/5)',
            'feedback' => $eval->feedback_text
                ? (strlen($eval->feedback_text) > 50 ? substr($eval->feedback_text, 0, 50).'...' : $eval->feedback_text)
                : '-',
        ])->toArray();
    }

    protected function getStatsCards(): array
    {
        return $this->stats;
    }

    protected function getChartConfig(): array
    {
        return [
            'type' => 'doughnut',
            'data' => $this->chartData,
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('evaluations::evaluations.report.rating_distribution'),
                    ],
                    'legend' => [
                        'position' => 'right',
                    ],
                ],
            ],
        ];
    }

    protected function getTableColumns(): array
    {
        return [
            ['key' => 'date', 'label' => __('evaluations::evaluations.fields.date')],
            ['key' => 'patient', 'label' => __('evaluations::evaluations.fields.patient')],
            ['key' => 'rating', 'label' => __('evaluations::evaluations.fields.overall_rating')],
            ['key' => 'feedback', 'label' => __('evaluations::evaluations.fields.feedback_text')],
        ];
    }
}
