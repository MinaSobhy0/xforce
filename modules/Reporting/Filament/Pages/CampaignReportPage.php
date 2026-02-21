<?php

namespace Modules\Reporting\Filament\Pages;

use Modules\Marketing\Models\Campaign;
use Modules\Marketing\Models\CampaignRecipient;
use Illuminate\Support\Facades\DB;

class CampaignReportPage extends BaseReportPage
{
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?int $navigationSort = 8;

    public static function getNavigationLabel(): string
    {
        return __('reporting::reporting.campaign_report');
    }

    public function getTitle(): string
    {
        return __('reporting::reporting.campaign_report');
    }

    protected function getReportTitle(): string
    {
        return __('reporting::reporting.campaign_report');
    }

    protected function loadReportData(): void
    {
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();
        $branchId = $this->getBranchId();

        // Campaigns in period
        $campaignsQuery = Campaign::query()
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($branchId) {
            $campaignsQuery->where('branch_id', $branchId);
        }

        $totalCampaigns = (clone $campaignsQuery)->count();
        $sentCampaigns = (clone $campaignsQuery)->where('status', 'sent')->count();

        // Message statistics
        $recipientsQuery = CampaignRecipient::query()
            ->whereHas('campaign', function ($q) use ($startDate, $endDate, $branchId) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
                if ($branchId) {
                    $q->where('branch_id', $branchId);
                }
            });

        $totalSent = (clone $recipientsQuery)->whereIn('status', ['sent', 'delivered', 'read'])->count();
        $delivered = (clone $recipientsQuery)->whereIn('status', ['delivered', 'read'])->count();
        $opened = (clone $recipientsQuery)->whereNotNull('read_at')->count();
        $clicked = $opened; // No click tracking available
        $failed = (clone $recipientsQuery)->where('status', 'failed')->count();
        $converted = 0; // No conversion tracking in CampaignRecipient

        // Rates
        $deliveryRate = $totalSent > 0 ? ($delivered / $totalSent) * 100 : 0;
        $openRate = $delivered > 0 ? ($opened / $delivered) * 100 : 0;
        $clickRate = 0;
        $conversionRate = 0;

        // Total campaign cost (not tracked in current schema)
        $totalCost = 0;

        // Revenue attributed to campaigns (not tracked yet)
        $campaignRevenue = 0;

        // ROI
        $roi = $totalCost > 0 ? (($campaignRevenue - $totalCost) / $totalCost) * 100 : 0;

        // Cost per acquisition
        $cpa = $converted > 0 ? $totalCost / $converted : 0;

        // Stats cards
        $this->stats = [
            [
                'label' => __('reporting::reporting.campaigns_sent'),
                'value' => number_format($sentCampaigns),
                'description' => number_format($totalSent) . ' ' . __('reporting::reporting.messages'),
                'icon' => 'heroicon-o-megaphone',
                'color' => 'primary',
            ],
            [
                'label' => __('reporting::reporting.open_rate'),
                'value' => $this->formatPercentage($openRate),
                'description' => number_format($opened) . ' ' . __('reporting::reporting.opened'),
                'icon' => 'heroicon-o-envelope-open',
                'color' => 'success',
            ],
            [
                'label' => __('reporting::reporting.conversion_rate'),
                'value' => $this->formatPercentage($conversionRate),
                'description' => number_format($converted) . ' ' . __('reporting::reporting.conversions'),
                'icon' => 'heroicon-o-chart-bar',
                'color' => 'info',
            ],
            [
                'label' => __('reporting::reporting.roi'),
                'value' => $this->formatPercentage($roi),
                'description' => __('reporting::reporting.cpa') . ': ' . $this->formatCurrency($cpa),
                'icon' => 'heroicon-o-banknotes',
                'color' => $roi > 0 ? 'success' : 'danger',
            ],
        ];

        // Campaign performance table
        $campaigns = Campaign::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'sent')
            ->withCount([
                'recipients',
                'recipients as delivered_count' => fn ($q) => $q->whereIn('status', ['delivered', 'read']),
                'recipients as opened_count' => fn ($q) => $q->whereNotNull('read_at'),
            ])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Channel performance for chart
        $byChannel = Campaign::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'sent')
            ->groupBy('channel')
            ->select(
                'channel',
                DB::raw('COUNT(*) as campaigns'),
                DB::raw('SUM(total_cost_minor) as cost')
            )
            ->get();

        // Chart data - funnel
        $this->chartData = [
            'labels' => [
                __('reporting::reporting.sent'),
                __('reporting::reporting.delivered'),
                __('reporting::reporting.opened'),
                __('reporting::reporting.clicked'),
                __('reporting::reporting.converted'),
            ],
            'datasets' => [
                [
                    'data' => [$totalSent, $delivered, $opened, $clicked, $converted],
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.7)',
                        'rgba(16, 185, 129, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(139, 92, 246, 0.7)',
                        'rgba(236, 72, 153, 0.7)',
                    ],
                ],
            ],
        ];

        // Table data
        $this->tableData = $campaigns->map(function ($campaign) {
            $name = is_array(json_decode($campaign->name, true))
                ? (json_decode($campaign->name, true)['en'] ?? $campaign->name)
                : $campaign->name;

            $openRate = $campaign->delivered_count > 0
                ? ($campaign->opened_count / $campaign->delivered_count) * 100
                : 0;

            return [
                'campaign' => $name,
                'channel' => ucfirst($campaign->channel),
                'recipients' => $campaign->recipients_count,
                'delivered' => $campaign->delivered_count,
                'open_rate' => $this->formatPercentage($openRate),
            ];
        })->toArray();
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
                'indexAxis' => 'y',
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => __('reporting::reporting.campaign_funnel'),
                    ],
                    'legend' => ['display' => false],
                ],
                'scales' => [
                    'x' => ['beginAtZero' => true],
                ],
            ],
        ];
    }

    protected function getTableColumns(): array
    {
        return [
            ['key' => 'campaign', 'label' => __('reporting::reporting.campaign')],
            ['key' => 'channel', 'label' => __('reporting::reporting.channel')],
            ['key' => 'recipients', 'label' => __('reporting::reporting.recipients')],
            ['key' => 'delivered', 'label' => __('reporting::reporting.delivered')],
            ['key' => 'open_rate', 'label' => __('reporting::reporting.open_rate')],
        ];
    }
}
