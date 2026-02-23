<?php

namespace Modules\Inventory\Filament\Resources\InventoryAdjustmentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Inventory\Models\InventoryAdjustment;
use Modules\Inventory\Models\InventoryAdjustmentLine;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockLevel;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $recordTitleAttribute = 'product.name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label(__('inventory::inventory.fields.product'))
                    ->options(fn () => Product::active()->pluck('name', 'id')->map(fn ($name) => is_array($name) ? ($name[app()->getLocale()] ?? $name['en'] ?? reset($name)) : $name))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $product = Product::find($state);
                            $adjustment = $this->getOwnerRecord();

                            // Get current stock level
                            $stockLevel = StockLevel::where('product_id', $state)
                                ->where('branch_id', $adjustment->branch_id)
                                ->first();

                            $theoreticalQty = $stockLevel?->quantity_on_hand ?? 0;
                            $unitCost = $product?->cost_price_minor ?? 0;

                            $set('theoretical_qty', $theoreticalQty);
                            $set('counted_qty', $theoreticalQty);
                            $set('unit_cost_minor', $unitCost);
                        }
                    }),

                Forms\Components\Grid::make(4)
                    ->schema([
                        Forms\Components\TextInput::make('theoretical_qty')
                            ->label(__('inventory::inventory.fields.theoretical_qty'))
                            ->numeric()
                            ->disabled()
                            ->dehydrated(true)
                            ->helperText('System quantity'),

                        Forms\Components\TextInput::make('counted_qty')
                            ->label(__('inventory::inventory.fields.counted_qty'))
                            ->numeric()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $theoretical = $get('theoretical_qty') ?? 0;
                                $counted = $state ?? 0;
                                $unitCost = $get('unit_cost_minor') ?? 0;

                                $difference = $counted - $theoretical;
                                $valueAdjustment = $difference * $unitCost;

                                $set('difference_qty', $difference);
                                $set('value_adjustment_minor', $valueAdjustment);
                            })
                            ->helperText('Physical count'),

                        Forms\Components\TextInput::make('difference_qty')
                            ->label(__('inventory::inventory.fields.difference'))
                            ->numeric()
                            ->disabled()
                            ->dehydrated(true),

                        Forms\Components\TextInput::make('unit_cost_minor')
                            ->label(__('inventory::inventory.fields.unit_cost'))
                            ->numeric()
                            ->disabled()
                            ->dehydrated(true)
                            ->suffix('cents'),
                    ]),

                Forms\Components\Hidden::make('value_adjustment_minor'),

                Forms\Components\Textarea::make('notes')
                    ->label(__('inventory::inventory.fields.notes'))
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product.name')
            ->columns([
                Tables\Columns\TextColumn::make('product.sku')
                    ->label(__('inventory::inventory.fields.sku'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('inventory::inventory.fields.product'))
                    ->getStateUsing(fn (InventoryAdjustmentLine $record) => $record->product?->getTranslation('name', app()->getLocale()))
                    ->searchable(),

                Tables\Columns\TextColumn::make('theoretical_qty')
                    ->label(__('inventory::inventory.fields.theoretical_qty'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('counted_qty')
                    ->label(__('inventory::inventory.fields.counted_qty'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('difference_qty')
                    ->label(__('inventory::inventory.fields.difference'))
                    ->alignCenter()
                    ->color(fn (InventoryAdjustmentLine $record) => match(true) {
                        $record->difference_qty > 0 => 'success',
                        $record->difference_qty < 0 => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state > 0 ? "+{$state}" : $state),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label(__('inventory::inventory.fields.unit_cost'))
                    ->money(current_currency()),

                Tables\Columns\TextColumn::make('value_adjustment')
                    ->label(__('inventory::inventory.fields.value_adjustment'))
                    ->money(current_currency())
                    ->color(fn (InventoryAdjustmentLine $record) => match(true) {
                        $record->value_adjustment_minor > 0 => 'success',
                        $record->value_adjustment_minor < 0 => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = auth()->user()->tenant_id;
                        return $data;
                    })
                    ->visible(fn () => $this->getOwnerRecord()->isDraft()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->isDraft()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->isDraft()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => $this->getOwnerRecord()->isDraft()),
                ]),
            ]);
    }
}
