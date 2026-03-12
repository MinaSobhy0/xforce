<?php

namespace Modules\Inventory\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Assets\Models\AssetType;
use Modules\Inventory\Enums\ProductType;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Filament\Resources\ProductResource\Pages;
use Modules\Inventory\Filament\Resources\ProductResource\RelationManagers;
use XLinic\Framework\Core\Filament\RelationManagers\ActivityLogRelationManager;

class ProductResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = Product::class;

    protected static ?string $moduleCode = 'inventory';

    protected static ?string $permissionKey = 'products';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'sku';

    public static function getNavigationLabel(): string
    {
        return __('inventory::inventory.navigation.products');
    }

    public static function getModelLabel(): string
    {
        return __('inventory::inventory.labels.product');
    }

    public static function getPluralModelLabel(): string
    {
        return __('inventory::inventory.labels.products');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Product')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make(__('inventory::inventory.sections.basic_info'))
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('sku')
                                            ->label(__('inventory::inventory.fields.sku'))
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->placeholder('Auto-generated'),

                                        Forms\Components\Select::make('category_id')
                                            ->label(__('inventory::inventory.fields.category'))
                                            ->relationship('category', 'id')
                                            ->getOptionLabelFromRecordUsing(fn (ProductCategory $record) => $record->getTranslation('name', app()->getLocale()))
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('name.en')
                                            ->label(__('inventory::inventory.fields.name') . ' (English)')
                                            ->required()
                                            ->maxLength(200),

                                        Forms\Components\TextInput::make('name.ar')
                                            ->label(__('inventory::inventory.fields.name') . ' (Arabic)')
                                            ->required()
                                            ->maxLength(200),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Textarea::make('description.en')
                                            ->label(__('inventory::inventory.fields.description') . ' (English)')
                                            ->rows(3),

                                        Forms\Components\Textarea::make('description.ar')
                                            ->label(__('inventory::inventory.fields.description') . ' (Arabic)')
                                            ->rows(3),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('sales_uom_id')
                                            ->label(__('inventory::inventory.fields.sales_uom'))
                                            ->relationship('salesUom', 'id')
                                            ->getOptionLabelFromRecordUsing(fn (Uom $record) => $record->getTranslation('name', app()->getLocale()) . ' (' . $record->abbreviation . ')')
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->helperText(__('inventory::inventory.helpers.sales_uom')),

                                        Forms\Components\Select::make('purchase_uom_id')
                                            ->label(__('inventory::inventory.fields.purchase_uom'))
                                            ->options(function (Forms\Get $get) {
                                                $salesUomId = $get('sales_uom_id');
                                                if (!$salesUomId) {
                                                    return Uom::active()
                                                        ->get()
                                                        ->mapWithKeys(fn (Uom $uom) => [
                                                            $uom->id => $uom->getTranslation('name', app()->getLocale()) . ' (' . $uom->abbreviation . ')'
                                                        ]);
                                                }

                                                $salesUom = Uom::find($salesUomId);
                                                if (!$salesUom) {
                                                    return [];
                                                }

                                                return Uom::active()
                                                    ->where('category_id', $salesUom->category_id)
                                                    ->get()
                                                    ->mapWithKeys(fn (Uom $uom) => [
                                                        $uom->id => $uom->getTranslation('name', app()->getLocale()) . ' (' . $uom->abbreviation . ')'
                                                    ]);
                                            })
                                            ->searchable()
                                            ->helperText(__('inventory::inventory.helpers.purchase_uom')),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('barcode')
                                            ->label(__('inventory::inventory.fields.barcode'))
                                            ->maxLength(100),

                                        Forms\Components\FileUpload::make('image_url')
                                            ->label(__('inventory::inventory.fields.image'))
                                            ->image()
                                            ->directory('products'),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('inventory::inventory.sections.pricing'))
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('cost_price_minor')
                                            ->label(__('inventory::inventory.fields.cost_price'))
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->prefix(current_currency())
                                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                                            ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),

                                        Forms\Components\TextInput::make('sell_price_minor')
                                            ->label(__('inventory::inventory.fields.sell_price'))
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->prefix(current_currency())
                                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                                            ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('inventory::inventory.sections.stock'))
                            ->schema([
                                Forms\Components\Placeholder::make('consumable_notice')
                                    ->label('')
                                    ->content(__('inventory::inventory.product_type_descriptions.consumable'))
                                    ->visible(fn (Forms\Get $get) => $get('product_type') === ProductType::CONSUMABLE->value),

                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('reorder_point')
                                            ->label(__('inventory::inventory.fields.reorder_point'))
                                            ->numeric()
                                            ->required(fn (Forms\Get $get) => $get('product_type') === ProductType::STORABLE->value)
                                            ->default(10)
                                            ->helperText('Alert when stock falls below this'),

                                        Forms\Components\TextInput::make('reorder_quantity')
                                            ->label(__('inventory::inventory.fields.reorder_quantity'))
                                            ->numeric()
                                            ->required(fn (Forms\Get $get) => $get('product_type') === ProductType::STORABLE->value)
                                            ->default(50)
                                            ->helperText('Suggested quantity to reorder'),

                                        Forms\Components\TextInput::make('lead_time_days')
                                            ->label(__('inventory::inventory.fields.lead_time_days'))
                                            ->numeric()
                                            ->required(fn (Forms\Get $get) => $get('product_type') === ProductType::STORABLE->value)
                                            ->default(7)
                                            ->helperText('Days to receive from supplier'),
                                    ])
                                    ->visible(fn (Forms\Get $get) => $get('product_type') === ProductType::STORABLE->value),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('inventory::inventory.sections.settings'))
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Select::make('product_type')
                                            ->label(__('inventory::inventory.fields.product_type'))
                                            ->options(ProductType::options())
                                            ->default(ProductType::STORABLE->value)
                                            ->required()
                                            ->live()
                                            ->helperText(fn (Forms\Get $get) => $get('product_type')
                                                ? ProductType::tryFrom($get('product_type'))?->description()
                                                : __('inventory::inventory.helpers.product_type')),

                                        Forms\Components\Toggle::make('is_consumable')
                                            ->label(__('inventory::inventory.fields.is_consumable'))
                                            ->default(true)
                                            ->helperText('Used during treatments/appointments'),

                                        Forms\Components\Toggle::make('is_active')
                                            ->label(__('inventory::inventory.fields.is_active'))
                                            ->default(true),
                                    ]),

                                Forms\Components\Section::make(__('assets::assets.module_name'))
                                    ->description('Configure asset management for this product')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_asset')
                                            ->label(__('assets::assets.asset_type.fields.is_active'))
                                            ->helperText('Mark this product as a fixed asset')
                                            ->live(),

                                        Forms\Components\Select::make('asset_type_id')
                                            ->label(__('assets::assets.asset.fields.asset_type'))
                                            ->options(fn () => AssetType::active()->pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn (Forms\Get $get) => $get('is_asset'))
                                            ->required(fn (Forms\Get $get) => $get('is_asset'))
                                            ->helperText('When purchased, assets of this type will be created automatically'),
                                    ])
                                    ->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('inventory::inventory.sections.accounting'))
                            ->schema([
                                Forms\Components\Select::make('valuation_method')
                                    ->label(__('inventory::inventory.fields.valuation_method'))
                                    ->options(Product::VALUATION_METHODS)
                                    ->default(Product::VALUATION_AVERAGE)
                                    ->helperText('Method used to value inventory'),

                                Forms\Components\Section::make(__('inventory::inventory.sections.stock_accounts'))
                                    ->description('Configure accounting accounts for automatic journal entries')
                                    ->schema([
                                        Forms\Components\Select::make('stock_valuation_account_id')
                                            ->label(__('inventory::inventory.fields.stock_valuation_account'))
                                            ->options(fn () => ChartOfAccount::where('is_active', true)
                                                ->orderBy('code')
                                                ->get()
                                                ->mapWithKeys(fn ($a) => [$a->id => "[{$a->code}] " . $a->getTranslation('name', app()->getLocale())]))
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Inventory asset account (Balance Sheet)'),

                                        Forms\Components\Select::make('income_account_id')
                                            ->label('Income Account')
                                            ->options(fn () => ChartOfAccount::where('is_active', true)
                                                ->orderBy('code')
                                                ->get()
                                                ->mapWithKeys(fn ($a) => [$a->id => "[{$a->code}] " . $a->getTranslation('name', app()->getLocale())]))
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Default account for sales invoices'),

                                        Forms\Components\Select::make('expense_account_id')
                                            ->label('Expense Account')
                                            ->options(fn () => ChartOfAccount::where('is_active', true)
                                                ->orderBy('code')
                                                ->get()
                                                ->mapWithKeys(fn ($a) => [$a->id => "[{$a->code}] " . $a->getTranslation('name', app()->getLocale())]))
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Default account for vendor bills'),
                                    ])
                                    ->columns(3),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('')
                    ->circular()
                    ->size(40),

                Tables\Columns\TextColumn::make('sku')
                    ->label(__('inventory::inventory.fields.sku'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('inventory::inventory.fields.name'))
                    ->getStateUsing(fn (Product $record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable(['name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label(__('inventory::inventory.fields.category'))
                    ->getStateUsing(fn (Product $record) => $record->category?->getTranslation('name', app()->getLocale()))
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('total_stock')
                    ->label(__('inventory::inventory.fields.stock'))
                    ->getStateUsing(fn (Product $record) => $record->total_stock)
                    ->badge()
                    ->color(fn (Product $record) => $record->isLowStock() ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('cost_price')
                    ->label(__('inventory::inventory.fields.cost_price'))
                    ->money(current_currency())
                    ->sortable(),

                Tables\Columns\TextColumn::make('sell_price')
                    ->label(__('inventory::inventory.fields.sell_price'))
                    ->money(current_currency())
                    ->sortable(),

                Tables\Columns\TextColumn::make('salesUom.abbreviation')
                    ->label(__('inventory::inventory.fields.uom'))
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product_type')
                    ->label(__('inventory::inventory.fields.product_type'))
                    ->badge()
                    ->formatStateUsing(fn (ProductType $state): string => $state->label())
                    ->color(fn (ProductType $state): string => $state->color()),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('inventory::inventory.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label(__('inventory::inventory.fields.category'))
                    ->relationship('category', 'id')
                    ->getOptionLabelFromRecordUsing(fn (ProductCategory $record) => $record->getTranslation('name', app()->getLocale())),

                Tables\Filters\SelectFilter::make('product_type')
                    ->label(__('inventory::inventory.fields.product_type'))
                    ->options(ProductType::options()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('inventory::inventory.fields.is_active')),

                Tables\Filters\Filter::make('low_stock')
                    ->label(__('inventory::inventory.filters.low_stock'))
                    ->query(function ($query) {
                        return $query->whereHas('stockLevels', function ($q) {
                            $q->whereRaw('quantity_on_hand <= (SELECT reorder_point FROM products WHERE products.id = stock_levels.product_id)');
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('inventory::inventory.sections.basic_info'))
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\ImageEntry::make('image_url')
                                    ->label(__('inventory::inventory.fields.image'))
                                    ->circular()
                                    ->size(80),

                                Infolists\Components\TextEntry::make('sku')
                                    ->label(__('inventory::inventory.fields.sku')),

                                Infolists\Components\TextEntry::make('barcode')
                                    ->label(__('inventory::inventory.fields.barcode'))
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('category.name')
                                    ->label(__('inventory::inventory.fields.category'))
                                    ->getStateUsing(fn (Product $record) => $record->category?->getTranslation('name', app()->getLocale()))
                                    ->placeholder('-'),
                            ]),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('name_en')
                                    ->label(__('inventory::inventory.fields.name') . ' (English)')
                                    ->getStateUsing(fn (Product $record) => $record->getTranslation('name', 'en')),

                                Infolists\Components\TextEntry::make('name_ar')
                                    ->label(__('inventory::inventory.fields.name') . ' (Arabic)')
                                    ->getStateUsing(fn (Product $record) => $record->getTranslation('name', 'ar')),
                            ]),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('description_en')
                                    ->label(__('inventory::inventory.fields.description') . ' (English)')
                                    ->getStateUsing(fn (Product $record) => $record->getTranslation('description', 'en'))
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('description_ar')
                                    ->label(__('inventory::inventory.fields.description') . ' (Arabic)')
                                    ->getStateUsing(fn (Product $record) => $record->getTranslation('description', 'ar'))
                                    ->placeholder('-'),
                            ]),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('salesUom.name')
                                    ->label(__('inventory::inventory.fields.sales_uom'))
                                    ->getStateUsing(fn (Product $record) => $record->salesUom ? $record->salesUom->getTranslation('name', app()->getLocale()) . ' (' . $record->salesUom->abbreviation . ')' : '-'),

                                Infolists\Components\TextEntry::make('purchaseUom.name')
                                    ->label(__('inventory::inventory.fields.purchase_uom'))
                                    ->getStateUsing(fn (Product $record) => $record->purchaseUom ? $record->purchaseUom->getTranslation('name', app()->getLocale()) . ' (' . $record->purchaseUom->abbreviation . ')' : '-'),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('inventory::inventory.sections.pricing'))
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('cost_price')
                                    ->label(__('inventory::inventory.fields.cost_price'))
                                    ->money(current_currency()),

                                Infolists\Components\TextEntry::make('sell_price')
                                    ->label(__('inventory::inventory.fields.sell_price'))
                                    ->money(current_currency()),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('inventory::inventory.sections.stock'))
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('total_stock')
                                    ->label(__('inventory::inventory.fields.total_stock'))
                                    ->badge()
                                    ->color(fn (Product $record) => $record->isLowStock() ? 'danger' : 'success'),

                                Infolists\Components\TextEntry::make('reorder_point')
                                    ->label(__('inventory::inventory.fields.reorder_point')),

                                Infolists\Components\TextEntry::make('reorder_quantity')
                                    ->label(__('inventory::inventory.fields.reorder_quantity')),

                                Infolists\Components\TextEntry::make('lead_time_days')
                                    ->label(__('inventory::inventory.fields.lead_time_days'))
                                    ->suffix(' days'),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('inventory::inventory.sections.settings'))
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('product_type')
                                    ->label(__('inventory::inventory.fields.product_type'))
                                    ->badge()
                                    ->formatStateUsing(fn (ProductType $state): string => $state->label())
                                    ->color(fn (ProductType $state): string => $state->color()),

                                Infolists\Components\IconEntry::make('is_consumable')
                                    ->label(__('inventory::inventory.fields.is_consumable'))
                                    ->boolean(),

                                Infolists\Components\IconEntry::make('is_active')
                                    ->label(__('inventory::inventory.fields.is_active'))
                                    ->boolean(),

                                Infolists\Components\IconEntry::make('is_asset')
                                    ->label(__('assets::assets.asset_type.fields.is_active'))
                                    ->boolean(),
                            ]),

                        Infolists\Components\TextEntry::make('assetType.name')
                            ->label(__('assets::assets.asset.fields.asset_type'))
                            ->visible(fn (Product $record) => $record->is_asset)
                            ->placeholder('-'),
                    ]),

                Infolists\Components\Section::make(__('inventory::inventory.sections.accounting'))
                    ->schema([
                        Infolists\Components\TextEntry::make('valuation_method')
                            ->label(__('inventory::inventory.fields.valuation_method'))
                            ->formatStateUsing(fn ($state) => Product::VALUATION_METHODS[$state] ?? $state),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('stockValuationAccount.code')
                                    ->label(__('inventory::inventory.fields.stock_valuation_account'))
                                    ->getStateUsing(fn (Product $record) => $record->stockValuationAccount
                                        ? "[{$record->stockValuationAccount->code}] " . $record->stockValuationAccount->getTranslation('name', app()->getLocale())
                                        : '-'),

                                Infolists\Components\TextEntry::make('incomeAccount.code')
                                    ->label('Income Account')
                                    ->getStateUsing(fn (Product $record) => $record->incomeAccount
                                        ? "[{$record->incomeAccount->code}] " . $record->incomeAccount->getTranslation('name', app()->getLocale())
                                        : '-'),

                                Infolists\Components\TextEntry::make('expenseAccount.code')
                                    ->label('Expense Account')
                                    ->getStateUsing(fn (Product $record) => $record->expenseAccount
                                        ? "[{$record->expenseAccount->code}] " . $record->expenseAccount->getTranslation('name', app()->getLocale())
                                        : '-'),
                            ]),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StockLevelsRelationManager::class,
            RelationManagers\StockMovementsRelationManager::class,
            ActivityLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
