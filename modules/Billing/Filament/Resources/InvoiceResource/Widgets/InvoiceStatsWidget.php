<?php

namespace Modules\Billing\Filament\Resources\InvoiceResource\Widgets;

use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InvoiceStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $todayRevenue = Payment::today()->sum('amount_minor');
        $monthRevenue = Payment::thisMonth()->sum('amount_minor');
        $outstanding = Invoice::unpaid()->sum('total_minor') - Invoice::unpaid()->sum('paid_minor');
        $overdueCount = Invoice::overdue()->count();

        return [
            Stat::make("Today's Revenue", number_format($todayRevenue / 100, 2) . ' ' . current_currency())
                ->description('Total payments received today')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Month Revenue', number_format($monthRevenue / 100, 2) . ' ' . current_currency())
                ->description('Total payments this month')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary'),

            Stat::make('Outstanding', number_format($outstanding / 100, 2) . ' ' . current_currency())
                ->description('Unpaid invoice balance')
                ->descriptionIcon('heroicon-m-clock')
                ->color($outstanding > 0 ? 'warning' : 'success'),

            Stat::make('Overdue Invoices', $overdueCount)
                ->description('Invoices past due date')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueCount > 0 ? 'danger' : 'success'),
        ];
    }
}
