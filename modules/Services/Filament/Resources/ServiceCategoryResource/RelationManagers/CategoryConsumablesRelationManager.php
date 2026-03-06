<?php

namespace Modules\Services\Filament\Resources\ServiceCategoryResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Inventory\Models\Product;

class CategoryConsumablesRelationManager extends RelationManager
{
    protected static string $relationship = 'consumables';

    protected static ?string $title = 'Consumables';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label(__('services::services.consumables.product'))
                    ->options(function () {
                        return Product::query()
                            ->where('is_consumable', true)
                            ->where('is_active', true)
                            ->get()
                            ->mapWithKeys(fn ($product) => [
                                $product->id => ($product->getTranslation('name', app()->getLocale()) ?? $product->sku) . " ({$product->sku})"
                            ]);
                    })
                    ->required()
                    ->searchable(),

                Forms\Components\TextInput::make('quantity')
                    ->label(__('services::services.consumables.quantity'))
                    ->helperText(__('services::services.consumables.quantity_help'))
                    ->numeric()
                    ->default(1)
                    ->minValue(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label(__('services::services.consumables.sku'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('services::services.consumables.product'))
                    ->formatStateUsing(fn ($record) => $record->getTranslation('name', app()->getLocale()) ?? $record->sku)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('unit')
                    ->label(__('services::services.consumables.unit'))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('pivot.quantity')
                    ->label(__('services::services.consumables.quantity'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label(__('services::services.consumables.category'))
                    ->formatStateUsing(fn ($record) => $record->category?->getTranslation('name', app()->getLocale()) ?? '-')
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label(__('services::services.consumables.add_consumable'))
                    ->icon('heroicon-o-plus')
                    ->modalHeading(__('services::services.consumables.add_consumable'))
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        Forms\Components\Select::make('recordId')
                            ->label(__('services::services.consumables.product'))
                            ->options(function () {
                                $attachedIds = $this->ownerRecord->consumables()->pluck('products.id')->toArray();
                                return Product::query()
                                    ->where('is_consumable', true)
                                    ->where('is_active', true)
                                    ->whereNotIn('id', $attachedIds)
                                    ->get()
                                    ->mapWithKeys(fn ($product) => [
                                        $product->id => ($product->getTranslation('name', app()->getLocale()) ?? $product->sku) . " ({$product->sku})"
                                    ]);
                            })
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('quantity')
                            ->label(__('services::services.consumables.quantity'))
                            ->numeric()
                            ->default(1)
                            ->minValue(1),
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('updateQuantity')
                    ->label(__('services::services.consumables.update_quantity'))
                    ->icon('heroicon-o-pencil')
                    ->form([
                        Forms\Components\TextInput::make('quantity')
                            ->label(__('services::services.consumables.quantity'))
                            ->numeric()
                            ->default(fn ($record) => $record->pivot->quantity)
                            ->minValue(1)
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $this->ownerRecord->consumables()->updateExistingPivot(
                            $record->id,
                            ['quantity' => $data['quantity']]
                        );
                    }),
                Tables\Actions\DetachAction::make()
                    ->label(__('services::services.actions.remove')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->label(__('services::services.actions.remove_selected')),
                ]),
            ]);
    }
}
