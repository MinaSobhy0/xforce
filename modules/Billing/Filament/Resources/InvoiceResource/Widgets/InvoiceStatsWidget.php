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
            Stat::make(__('billing::billing.stats.todays_revenue'), number_format($todayRevenue / 100, 2) . ' ' . current_currency())
                ->description(__('billing::billing.stats.total_payments_today'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make(__('billing::billing.stats.month_revenue'), number_format($monthRevenue / 100, 2) . ' ' . current_currency())
                ->description(__('billing::billing.stats.total_payments_month'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary'),

            Stat::make(__('billing::billing.stats.outstanding'), number_format($outstanding / 100, 2) . ' ' . current_currency())
                ->description(__('billing::billing.stats.unpaid_balance'))
                ->descriptionIcon('heroicon-m-clock')
                ->color($outstanding > 0 ? 'warning' : 'success'),

            Stat::make(__('billing::billing.stats.overdue_invoices'), $overdueCount)
                ->description(__('billing::billing.stats.invoices_past_due'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueCount > 0 ? 'danger' : 'success'),
        ];
    }
}
