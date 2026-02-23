<?php

namespace Modules\Billing\Filament\Resources\PaymentResource\Widgets;

use Modules\Billing\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaymentStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $today = Payment::today();
        $thisMonth = Payment::thisMonth();

        $todayTotal = $today->sum('amount_minor');
        $monthTotal = $thisMonth->sum('amount_minor');

        // Payment method breakdown for today (by journal type)
        $todayCash = $today->clone()->byJournalType('cash')->sum('amount_minor');
        $todayBank = $today->clone()->byJournalType('bank')->sum('amount_minor');

        return [
            Stat::make("Today's Payments", number_format($todayTotal / 100, 2) . ' ' . current_currency())
                ->description($today->count() . ' payments')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Cash Today', number_format($todayCash / 100, 2) . ' ' . current_currency())
                ->description('Cash payments')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Bank Today', number_format($todayBank / 100, 2) . ' ' . current_currency())
                ->description('Bank transfers')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('primary'),

            Stat::make('Month Total', number_format($monthTotal / 100, 2) . ' ' . current_currency())
                ->description($thisMonth->count() . ' payments this month')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),
        ];
    }
}
