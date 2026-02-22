<?php

namespace Modules\Inventory\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\RelationManagers\BaseRelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Modules\Core\Models\Branch;
use Modules\Inventory\Models\StockLevel;

class StockLevelsRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'stockLevels';

    protected static ?string $title = 'Stock Levels';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('branch_id')
                    ->label(__('inventory::inventory.fields.branch'))
                    ->relationship('branch', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Branch $record) => $record->name)
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('quantity_on_hand')
                    ->label(__('inventory::inventory.fields.quantity_on_hand'))
                    ->numeric()
                    ->required()
                    ->default(0),

                Forms\Components\TextInput::make('quantity_reserved')
                    ->label(__('inventory::inventory.fields.quantity_reserved'))
                    ->numeric()
                    ->default(0),

                Forms\Components\TextInput::make('quantity_on_order')
                    ->label(__('inventory::inventory.fields.quantity_on_order'))
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('inventory::inventory.fields.branch'))
                    ->getStateUsing(fn (StockLevel $record) => $record->branch?->name)
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity_on_hand')
                    ->label(__('inventory::inventory.fields.quantity_on_hand'))
                    ->sortable()
                    ->badge()
                    ->color(fn (StockLevel $record) => $record->isLowStock() ? 'danger' : ($record->isOutOfStock() ? 'gray' : 'success')),

                Tables\Columns\TextColumn::make('quantity_reserved')
                    ->label(__('inventory::inventory.fields.quantity_reserved'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity_on_order')
                    ->label(__('inventory::inventory.fields.quantity_on_order'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('available_quantity')
                    ->label(__('inventory::inventory.fields.available'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_restock_at')
                    ->label(__('inventory::inventory.fields.last_restock'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_count_at')
                    ->label(__('inventory::inventory.fields.last_count'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('adjust')
                    ->label(__('inventory::inventory.actions.adjust_stock'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('new_quantity')
                            ->label(__('inventory::inventory.fields.new_quantity'))
                            ->numeric()
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('inventory::inventory.fields.notes'))
                            ->rows(2),
                    ])
                    ->action(function (StockLevel $record, array $data) {
                        $record->adjustTo($data['new_quantity'], $data['notes'] ?? null);

                        Notification::make()
                            ->title(__('inventory::inventory.messages.stock_adjusted'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }
}
