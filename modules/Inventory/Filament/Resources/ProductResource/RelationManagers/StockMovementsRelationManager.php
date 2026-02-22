<?php

namespace Modules\Inventory\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\RelationManagers\BaseRelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Core\Models\Branch;
use Modules\Inventory\Models\StockMovement;

class StockMovementsRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'stockMovements';

    protected static ?string $title = 'Stock History';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('inventory::inventory.fields.date'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('inventory::inventory.fields.branch'))
                    ->getStateUsing(fn (StockMovement $record) => $record->branch?->name),

                Tables\Columns\TextColumn::make('movement_type')
                    ->label(__('inventory::inventory.fields.movement_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => StockMovement::TYPES[$state] ?? $state)
                    ->color(fn ($state) => StockMovement::TYPE_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label(__('inventory::inventory.fields.quantity'))
                    ->formatStateUsing(fn ($state) => $state > 0 ? "+{$state}" : $state)
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('quantity_before')
                    ->label(__('inventory::inventory.fields.before')),

                Tables\Columns\TextColumn::make('quantity_after')
                    ->label(__('inventory::inventory.fields.after')),

                Tables\Columns\TextColumn::make('reference_type')
                    ->label(__('inventory::inventory.fields.reference'))
                    ->formatStateUsing(fn ($state) => $state ? ucfirst(str_replace('_', ' ', $state)) : '-'),

                Tables\Columns\TextColumn::make('notes')
                    ->label(__('inventory::inventory.fields.notes'))
                    ->limit(30)
                    ->tooltip(fn (StockMovement $record) => $record->notes),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label(__('inventory::inventory.fields.created_by')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('movement_type')
                    ->label(__('inventory::inventory.fields.movement_type'))
                    ->options(StockMovement::TYPES),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('inventory::inventory.fields.branch'))
                    ->relationship('branch', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Branch $record) => $record->name),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}
