<?php

namespace App\Filament\SuperAdmin\Resources\PlatformInvoiceResource\Widgets;

use App\Models\PlatformInvoice;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InvoiceStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $thisMonth = PlatformInvoice::whereMonth('period_start', now()->month)
            ->whereYear('period_start', now()->year);

        $invoiced = $thisMonth->sum('total_minor');
        $collected = $thisMonth->clone()->where('status', 'paid')->sum('total_minor');
        $outstanding = $thisMonth->clone()->whereIn('status', ['pending', 'overdue'])->sum('total_minor');
        $overdue = PlatformInvoice::where('status', 'overdue')->sum('total_minor');

        return [
            Stat::make('Invoiced This Month', 'EGP ' . number_format($invoiced / 100))
                ->description(PlatformInvoice::thisMonth()->count() . ' invoices')
                ->color('info'),

            Stat::make('Collected', 'EGP ' . number_format($collected / 100))
                ->description(PlatformInvoice::thisMonth()->paid()->count() . ' paid')
                ->color('success'),

            Stat::make('Outstanding', 'EGP ' . number_format($outstanding / 100))
                ->description(PlatformInvoice::thisMonth()->pending()->count() . ' pending')
                ->color('warning'),

            Stat::make('Overdue', 'EGP ' . number_format($overdue / 100))
                ->description(PlatformInvoice::overdue()->count() . ' overdue')
                ->color('danger'),
        ];
    }
}
