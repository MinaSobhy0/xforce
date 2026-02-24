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
        $todayCount = $today->count();
        $monthCount = $thisMonth->count();

        // Payment method breakdown for today (by journal type)
        $todayCash = $today->clone()->byJournalType('cash')->sum('amount_minor');
        $todayBank = $today->clone()->byJournalType('bank')->sum('amount_minor');

        return [
            Stat::make(__('billing::billing.stats.todays_payments'), number_format($todayTotal / 100, 2) . ' ' . current_currency())
                ->description(__('billing::billing.stats.payments_count', ['count' => $todayCount]))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make(__('billing::billing.stats.cash_today'), number_format($todayCash / 100, 2) . ' ' . current_currency())
                ->description(__('billing::billing.stats.cash_payments'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make(__('billing::billing.stats.bank_today'), number_format($todayBank / 100, 2) . ' ' . current_currency())
                ->description(__('billing::billing.stats.bank_transfers'))
                ->descriptionIcon('heroicon-m-building-library')
                ->color('primary'),

            Stat::make(__('billing::billing.stats.month_total'), number_format($monthTotal / 100, 2) . ' ' . current_currency())
                ->description(__('billing::billing.stats.payments_this_month', ['count' => $monthCount]))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),
        ];
    }
}
