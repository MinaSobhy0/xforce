<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Models\Module;
use Modules\Core\Models\Tenant;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ModulePopularityWidget extends ChartWidget
{
    protected static ?string $heading = 'Module Popularity';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'half';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $moduleStats = $this->getModuleUsageStats();

        $labels = $moduleStats->pluck('name')->toArray();
        $data = $moduleStats->pluck('usage_count')->toArray();

        // Generate colors for each module
        $colors = [
            'rgba(99, 102, 241, 0.8)',   // Indigo
            'rgba(16, 185, 129, 0.8)',   // Emerald
            'rgba(245, 158, 11, 0.8)',   // Amber
            'rgba(239, 68, 68, 0.8)',    // Red
            'rgba(59, 130, 246, 0.8)',   // Blue
            'rgba(168, 85, 247, 0.8)',   // Purple
            'rgba(236, 72, 153, 0.8)',   // Pink
            'rgba(20, 184, 166, 0.8)',   // Teal
            'rgba(251, 146, 60, 0.8)',   // Orange
            'rgba(132, 204, 22, 0.8)',   // Lime
        ];

        // Extend colors if needed
        while (count($colors) < count($labels)) {
            $colors = array_merge($colors, $colors);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Active Tenants',
                    'data' => $data,
                    'backgroundColor' => array_slice($colors, 0, count($labels)),
                    'borderColor' => array_map(fn($c) => str_replace('0.8', '1', $c), array_slice($colors, 0, count($labels))),
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Number of Tenants',
                    ],
                ],
            ],
        ];
    }

    private function getModuleUsageStats()
    {
        // Get all active tenants with their features
        $tenants = Tenant::where(function ($query) {
            $query->where('subscription_status', 'active')
                ->orWhere('status', 'active');
        })->whereNotNull('features')->get();

        // Count module usage across all tenants
        $moduleCounts = [];
        foreach ($tenants as $tenant) {
            $features = $tenant->features ?? [];
            if (is_array($features)) {
                foreach ($features as $moduleCode) {
                    $moduleCounts[$moduleCode] = ($moduleCounts[$moduleCode] ?? 0) + 1;
                }
            }
        }

        // Get module names from database
        $modules = Module::whereIn('code', array_keys($moduleCounts))->pluck('name', 'code');

        // Build stats array
        $stats = collect($moduleCounts)->map(function ($count, $code) use ($modules) {
            return [
                'name' => $modules[$code] ?? ucfirst($code),
                'usage_count' => $count,
            ];
        })->sortByDesc('usage_count')->take(10)->values();

        // If no data, show placeholder
        if ($stats->isEmpty()) {
            $totalTenants = $tenants->count() ?: 1;
            return collect([
                ['name' => 'Core', 'usage_count' => $totalTenants],
                ['name' => 'Patients', 'usage_count' => $totalTenants],
                ['name' => 'Booking', 'usage_count' => (int) ($totalTenants * 0.95)],
                ['name' => 'Billing', 'usage_count' => (int) ($totalTenants * 0.90)],
            ]);
        }

        return $stats;
    }
}
