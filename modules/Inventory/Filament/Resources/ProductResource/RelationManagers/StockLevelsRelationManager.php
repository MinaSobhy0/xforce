<?php

namespace Modules\Inventory\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Modules\Core\Models\Branch;
use Modules\Inventory\Models\StockLevel;
use Modules\Inventory\Models\StockLocation;
use Modules\Inventory\Services\StockMoveService;

class StockLevelsRelationManager extends RelationManager
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
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Forms\Set $set) => $set('location_id', null)),

                Forms\Components\Select::make('location_id')
                    ->label(__('inventory::inventory.fields.location'))
                    ->options(function (Forms\Get $get) {
                        $branchId = $get('branch_id');
                        if (!$branchId) return [];
                        return StockLocation::where('branch_id', $branchId)
                            ->where('location_type', StockLocation::TYPE_INTERNAL)
                            ->active()
                            ->orderBy('sort_order')
                            ->get()
                            ->pluck('indented_name', 'id');
                    })
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

                Tables\Columns\TextColumn::make('location.code')
                    ->label(__('inventory::inventory.fields.location'))
                    ->description(fn (StockLevel $record) => $record->location?->getTranslation('name', app()->getLocale()))
                    ->searchable()
                    ->placeholder('-'),

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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_count_at')
                    ->label(__('inventory::inventory.fields.last_count'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('inventory::inventory.fields.branch'))
                    ->relationship('branch', 'name'),

                Tables\Filters\SelectFilter::make('location_id')
                    ->label(__('inventory::inventory.fields.location'))
                    ->relationship('location', 'code'),
            ])
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
                        $newQuantity = (float) $data['new_quantity'];
                        $currentQuantity = (float) $record->quantity_on_hand;
                        $difference = $newQuantity - $currentQuantity;

                        // Skip if no change
                        if ($difference == 0) {
                            Notification::make()
                                ->title(__('inventory::inventory.messages.no_change'))
                                ->info()
                                ->send();
                            return;
                        }

                        $product = $record->product;
                        $location = $record->location;

                        // Validate product tracks inventory
                        if (!$product || !$product->tracksInventory()) {
                            Notification::make()
                                ->title(__('inventory::inventory.messages.product_not_trackable'))
                                ->danger()
                                ->send();
                            return;
                        }

                        // Validate location exists
                        if (!$location) {
                            Notification::make()
                                ->title(__('inventory::inventory.messages.location_required'))
                                ->danger()
                                ->send();
                            return;
                        }

                        // Use StockMoveService to create proper adjustment with journal entries
                        $stockMoveService = app(StockMoveService::class);
                        $stockMoveService->createAdjustment(
                            $product,
                            $location,
                            $difference,
                            null, // uses product's sales_uom
                            'stock_level_adjustment',
                            (string) $record->id,
                            $data['notes'] ?? null
                        );

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
