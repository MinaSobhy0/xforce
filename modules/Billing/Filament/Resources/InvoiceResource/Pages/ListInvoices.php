<?php

namespace Modules\Billing\Filament\Resources\InvoiceResource\Pages;

use Modules\Billing\Filament\Resources\InvoiceResource;
use Modules\Billing\Models\Invoice;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends BaseListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            InvoiceResource\Widgets\InvoiceStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('billing::billing.tabs.all')),

            'draft' => Tab::make(__('billing::billing.tabs.draft'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Invoice::STATUS_DRAFT))
                ->badge(Invoice::where('status', Invoice::STATUS_DRAFT)->count())
                ->badgeColor('gray'),

            'issued' => Tab::make(__('billing::billing.tabs.issued'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Invoice::STATUS_ISSUED))
                ->badge(Invoice::where('status', Invoice::STATUS_ISSUED)->count())
                ->badgeColor('info'),

            'unpaid' => Tab::make(__('billing::billing.tabs.unpaid'))
                ->modifyQueryUsing(fn (Builder $query) => $query->unpaid())
                ->badge(Invoice::unpaid()->count())
                ->badgeColor('warning'),

            'paid' => Tab::make(__('billing::billing.tabs.paid'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Invoice::STATUS_PAID))
                ->badge(Invoice::where('status', Invoice::STATUS_PAID)->count())
                ->badgeColor('success'),

            'overdue' => Tab::make(__('billing::billing.tabs.overdue'))
                ->modifyQueryUsing(fn (Builder $query) => $query->overdue())
                ->badge(Invoice::overdue()->count())
                ->badgeColor('danger'),
        ];
    }
}
