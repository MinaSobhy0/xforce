<?php

namespace Modules\GiftCards\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Modules\GiftCards\Services\GiftCardService;

class MyGiftCardStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $stats = app(GiftCardService::class)->getStaffStatistics(Auth::id());

        return [
            Stat::make(__('giftcards::giftcards.stats.cards_available'), $stats['available'] ?? 0)
                ->description(__('giftcards::giftcards.staff_dashboard.available_cards'))
                ->descriptionIcon('heroicon-m-gift')
                ->color('primary'),

            Stat::make(__('giftcards::giftcards.stats.total_issued'), $stats['assigned'] ?? 0)
                ->description(__('giftcards::giftcards.staff_dashboard.cards_by_denomination'))
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('success'),

            Stat::make(__('giftcards::giftcards.stats.cards_sold'), $stats['sold'] ?? 0)
                ->description(__('giftcards::giftcards.staff_dashboard.my_statistics'))
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('info'),

            Stat::make(__('giftcards::giftcards.stats.sales_value'), $stats['formatted_sales_value'] ?? format_money(0))
                ->description(__('giftcards::giftcards.stats.total_value'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),
        ];
    }
}
