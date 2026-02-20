<?php

namespace Modules\Reporting\Filament\Pages;

use Modules\GiftCards\Models\GiftCard;
use Modules\GiftCards\Models\GiftCardTransaction;
use Illuminate\Support\Facades\DB;

class GiftCardReportPage extends BaseReportPage
{
    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?int $navigationSort = 7;

    public static function getNavigationLabel(): string
    {
        return __('reporting::reporting.gift_card_report');
    }

    public function getTitle(): string
    {
        return __('reporting::reporting.gift_card_report');
    }

    protected function getReportTitle(): string
    {
        return __('reporting::reporting.gift_card_report');
    }

    protected function loadReportData(): void
    {
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();
        $branchId = $this->getBranchId();

        // Total gift cards sold in period
        $soldQuery = GiftCard::query()
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($branchId) {
            $soldQuery->where('branch_id', $branchId);
        }

        $cardsSold = $soldQuery->count();
        $totalSoldValue = $soldQuery->sum('initial_balance_minor');

        // Outstanding balance (all active cards)
        $outstandingQuery = GiftCard::query()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });

        if ($branchId) {
            $outstandingQuery->where('branch_id', $branchId);
        }

        $outstandingBalance = $outstandingQuery->sum('current_balance_minor');
        $activeCards = $outstandingQuery->count();

        // Redemptions in period
        $redemptionsQuery = GiftCardTransaction::query()
            ->where('type', 'redeem')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($branchId) {
            $redemptionsQuery->whereHas('giftCard', fn ($q) => $q->where('branch_id', $branchId));
        }

        $redemptionsCount = $redemptionsQuery->count();
        $redemptionsValue = $redemptionsQuery->sum('amount_minor');

        // Expired cards in period
        $expiredQuery = GiftCard::query()
            ->where('status', 'expired')
            ->whereBetween('expires_at', [$startDate, $endDate]);

        if ($branchId) {
            $expiredQuery->where('branch_id', $branchId);
        }

        $expiredCards = $expiredQuery->count();
        $expiredValue = $expiredQuery->sum('current_balance_minor');

        // Redemption rate
        $totalCards = GiftCard::when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count();
        $redeemedCards = GiftCard::where('status', 'redeemed')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();
        $redemptionRate = $totalCards > 0 ? ($redeemedCards / $totalCards) * 100 : 0;

        // Stats cards
        $this->stats = [
            [
                'label' => __('reporting::reporting.cards_sold'),
                'value' => number_format($cardsSold),
                'description' => $this->formatCurrency($totalSoldValue),
                'icon' => 'heroicon-o-gift',
                'color' => 'success',
            ],
            [
                'label' => __('reporting::reporting.outstanding_balance'),
                'value' => $this->formatCurrency($outstandingBalance),
                'description' => $activeCards . ' ' . __('reporting::reporting.active_cards'),
                'icon' => 'heroicon-o-banknotes',
                'color' => 'primary',
            ],
            [
                'label' => __('reporting::reporting.redemptions'),
                'value' => $this->formatCurrency($redemptionsValue),
                'description' => $redemptionsCount . ' ' . __('reporting::reporting.transactions'),
                'icon' => 'heroicon-o-shopping-bag',
                'color' => 'info',
            ],
            [
                'label' => __('reporting::reporting.redemption_rate'),
                'value' => $this->formatPercentage($redemptionRate),
                'description' => $expiredCards . ' ' . __('reporting::reporting.expired'),
                'icon' => 'heroicon-o-chart-pie',
                'color' => 'warning',
            ],
        ];

        // Daily sales for chart
        $dailySales = GiftCard::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(initial_balance_minor) as total')
            )
            ->orderBy('date')
            ->get();

        // Chart data
        $this->chartData = [
            'labels' => $dailySales->pluck('date')->map(fn ($d) => \Carbon\Carbon::parse($d)->format('M d'))->toArray(),
            'datasets' => [
                [
                    'label' => __('reporting::reporting.cards_sold'),
                    'data' => $dailySales->pluck('count')->toArray(),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.5)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'borderWidth' => 2,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => __('reporting::reporting.value') . ' (EGP)',
                    'data' => $dailySales->pluck('total')->map(fn ($v) => $v / 100)->toArray(),
                    'backgroundColor' => 'rgba(139, 92, 246, 0.5)',
                    'borderColor' => 'rgb(139, 92, 246)',
                    'borderWidth' => 2,
                    'yAxisID' => 'y1',
                ],
            ],
        ];

        // Top gift cards by value
        $topCards = GiftCard::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with('purchaser')
            ->orderByDesc('initial_balance_minor')
            ->limit(10)
            ->get();

        // Table data
        $this->tableData = $topCards->map(fn ($card) => [
            'code' => $card->code,
            'purchaser' => $card->purchaser?->full_name ?? __('reporting::reporting.anonymous'),
            'initial_value' => $this->formatCurrency($card->initial_balance_minor),
            'current_balance' => $this->formatCurrency($card->current_balance_minor),
            'status' => __('gift_cards::gift_cards.statuses.' . $card->status),
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
                        'text' => __('reporting::reporting.gift_card_sales'),
                    ],
                ],
                'scales' => [
                    'y' => [
                        'type' => 'linear',
                        'display' => true,
                        'position' => 'left',
                        'title' => ['display' => true, 'text' => __('reporting::reporting.count')],
                    ],
                    'y1' => [
                        'type' => 'linear',
                        'display' => true,
                        'position' => 'right',
                        'title' => ['display' => true, 'text' => __('reporting::reporting.value')],
                        'grid' => ['drawOnChartArea' => false],
                    ],
                ],
            ],
        ];
    }

    protected function getTableColumns(): array
    {
        return [
            ['key' => 'code', 'label' => __('reporting::reporting.code')],
            ['key' => 'purchaser', 'label' => __('reporting::reporting.purchaser')],
            ['key' => 'initial_value', 'label' => __('reporting::reporting.initial_value')],
            ['key' => 'current_balance', 'label' => __('reporting::reporting.current_balance')],
            ['key' => 'status', 'label' => __('reporting::reporting.status')],
        ];
    }
}
