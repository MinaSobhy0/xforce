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
        // Get types by category
        $assetTypes = array_keys(array_filter(ChartOfAccount::TYPE_CATEGORY, fn($cat) => $cat === 'asset'));
        $liabilityTypes = array_keys(array_filter(ChartOfAccount::TYPE_CATEGORY, fn($cat) => $cat === 'liability'));
        $equityTypes = array_keys(array_filter(ChartOfAccount::TYPE_CATEGORY, fn($cat) => $cat === 'equity'));
        $incomeTypes = array_keys(array_filter(ChartOfAccount::TYPE_CATEGORY, fn($cat) => $cat === 'income'));
        $expenseTypes = array_keys(array_filter(ChartOfAccount::TYPE_CATEGORY, fn($cat) => $cat === 'expense'));

        return [
            'all' => Tab::make('All'),

            'asset' => Tab::make('Assets')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('type', $assetTypes))
                ->badge(ChartOfAccount::whereIn('type', $assetTypes)->count())
                ->badgeColor('primary'),

            'liability' => Tab::make('Liabilities')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('type', $liabilityTypes))
                ->badge(ChartOfAccount::whereIn('type', $liabilityTypes)->count())
                ->badgeColor('danger'),

            'equity' => Tab::make('Equity')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('type', $equityTypes))
                ->badge(ChartOfAccount::whereIn('type', $equityTypes)->count())
                ->badgeColor('info'),

            'income' => Tab::make('Income')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('type', $incomeTypes))
                ->badge(ChartOfAccount::whereIn('type', $incomeTypes)->count())
                ->badgeColor('success'),

            'expense' => Tab::make('Expenses')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('type', $expenseTypes))
                ->badge(ChartOfAccount::whereIn('type', $expenseTypes)->count())
                ->badgeColor('warning'),
        ];
    }
}
