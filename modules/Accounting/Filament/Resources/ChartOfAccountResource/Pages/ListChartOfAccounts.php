<?php

namespace Modules\Accounting\Filament\Resources\ChartOfAccountResource\Pages;

use Modules\Accounting\Filament\Resources\ChartOfAccountResource;
use Modules\Accounting\Models\ChartOfAccount;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListChartOfAccounts extends BaseListRecords
{
    protected static string $resource = ChartOfAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),

            'asset' => Tab::make('Assets')
                ->modifyQueryUsing(fn (Builder $query) => $query->ofType(ChartOfAccount::TYPE_ASSET))
                ->badge(ChartOfAccount::ofType(ChartOfAccount::TYPE_ASSET)->count())
                ->badgeColor('primary'),

            'liability' => Tab::make('Liabilities')
                ->modifyQueryUsing(fn (Builder $query) => $query->ofType(ChartOfAccount::TYPE_LIABILITY))
                ->badge(ChartOfAccount::ofType(ChartOfAccount::TYPE_LIABILITY)->count())
                ->badgeColor('danger'),

            'equity' => Tab::make('Equity')
                ->modifyQueryUsing(fn (Builder $query) => $query->ofType(ChartOfAccount::TYPE_EQUITY))
                ->badge(ChartOfAccount::ofType(ChartOfAccount::TYPE_EQUITY)->count())
                ->badgeColor('info'),

            'revenue' => Tab::make('Revenue')
                ->modifyQueryUsing(fn (Builder $query) => $query->ofType(ChartOfAccount::TYPE_REVENUE))
                ->badge(ChartOfAccount::ofType(ChartOfAccount::TYPE_REVENUE)->count())
                ->badgeColor('success'),

            'expense' => Tab::make('Expenses')
                ->modifyQueryUsing(fn (Builder $query) => $query->ofType(ChartOfAccount::TYPE_EXPENSE))
                ->badge(ChartOfAccount::ofType(ChartOfAccount::TYPE_EXPENSE)->count())
                ->badgeColor('warning'),
        ];
    }
}
