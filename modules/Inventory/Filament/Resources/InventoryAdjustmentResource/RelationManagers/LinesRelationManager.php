<?php

namespace Modules\Inventory\Filament\Resources\InventoryAdjustmentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Models\InventoryAdjustmentLine;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockLevel;
use Modules\Inventory\Models\Uom;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('inventory::inventory.sections.adjustment_lines');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label(__('inventory::inventory.fields.product'))
                    ->options(function () {
                        return Product::query()
                            ->where('product_type', 'storable')
                            ->limit(100)
                            ->get()
                            ->mapWithKeys(fn ($product) => [
                                $product->id => "[{$product->sku}] " . $product->getTranslation('name', app()->getLocale())
                            ]);
                    })
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search) {
                        return Product::query()
                            ->where('product_type', 'storable')
                            ->where(function ($query) use ($search) {
                                $query->where('sku', 'ilike', "%{$search}%")
                                    ->orWhereRaw("name->>'en' ILIKE ?", ["%{$search}%"])
                                    ->orWhereRaw("name->>'ar' ILIKE ?", ["%{$search}%"]);
                            })
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn ($product) => [
                                $product->id => "[{$product->sku}] " . $product->getTranslation('name', app()->getLocale())
                            ]);
                    })
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if (!$state) return;

                        $product = Product::find($state);
                        if ($product) {
                            $set('uom_id', $product->sales_uom_id);
                            $set('unit_cost_minor', $product->cost_price_minor ?? 0);

                            // Get theoretical qty from stock level
                            $adjustment = $this->ownerRecord;
                            $query = StockLevel::where('product_id', $product->id)
                                ->where('branch_id', $adjustment->branch_id);

                            if ($adjustment->location_id) {
                                $query->where('location_id', $adjustment->location_id);
                            }

                            $stockLevel = $query->first();
                            $theoreticalQty = $stockLevel?->quantity_on_hand ?? 0;
                            $set('theoretical_qty', $theoreticalQty);
                        }
                    })
                    ->disabled(fn (?InventoryAdjustmentLine $record) => $record !== null)
                    ->columnSpan(2),

                Forms\Components\Select::make('uom_id')
                    ->label(__('inventory::inventory.fields.uom'))
                    ->options(function (Forms\Get $get) {
                        $productId = $get('product_id');
                        if (!$productId) return [];

                        $product = Product::find($productId);
                        if (!$product || !$product->salesUom) return [];

                        $categoryId = $product->salesUom->category_id;
                        return Uom::where('category_id', $categoryId)
                            ->active()
                            ->get()
                            ->mapWithKeys(fn ($uom) => [
                                $uom->id => $uom->getTranslation('name', app()->getLocale()) . ' (' . $uom->abbreviation . ')'
                            ]);
                    })
                    ->searchable()
                    ->preload()
                    ->columnSpan(1),

                Forms\Components\TextInput::make('theoretical_qty')
                    ->label(__('inventory::inventory.fields.theoretical_qty'))
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated(true)
                    ->columnSpan(1),

                Forms\Components\TextInput::make('counted_qty')
                    ->label(__('inventory::inventory.fields.counted_qty'))
                    ->numeric()
                    ->required()
                    ->columnSpan(1),

                Forms\Components\Hidden::make('difference_qty'),
                Forms\Components\Hidden::make('unit_cost_minor'),
                Forms\Components\Hidden::make('value_adjustment_minor'),
            ])
            ->columns(5);
    }

    public function table(Table $table): Table
    {
        $isEditable = $this->ownerRecord->isDraft();

        return $table
            ->recordTitleAttribute('product_id')
            ->columns([
                Tables\Columns\TextColumn::make('product.sku')
                    ->label(__('inventory::inventory.fields.sku'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('inventory::inventory.fields.product'))
                    ->getStateUsing(fn ($record) => $record->product?->getTranslation('name', app()->getLocale()))
                    ->searchable(query: function ($query, $search) {
                        $query->whereHas('product', function ($q) use ($search) {
                            $q->whereRaw("name->>'en' ILIKE ?", ["%{$search}%"])
                              ->orWhereRaw("name->>'ar' ILIKE ?", ["%{$search}%"]);
                        });
                    })
                    ->wrap()
                    ->limit(30),

                Tables\Columns\SelectColumn::make('uom_id')
                    ->label(__('inventory::inventory.fields.uom'))
                    ->options(function ($record) {
                        if (!$record->product || !$record->product->salesUom) {
                            return Uom::active()->pluck('abbreviation', 'id')->toArray();
                        }
                        $categoryId = $record->product->salesUom->category_id;
                        return Uom::where('category_id', $categoryId)
                            ->active()
                            ->pluck('abbreviation', 'id')
                            ->toArray();
                    })
                    ->disabled(!$isEditable),

                Tables\Columns\TextColumn::make('theoretical_qty')
                    ->label(__('inventory::inventory.fields.theoretical_qty'))
                    ->alignCenter(),

                Tables\Columns\TextInputColumn::make('counted_qty')
                    ->label(__('inventory::inventory.fields.counted_qty'))
                    ->type('number')
                    ->rules(['required', 'numeric', 'min:0'])
                    ->disabled(!$isEditable)
                    ->alignCenter()
                    ->afterStateUpdated(fn ($record) => $this->recalculate($record)),

                Tables\Columns\TextColumn::make('difference_qty')
                    ->label(__('inventory::inventory.fields.difference'))
                    ->alignCenter()
                    ->color(fn ($state) => match(true) {
                        $state > 0 => 'success',
                        $state < 0 => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state > 0 ? "+{$state}" : $state),

                Tables\Columns\TextColumn::make('value_adjustment')
                    ->label(__('inventory::inventory.fields.value_adjustment'))
                    ->money(current_currency())
                    ->color(fn ($record) => match(true) {
                        $record->value_adjustment_minor > 0 => 'success',
                        $record->value_adjustment_minor < 0 => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_difference')
                    ->label(__('inventory::inventory.filters.has_difference'))
                    ->query(fn ($query) => $query->where('difference_qty', '!=', 0)),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible($isEditable)
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['difference_qty'] = ($data['counted_qty'] ?? 0) - ($data['theoretical_qty'] ?? 0);
                        $data['value_adjustment_minor'] = $data['difference_qty'] * ($data['unit_cost_minor'] ?? 0);
                        return $data;
                    })
                    ->after(fn () => $this->ownerRecord->recalculateTotals()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible($isEditable)
                    ->after(fn ($record) => $this->recalculate($record)),
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->visible($isEditable)
                    ->after(fn () => $this->ownerRecord->recalculateTotals()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible($isEditable)
                        ->after(fn () => $this->ownerRecord->recalculateTotals()),
                ]),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->defaultSort('product.sku');
    }

    protected function recalculate($record): void
    {
        $record->refresh();
        $record->calculateDifference();
        $record->save();
        $this->ownerRecord->recalculateTotals();
    }
}
