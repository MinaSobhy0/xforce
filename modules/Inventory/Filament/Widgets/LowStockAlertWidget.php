<?php

namespace Modules\Inventory\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Inventory\Models\StockLevel;

class LowStockAlertWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return __('inventory::inventory.widgets.low_stock_alerts');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockLevel::query()
                    ->with(['product', 'branch'])
                    ->join('products', 'stock_levels.product_id', '=', 'products.id')
                    ->whereRaw('stock_levels.quantity_on_hand <= products.reorder_point')
                    ->orderByRaw('stock_levels.quantity_on_hand - products.reorder_point ASC')
                    ->select('stock_levels.*')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('product.sku')
                    ->label(__('inventory::inventory.fields.sku'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('inventory::inventory.labels.product'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '') : $state)
                    ->limit(30),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('inventory::inventory.widgets.branch'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '') : $state),

                Tables\Columns\TextColumn::make('quantity_on_hand')
                    ->label(__('inventory::inventory.widgets.on_hand'))
                    ->alignCenter()
                    ->color(fn ($record) => $record->quantity_on_hand <= 0 ? 'danger' : 'warning'),

                Tables\Columns\TextColumn::make('product.reorder_point')
                    ->label(__('inventory::inventory.fields.reorder_point'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('shortage')
                    ->label(__('inventory::inventory.widgets.shortage'))
                    ->getStateUsing(fn ($record) => max(0, ($record->product->reorder_point ?? 0) - $record->quantity_on_hand))
                    ->alignCenter()
                    ->color('danger')
                    ->weight('bold'),
            ])
            ->emptyStateHeading(__('inventory::inventory.widgets.no_low_stock'))
            ->emptyStateDescription(__('inventory::inventory.widgets.all_stock_levels_ok'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated(false);
    }
}
