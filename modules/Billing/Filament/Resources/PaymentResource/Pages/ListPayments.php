<?php

namespace Modules\Billing\Filament\Resources\PaymentResource\Pages;

use Modules\Billing\Filament\Resources\PaymentResource;
use Modules\Billing\Models\Payment;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends BaseListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PaymentResource\Widgets\PaymentStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('billing::billing.tabs.all')),

            'receive' => Tab::make(__('billing::billing.tabs.receive'))
                ->modifyQueryUsing(fn (Builder $query) => $query->received())
                ->badge(Payment::received()->count())
                ->badgeColor('success')
                ->icon('heroicon-o-arrow-down-tray'),

            'send' => Tab::make(__('billing::billing.tabs.send'))
                ->modifyQueryUsing(fn (Builder $query) => $query->sent())
                ->badge(Payment::sent()->count())
                ->badgeColor('danger')
                ->icon('heroicon-o-arrow-up-tray'),

            'today' => Tab::make(__('billing::billing.tabs.today'))
                ->modifyQueryUsing(fn (Builder $query) => $query->today())
                ->badge(Payment::today()->count()),

            'cash' => Tab::make(__('billing::billing.tabs.cash'))
                ->modifyQueryUsing(fn (Builder $query) => $query->byJournalType('cash'))
                ->badge(Payment::byJournalType('cash')->count())
                ->badgeColor('success'),

            'bank' => Tab::make(__('billing::billing.tabs.bank'))
                ->modifyQueryUsing(fn (Builder $query) => $query->byJournalType('bank'))
                ->badge(Payment::byJournalType('bank')->count())
                ->badgeColor('info'),
        ];
    }
}
