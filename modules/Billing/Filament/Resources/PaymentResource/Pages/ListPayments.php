<?php

namespace Modules\Billing\Filament\Resources\PaymentResource\Pages;

use Modules\Billing\Filament\Resources\PaymentResource;
use Modules\Billing\Models\Payment;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
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
            'all' => Tab::make('All'),

            'today' => Tab::make('Today')
                ->modifyQueryUsing(fn (Builder $query) => $query->today())
                ->badge(Payment::today()->count()),

            'cash' => Tab::make('Cash')
                ->modifyQueryUsing(fn (Builder $query) => $query->byMethod(Payment::METHOD_CASH))
                ->badge(Payment::byMethod(Payment::METHOD_CASH)->count())
                ->badgeColor('success'),

            'card' => Tab::make('Card')
                ->modifyQueryUsing(fn (Builder $query) => $query->byMethod(Payment::METHOD_CARD))
                ->badge(Payment::byMethod(Payment::METHOD_CARD)->count())
                ->badgeColor('primary'),

            'bank' => Tab::make('Bank Transfer')
                ->modifyQueryUsing(fn (Builder $query) => $query->byMethod(Payment::METHOD_BANK_TRANSFER))
                ->badge(Payment::byMethod(Payment::METHOD_BANK_TRANSFER)->count())
                ->badgeColor('info'),
        ];
    }
}
