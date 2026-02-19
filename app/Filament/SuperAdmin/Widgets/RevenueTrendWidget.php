<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Models\PlatformInvoice;
use Modules\Core\Models\Tenant;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RevenueTrendWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue Trend';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $months = collect();
        $revenues = collect();

        // Get last 12 months of revenue data
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $months->push($date->format('M Y'));

            // Calculate revenue for this month
            // First try to get from invoices
            $invoiceRevenue = PlatformInvoice::where('status', 'paid')
                ->whereYear('paid_at', $date->year)
                ->whereMonth('paid_at', $date->month)
                ->sum('amount_minor');

            if ($invoiceRevenue > 0) {
                $revenues->push($invoiceRevenue / 100);
            } else {
                // Fallback: estimate from active tenants at that time
                $activeCount = Tenant::where('created_at', '<=', $date->endOfMonth())
                    ->where(function ($query) use ($date) {
                        $query->whereNull('subscription_expires_at')
                            ->orWhere('subscription_expires_at', '>=', $date->startOfMonth());
                    })
                    ->where(function ($query) {
                        $query->where('subscription_status', 'active')
                            ->orWhere('status', 'active');
                    })
                    ->count();

                // Estimate average monthly revenue per tenant
                $avgRevenue = $this->getAverageMonthlyRevenue();
                $revenues->push($activeCount * $avgRevenue);
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Monthly Revenue (EGP)',
                    'data' => $revenues->toArray(),
                    'fill' => true,
                    'backgroundColor' => 'rgba(99, 102, 241, 0.2)',
                    'borderColor' => 'rgb(99, 102, 241)',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $months->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => "function(context) { return 'EGP ' + context.raw.toLocaleString(); }",
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => "function(value) { return 'EGP ' + value.toLocaleString(); }",
                    ],
                ],
            ],
        ];
    }

    private function getAverageMonthlyRevenue(): float
    {
        // Get average plan price from active tenants
        $activeTenants = Tenant::with('plan')
            ->where(function ($query) {
                $query->where('subscription_status', 'active')
                    ->orWhere('status', 'active');
            })
            ->get();

        if ($activeTenants->isEmpty()) {
            return 1500; // Default estimate
        }

        $totalRevenue = $activeTenants->sum(function ($tenant) {
            return $tenant->plan?->price_monthly_minor ?? 150000; // Default 1500 EGP
        });

        return ($totalRevenue / $activeTenants->count()) / 100;
    }
}
