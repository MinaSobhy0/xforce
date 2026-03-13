<?php

namespace Modules\Inventory\Filament\Resources;

use App\Services\BranchContext;
use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Modules\Inventory\Filament\Resources\StockTransferResource\Pages;
use Modules\Inventory\Models\StockLocation;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;

class StockTransferResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = StockTransfer::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-up-down';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 60;

    protected static ?string $moduleCode = 'inventory';

    protected static ?string $permissionKey = 'stock_transfers';

    public static function getNavigationLabel(): string
    {
        return __('inventory::inventory.navigation.stock_transfers');
    }

    public static function getModelLabel(): string
    {
        return __('inventory::inventory.labels.stock_transfer');
    }

    public static function getPluralModelLabel(): string
    {
        return __('inventory::inventory.navigation.stock_transfers');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('inventory::inventory.sections.transfer_info'))
                    ->schema([
                        Forms\Components\Select::make('branch_id')
                            ->label(__('inventory::inventory.fields.branch'))
                            ->relationship('branch', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(fn () => BranchContext::currentId())
                            ->live(),

                        Forms\Components\TextInput::make('transfer_number')
                            ->label(__('inventory::inventory.fields.transfer_number'))
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),

                        Forms\Components\Select::make('source_location_id')
                            ->label(__('inventory::inventory.fields.source_location'))
                            ->relationship('sourceLocation', 'code')
                            ->getOptionLabelFromRecordUsing(fn (StockLocation $record) =>
                                $record->code
                                    ? "{$record->code} - " . $record->getTranslation('name', app()->getLocale())
                                    : $record->getTranslation('name', app()->getLocale())
                            )
                            ->options(function ($get) {
                                $branchId = $get('branch_id');
                                if (!$branchId) return [];
                                return StockLocation::where('branch_id', $branchId)
                                    ->where('location_type', StockLocation::TYPE_INTERNAL)
                                    ->active()
                                    ->orderBy('sort_order')
                                    ->get()
                                    ->mapWithKeys(fn ($loc) => [
                                        $loc->id => $loc->code
                                            ? "{$loc->code} - " . $loc->getTranslation('name', app()->getLocale())
                                            : $loc->getTranslation('name', app()->getLocale())
                                    ]);
                            })
                            ->default(function () {
                                $branchId = BranchContext::currentId();
                                if (!$branchId) return null;
                                return StockLocation::where('branch_id', $branchId)
                                    ->where('location_type', StockLocation::TYPE_INTERNAL)
                                    ->active()
                                    ->orderBy('sort_order')
                                    ->first()?->id;
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),

                        Forms\Components\Select::make('destination_location_id')
                            ->label(__('inventory::inventory.fields.destination_location'))
                            ->relationship('destinationLocation', 'code')
                            ->getOptionLabelFromRecordUsing(fn (StockLocation $record) =>
                                $record->code
                                    ? "{$record->code} - " . $record->getTranslation('name', app()->getLocale())
                                    : $record->getTranslation('name', app()->getLocale())
                            )
                            ->options(function ($get) {
                                $branchId = $get('branch_id');
                                $sourceId = $get('source_location_id');
                                if (!$branchId) return [];
                                return StockLocation::where('branch_id', $branchId)
                                    ->where('location_type', StockLocation::TYPE_INTERNAL)
                                    ->where('id', '!=', $sourceId)
                                    ->active()
                                    ->orderBy('sort_order')
                                    ->get()
                                    ->mapWithKeys(fn ($loc) => [
                                        $loc->id => $loc->code
                                            ? "{$loc->code} - " . $loc->getTranslation('name', app()->getLocale())
                                            : $loc->getTranslation('name', app()->getLocale())
                                    ]);
                            })
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\DateTimePicker::make('scheduled_date')
                            ->label(__('inventory::inventory.fields.scheduled_date'))
                            ->default(now())
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('inventory::inventory.fields.notes'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('inventory::inventory.sections.items'))
                    ->schema([
                        Forms\Components\Repeater::make('lines')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label(__('inventory::inventory.fields.product'))
                                    ->relationship('product', 'sku')
                                    ->getOptionLabelFromRecordUsing(fn (Product $record) =>
                                        "{$record->sku} - " . $record->getTranslation('name', app()->getLocale())
                                    )
                                    ->getSearchResultsUsing(function (string $search): array {
                                        return Product::query()
                                            ->where('is_active', true)
                                            ->where(function ($query) use ($search) {
                                                $query->where('sku', 'ilike', "%{$search}%")
                                                    ->orWhere('barcode', 'ilike', "%{$search}%")
                                                    ->orWhere('name', 'ilike', "%{$search}%");
                                            })
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn (Product $record) => [
                                                $record->id => "{$record->sku} - " . $record->getTranslation('name', app()->getLocale())
                                            ])
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                // Default to sales_uom (stock UOM)
                                                $set('uom_id', $product->sales_uom_id);
                                            }
                                        }
                                    })
                                    ->columnSpan(3),

                                Forms\Components\Select::make('uom_id')
                                    ->label(__('inventory::inventory.fields.uom'))
                                    ->options(function (Forms\Get $get) {
                                        $productId = $get('product_id');
                                        if (!$productId) {
                                            return [];
                                        }
                                        $product = Product::find($productId);
                                        if (!$product || !$product->salesUom) {
                                            return Uom::where('is_active', true)
                                                ->get()
                                                ->mapWithKeys(fn (Uom $uom) => [$uom->id => $uom->display_name]);
                                        }
                                        // Only show UOMs from the same category
                                        return Uom::where('is_active', true)
                                            ->where('category_id', $product->salesUom->category_id)
                                            ->get()
                                            ->mapWithKeys(fn (Uom $uom) => [$uom->id => $uom->display_name]);
                                    })
                                    ->required()
                                    ->searchable()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('quantity_planned')
                                    ->label(__('inventory::inventory.fields.quantity'))
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->default(1)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('quantity_done')
                                    ->label(__('inventory::inventory.fields.quantity_done'))
                                    ->numeric()
                                    ->disabled()
                                    ->default(0)
                                    ->columnSpan(1)
                                    ->visibleOn('edit'),
                            ])
                            ->columns(6)
                            ->reorderable(false)
                            ->addActionLabel(__('inventory::inventory.actions.add_product'))
                            ->minItems(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transfer_number')
                    ->label(__('inventory::inventory.fields.transfer_number'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('inventory::inventory.fields.branch'))
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('source_location_id')
                    ->label(__('inventory::inventory.fields.source_location'))
                    ->formatStateUsing(function ($state, StockTransfer $record) {
                        $location = $record->sourceLocation;
                        if (!$location) {
                            return $state; // Return ID if relationship not found
                        }
                        $name = $location->getTranslation('name', app()->getLocale());
                        return $location->code ? "{$location->code} - {$name}" : $name;
                    }),

                Tables\Columns\TextColumn::make('destination_location_id')
                    ->label(__('inventory::inventory.fields.destination_location'))
                    ->formatStateUsing(function ($state, StockTransfer $record) {
                        $location = $record->destinationLocation;
                        if (!$location) {
                            return $state; // Return ID if relationship not found
                        }
                        $name = $location->getTranslation('name', app()->getLocale());
                        return $location->code ? "{$location->code} - {$name}" : $name;
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('inventory::inventory.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("inventory::inventory.transfer_statuses.{$state}"))
                    ->color(fn (string $state): string => StockTransfer::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('lines_count')
                    ->label(__('inventory::inventory.fields.lines_count'))
                    ->counts('lines'),

                Tables\Columns\TextColumn::make('scheduled_date')
                    ->label(__('inventory::inventory.fields.scheduled_date'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_date')
                    ->label(__('inventory::inventory.fields.effective_date'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label(__('inventory::inventory.fields.created_by'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('inventory::inventory.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('branch_id')
                    ->label(__('inventory::inventory.fields.branch'))
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label(__('inventory::inventory.fields.status'))
                    ->options(fn () => collect(StockTransfer::STATUSES)->mapWithKeys(
                        fn ($label, $value) => [$value => __("inventory::inventory.transfer_statuses.{$value}")]
                    ))
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (StockTransfer $record) => $record->canEdit()),
                Tables\Actions\Action::make('confirm')
                    ->label(__('inventory::inventory.actions.confirm'))
                    ->icon('heroicon-o-check')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (StockTransfer $record) => $record->canConfirm())
                    ->action(function (StockTransfer $record) {
                        if ($record->confirm()) {
                            Notification::make()
                                ->title(__('inventory::inventory.messages.transfer_confirmed'))
                                ->success()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('process')
                    ->label(__('inventory::inventory.actions.process'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(__('inventory::inventory.messages.process_transfer_confirmation'))
                    ->visible(fn (StockTransfer $record) => $record->canProcess())
                    ->action(function (StockTransfer $record) {
                        if ($record->process()) {
                            Notification::make()
                                ->title(__('inventory::inventory.messages.transfer_completed'))
                                ->success()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('cancel')
                    ->label(__('inventory::inventory.actions.cancel'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (StockTransfer $record) => $record->canCancel())
                    ->action(function (StockTransfer $record) {
                        if ($record->cancel()) {
                            Notification::make()
                                ->title(__('inventory::inventory.messages.transfer_cancelled'))
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(false),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockTransfers::route('/'),
            'create' => Pages\CreateStockTransfer::route('/create'),
            'edit' => Pages\EditStockTransfer::route('/{record}/edit'),
            'view' => Pages\ViewStockTransfer::route('/{record}'),
        ];
    }
}
