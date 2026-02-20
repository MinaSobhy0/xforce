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

        // Payment method breakdown for today
        $todayCash = $today->clone()->byMethod(Payment::METHOD_CASH)->sum('amount_minor');
        $todayCard = $today->clone()->byMethod(Payment::METHOD_CARD)->sum('amount_minor');

        return [
            Stat::make("Today's Payments", number_format($todayTotal / 100, 2) . ' ' . config('app.currency_symbol', 'EGP'))
                ->description($today->count() . ' payments')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Cash Today', number_format($todayCash / 100, 2) . ' ' . config('app.currency_symbol', 'EGP'))
                ->description('Cash payments')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Card Today', number_format($todayCard / 100, 2) . ' ' . config('app.currency_symbol', 'EGP'))
                ->description('Card payments')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('primary'),

            Stat::make('Month Total', number_format($monthTotal / 100, 2) . ' ' . config('app.currency_symbol', 'EGP'))
                ->description($thisMonth->count() . ' payments this month')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),
        ];
    }
}
