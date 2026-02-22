<?php

namespace Modules\Inventory\Filament\Resources\SupplierResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Inventory\Models\PurchaseOrder;

class PurchaseOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'purchaseOrders';

    protected static ?string $title = 'Purchase Orders';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order_number')
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label(__('inventory::inventory.fields.order_number'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('inventory::inventory.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => PurchaseOrder::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => PurchaseOrder::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('order_date')
                    ->label(__('inventory::inventory.fields.order_date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label(__('inventory::inventory.fields.total'))
                    ->money(current_currency())
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_date')
                    ->label(__('inventory::inventory.fields.expected_date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('received_date')
                    ->label(__('inventory::inventory.fields.received_date'))
                    ->date()
                    ->sortable()
                    ->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('inventory::inventory.fields.status'))
                    ->options(PurchaseOrder::STATUSES),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (PurchaseOrder $record) => route('filament.tenant.resources.purchase-orders.view', $record)),
            ])
            ->bulkActions([])
            ->defaultSort('order_date', 'desc');
    }
}
