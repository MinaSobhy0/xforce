<?php

namespace Modules\Inventory\Filament\Resources\StockLocationResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Inventory\Models\StockLevel;

class StockLevelsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockLevels';

    protected static ?string $title = 'Stock at Location';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('inventory::inventory.sections.stock');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('product.sku')
                    ->label(__('inventory::inventory.fields.sku'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('inventory::inventory.fields.product'))
                    ->getStateUsing(fn (StockLevel $record) => $record->product?->getTranslation('name', app()->getLocale()))
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('quantity_on_hand')
                    ->label(__('inventory::inventory.fields.quantity_on_hand'))
                    ->sortable()
                    ->badge()
                    ->color(fn (StockLevel $record) => $record->isLowStock() ? 'danger' : ($record->isOutOfStock() ? 'gray' : 'success')),

                Tables\Columns\TextColumn::make('quantity_reserved')
                    ->label(__('inventory::inventory.fields.quantity_reserved'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('available_quantity')
                    ->label(__('inventory::inventory.fields.available'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_restock_at')
                    ->label(__('inventory::inventory.fields.last_restock'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_stock')
                    ->label(__('inventory::inventory.filters.has_stock'))
                    ->query(fn ($query) => $query->where('quantity_on_hand', '>', 0))
                    ->toggle(),

                Tables\Filters\Filter::make('low_stock')
                    ->label(__('inventory::inventory.filters.low_stock'))
                    ->query(fn ($query) => $query->whereRaw('quantity_on_hand <= (SELECT reorder_point FROM products WHERE products.id = stock_levels.product_id)'))
                    ->toggle(),
            ])
            ->defaultSort('product.sku')
            ->actions([
                Tables\Actions\Action::make('view_product')
                    ->label(__('inventory::inventory.actions.view_product'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (StockLevel $record) => \Modules\Inventory\Filament\Resources\ProductResource::getUrl('view', ['record' => $record->product_id])),
            ])
            ->bulkActions([]);
    }
}
