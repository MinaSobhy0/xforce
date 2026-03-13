<?php

namespace Modules\Inventory\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Branch;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockLevel;
use Modules\Inventory\Models\StockLocation;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Services\StockValuationService;

class StockReportPage extends Page implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 50;

    protected static string $view = 'inventory::filament.pages.stock-report';

    public ?string $filterBranchId = null;
    public ?string $filterLocationId = null;
    public ?string $filterCategoryId = null;
    public bool $showZeroStock = false;

    public static function getNavigationLabel(): string
    {
        return __('inventory::inventory.stock_report.navigation');
    }

    public function getTitle(): string
    {
        return __('inventory::inventory.stock_report.title');
    }

    public function mount(): void
    {
        $this->filterBranchId = current_branch_id();
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('export')
                ->label(__('inventory::inventory.stock_report.export'))
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    // TODO: Export to CSV/Excel
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('product.sku')
                    ->label(__('inventory::inventory.fields.sku'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('inventory::inventory.fields.product'))
                    ->formatStateUsing(fn ($record) => $record->product?->getTranslation('name', app()->getLocale()))
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('location.name')
                    ->label(__('inventory::inventory.fields.location'))
                    ->formatStateUsing(fn ($record) => $record->location?->getTranslation('name', app()->getLocale()) ?? '-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('inventory::inventory.fields.branch'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('quantity_on_hand')
                    ->label(__('inventory::inventory.stock_report.qty_on_hand'))
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->color(fn ($state) => $state <= 0 ? 'danger' : ($state <= 10 ? 'warning' : 'success')),

                Tables\Columns\TextColumn::make('quantity_reserved')
                    ->label(__('inventory::inventory.stock_report.qty_reserved'))
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('available_quantity')
                    ->label(__('inventory::inventory.stock_report.qty_available'))
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label(__('inventory::inventory.stock_report.unit_cost'))
                    ->getStateUsing(function ($record) {
                        // Always calculate from stock movement layers (remaining inventory)
                        $product = $record->product;
                        if (!$product) return 0;

                        // Calculate weighted average from remaining receipt layers
                        $layers = StockMovement::where('product_id', $product->id)
                            ->when($record->branch_id, fn($q) => $q->where('branch_id', $record->branch_id))
                            ->whereIn('movement_type', [
                                StockMovement::TYPE_PURCHASE_RECEIVE,
                                StockMovement::TYPE_IN,
                                StockMovement::TYPE_RETURN,
                            ])
                            ->where('remaining_quantity', '>', 0)
                            ->selectRaw('SUM(remaining_quantity) as total_qty, SUM(remaining_quantity * unit_cost_minor) as total_value')
                            ->first();

                        if ($layers && $layers->total_qty > 0) {
                            return (int) ($layers->total_value / $layers->total_qty);
                        }

                        // Fallback to product cost if no layers found
                        return $product->cost_price_minor ?? 0;
                    })
                    ->formatStateUsing(fn ($state) => number_format(($state ?? 0) / 100, 2) . ' EGP')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('stock_value')
                    ->label(__('inventory::inventory.stock_report.stock_value'))
                    ->getStateUsing(function ($record) {
                        $product = $record->product;
                        if (!$product) return 0;

                        $qty = $record->quantity_on_hand ?? 0;
                        if ($qty <= 0) return 0;

                        // Always calculate from stock movement layers
                        $value = StockMovement::where('product_id', $product->id)
                            ->when($record->branch_id, fn($q) => $q->where('branch_id', $record->branch_id))
                            ->whereIn('movement_type', [
                                StockMovement::TYPE_PURCHASE_RECEIVE,
                                StockMovement::TYPE_IN,
                                StockMovement::TYPE_RETURN,
                            ])
                            ->where('remaining_quantity', '>', 0)
                            ->selectRaw('SUM(remaining_quantity * unit_cost_minor) as total_value')
                            ->value('total_value');

                        if ($value && $value > 0) {
                            return $value;
                        }

                        // Fallback to product cost if no layers
                        return $qty * ($product->cost_price_minor ?? 0);
                    })
                    ->formatStateUsing(fn ($state) => number_format(($state ?? 0) / 100, 2) . ' EGP')
                    ->alignEnd()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('product.valuation_method')
                    ->label(__('inventory::inventory.stock_report.valuation_method'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('inventory::inventory.fields.branch'))
                    ->options(fn () => Branch::active()->pluck('name', 'id'))
                    ->default($this->filterBranchId),

                Tables\Filters\SelectFilter::make('location_id')
                    ->label(__('inventory::inventory.fields.location'))
                    ->options(fn () => StockLocation::where('is_active', true)
                        ->get()
                        ->mapWithKeys(fn ($loc) => [$loc->id => $loc->getTranslation('name', app()->getLocale())]))
                    ->searchable(),

                Tables\Filters\SelectFilter::make('category_id')
                    ->label(__('inventory::inventory.fields.category'))
                    ->relationship('product.category', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('show_zero_stock')
                    ->label(__('inventory::inventory.stock_report.show_zero_stock'))
                    ->placeholder(__('inventory::inventory.stock_report.hide_zero'))
                    ->trueLabel(__('inventory::inventory.stock_report.show_all'))
                    ->falseLabel(__('inventory::inventory.stock_report.only_zero'))
                    ->queries(
                        true: fn (Builder $query) => $query,
                        false: fn (Builder $query) => $query->where('quantity_on_hand', '<=', 0),
                        blank: fn (Builder $query) => $query->where('quantity_on_hand', '>', 0),
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('viewMovements')
                    ->label(__('inventory::inventory.stock_report.view_movements'))
                    ->icon('heroicon-o-arrows-right-left')
                    ->modalHeading(fn ($record) => __('inventory::inventory.stock_report.movements_for', [
                        'product' => $record->product?->getTranslation('name', app()->getLocale())
                    ]))
                    ->modalContent(fn ($record) => view('inventory::filament.pages.partials.stock-movements-modal', [
                        'movements' => StockMovement::where('product_id', $record->product_id)
                            ->when($record->location_id, fn ($q) => $q->where(function ($sq) use ($record) {
                                $sq->where('source_location_id', $record->location_id)
                                    ->orWhere('destination_location_id', $record->location_id);
                            }))
                            ->with(['sourceLocation', 'destinationLocation', 'createdBy'])
                            ->orderByDesc('created_at')
                            ->limit(50)
                            ->get(),
                    ]))
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close')),
            ])
            ->defaultSort('product.sku')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->poll(null);
    }

    protected function getTableQuery(): Builder
    {
        return StockLevel::query()
            ->with(['product', 'branch', 'location'])
            ->whereHas('product', fn ($q) => $q->where('is_active', true));
    }

    public function getTableRecordKey($record): string
    {
        return (string) $record->id;
    }

    public function getStockSummaryStats(): array
    {
        $query = StockLevel::query()
            ->whereHas('product', fn ($q) => $q->where('is_active', true));

        // Apply branch filter if set
        if ($this->filterBranchId) {
            $query->where('branch_id', $this->filterBranchId);
        }

        $totalProducts = $query->distinct('product_id')->count('product_id');
        $totalQuantity = $query->sum('quantity_on_hand');

        // Calculate total value from FIFO layers (remaining_quantity * unit_cost_minor)
        $fifoValue = DB::table('stock_movements')
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->where('products.is_active', true)
            ->whereIn('stock_movements.movement_type', ['purchase_receive', 'in', 'return'])
            ->where('stock_movements.remaining_quantity', '>', 0)
            ->when($this->filterBranchId, fn ($q) => $q->where('stock_movements.branch_id', $this->filterBranchId))
            ->selectRaw('SUM(stock_movements.remaining_quantity * stock_movements.unit_cost_minor) as total')
            ->value('total') ?? 0;

        // For non-FIFO products, calculate from stock levels * product cost
        $nonFifoValue = DB::table('stock_levels')
            ->join('products', 'stock_levels.product_id', '=', 'products.id')
            ->where('products.is_active', true)
            ->where('products.valuation_method', '!=', 'fifo')
            ->when($this->filterBranchId, fn ($q) => $q->where('stock_levels.branch_id', $this->filterBranchId))
            ->selectRaw('SUM(stock_levels.quantity_on_hand * products.cost_price_minor) as total')
            ->value('total') ?? 0;

        $totalValue = $fifoValue + $nonFifoValue;

        // Low stock count
        $lowStockCount = DB::table('stock_levels')
            ->join('products', 'stock_levels.product_id', '=', 'products.id')
            ->where('products.is_active', true)
            ->whereColumn('stock_levels.quantity_on_hand', '<=', 'products.reorder_point')
            ->where('stock_levels.quantity_on_hand', '>', 0)
            ->when($this->filterBranchId, fn ($q) => $q->where('stock_levels.branch_id', $this->filterBranchId))
            ->count();

        return [
            'total_products' => $totalProducts,
            'total_quantity' => $totalQuantity,
            'total_value' => $totalValue,
            'low_stock_count' => $lowStockCount,
        ];
    }
}
