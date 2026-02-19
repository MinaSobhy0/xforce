<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Models\SubscriptionPlan;
use Modules\Core\Models\Tenant;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ClinicsByPlanWidget extends ChartWidget
{
    protected static ?string $heading = 'Clinics by Plan';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'half';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $planStats = $this->getPlanDistribution();

        $labels = $planStats->pluck('name')->toArray();
        $data = $planStats->pluck('count')->toArray();

        $colors = [
            'rgba(99, 102, 241, 0.8)',   // Indigo
            'rgba(16, 185, 129, 0.8)',   // Emerald
            'rgba(245, 158, 11, 0.8)',   // Amber
            'rgba(239, 68, 68, 0.8)',    // Red
            'rgba(59, 130, 246, 0.8)',   // Blue
            'rgba(168, 85, 247, 0.8)',   // Purple
        ];

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => array_slice($colors, 0, count($labels)),
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'right',
                ],
            ],
            'cutout' => '60%',
        ];
    }

    private function getPlanDistribution()
    {
        // Get tenant counts by plan
        $stats = Tenant::query()
            ->select('subscription_plan_id', DB::raw('count(*) as count'))
            ->where(function ($query) {
                $query->where('subscription_status', 'active')
                    ->orWhere('subscription_status', 'trial')
                    ->orWhere('status', 'active');
            })
            ->groupBy('subscription_plan_id')
            ->get();

        // Map plan IDs to names
        return $stats->map(function ($item) {
            $plan = SubscriptionPlan::find($item->subscription_plan_id);
            return [
                'name' => $plan?->name ?? 'No Plan',
                'count' => $item->count,
            ];
        })->sortByDesc('count')->values();
    }
}
