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
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Filament\Resources\ProductResource\Pages;
use Modules\Inventory\Filament\Resources\ProductResource\RelationManagers;

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

                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Select::make('unit')
                                            ->label(__('inventory::inventory.fields.unit'))
                                            ->options(Product::UNITS)
                                            ->default(Product::UNIT_PCS)
                                            ->required(),

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
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('reorder_point')
                                            ->label(__('inventory::inventory.fields.reorder_point'))
                                            ->numeric()
                                            ->required()
                                            ->default(10)
                                            ->helperText('Alert when stock falls below this'),

                                        Forms\Components\TextInput::make('reorder_quantity')
                                            ->label(__('inventory::inventory.fields.reorder_quantity'))
                                            ->numeric()
                                            ->required()
                                            ->default(50)
                                            ->helperText('Suggested quantity to reorder'),

                                        Forms\Components\TextInput::make('lead_time_days')
                                            ->label(__('inventory::inventory.fields.lead_time_days'))
                                            ->numeric()
                                            ->required()
                                            ->default(7)
                                            ->helperText('Days to receive from supplier'),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('inventory::inventory.sections.settings'))
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Toggle::make('is_consumable')
                                            ->label(__('inventory::inventory.fields.is_consumable'))
                                            ->default(true)
                                            ->helperText('Used during treatments/appointments'),

                                        Forms\Components\Toggle::make('is_active')
                                            ->label(__('inventory::inventory.fields.is_active'))
                                            ->default(true),
                                    ]),
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
                                            ->options(fn () => ChartOfAccount::where('type', 'asset')
                                                ->pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Inventory asset account (Balance Sheet)'),

                                        Forms\Components\Select::make('stock_input_account_id')
                                            ->label(__('inventory::inventory.fields.stock_input_account'))
                                            ->options(fn () => ChartOfAccount::whereIn('type', ['liability', 'expense'])
                                                ->pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Account for stock receipts (e.g., Goods Received Not Invoiced)'),

                                        Forms\Components\Select::make('stock_output_account_id')
                                            ->label(__('inventory::inventory.fields.stock_output_account'))
                                            ->options(fn () => ChartOfAccount::where('type', 'expense')
                                                ->pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Account for stock consumption (e.g., Cost of Goods Sold)'),

                                        Forms\Components\Select::make('income_account_id')
                                            ->label('Income Account')
                                            ->options(fn () => ChartOfAccount::where('type', ChartOfAccount::TYPE_INCOME)
                                                ->where('is_active', true)
                                                ->orderBy('code')
                                                ->get()
                                                ->mapWithKeys(fn ($a) => [$a->id => "[{$a->code}] " . $a->getTranslation('name', app()->getLocale())]))
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Default account for sales invoices'),

                                        Forms\Components\Select::make('expense_account_id')
                                            ->label('Expense Account')
                                            ->options(fn () => ChartOfAccount::where('type', ChartOfAccount::TYPE_EXPENSE)
                                                ->where('is_active', true)
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

                Tables\Columns\IconColumn::make('is_consumable')
                    ->label(__('inventory::inventory.fields.is_consumable'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('inventory::inventory.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label(__('inventory::inventory.fields.category'))
                    ->relationship('category', 'id')
                    ->getOptionLabelFromRecordUsing(fn (ProductCategory $record) => $record->getTranslation('name', app()->getLocale())),

                Tables\Filters\TernaryFilter::make('is_consumable')
                    ->label(__('inventory::inventory.fields.is_consumable')),

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
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('sku')
                                    ->label(__('inventory::inventory.fields.sku')),

                                Infolists\Components\TextEntry::make('category.name')
                                    ->label(__('inventory::inventory.fields.category'))
                                    ->getStateUsing(fn (Product $record) => $record->category?->getTranslation('name', app()->getLocale())),

                                Infolists\Components\TextEntry::make('unit')
                                    ->label(__('inventory::inventory.fields.unit'))
                                    ->formatStateUsing(fn ($state) => Product::UNITS[$state] ?? $state),
                            ]),

                        Infolists\Components\TextEntry::make('name')
                            ->label(__('inventory::inventory.fields.name'))
                            ->getStateUsing(fn (Product $record) => $record->getTranslation('name', app()->getLocale())),

                        Infolists\Components\TextEntry::make('description')
                            ->label(__('inventory::inventory.fields.description'))
                            ->getStateUsing(fn (Product $record) => $record->getTranslation('description', app()->getLocale())),
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
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StockLevelsRelationManager::class,
            RelationManagers\StockMovementsRelationManager::class,
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
