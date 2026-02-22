<?php

namespace Modules\Inventory\Filament\Resources\PurchaseOrderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\PurchaseOrderLine;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Order Lines';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label(__('inventory::inventory.fields.product'))
                    ->relationship('product', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Product $record) => "[{$record->sku}] " . $record->getTranslation('name', app()->getLocale()))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state) {
                            $product = Product::find($state);
                            if ($product) {
                                $set('unit_price_minor', $product->cost_price_minor);
                            }
                        }
                    }),

                Forms\Components\TextInput::make('quantity')
                    ->label(__('inventory::inventory.fields.quantity'))
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->minValue(1),

                Forms\Components\TextInput::make('unit_price_minor')
                    ->label(__('inventory::inventory.fields.unit_price'))
                    ->numeric()
                    ->required()
                    ->suffix('cents'),

                Forms\Components\Textarea::make('notes')
                    ->label(__('inventory::inventory.fields.notes'))
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('product.sku')
                    ->label(__('inventory::inventory.fields.sku'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('inventory::inventory.fields.product'))
                    ->getStateUsing(fn (PurchaseOrderLine $record) => $record->product?->getTranslation('name', app()->getLocale()))
                    ->searchable(['name']),

                Tables\Columns\TextColumn::make('quantity')
                    ->label(__('inventory::inventory.fields.quantity'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity_received')
                    ->label(__('inventory::inventory.fields.received'))
                    ->sortable()
                    ->badge()
                    ->color(fn (PurchaseOrderLine $record) => match (true) {
                        $record->isFullyReceived() => 'success',
                        $record->isPartiallyReceived() => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('remaining_quantity')
                    ->label(__('inventory::inventory.fields.remaining'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label(__('inventory::inventory.fields.unit_price'))
                    ->money(current_currency()),

                Tables\Columns\TextColumn::make('line_total')
                    ->label(__('inventory::inventory.fields.total'))
                    ->money(current_currency()),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => $this->ownerRecord->isEditable()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => $this->ownerRecord->isEditable()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => $this->ownerRecord->isEditable()),
            ])
            ->bulkActions([]);
    }
}
