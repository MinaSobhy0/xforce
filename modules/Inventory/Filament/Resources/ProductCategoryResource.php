<?php

namespace Modules\Inventory\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Filament\Resources\ProductCategoryResource\Pages;
use Illuminate\Database\Eloquent\Builder;

class ProductCategoryResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = ProductCategory::class;

    protected static ?string $moduleCode = 'inventory';

    protected static ?string $permissionKey = 'product_categories';

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 31;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('inventory::inventory.navigation.categories');
    }

    public static function getModelLabel(): string
    {
        return __('inventory::inventory.labels.category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('inventory::inventory.labels.categories');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('parent_id')
                            ->label(__('inventory::inventory.fields.parent_category'))
                            ->relationship(
                                'parent',
                                'id',
                                fn (Builder $query) => $query->whereNull('parent_id')->where('is_active', true)
                            )
                            ->getOptionLabelFromRecordUsing(fn (ProductCategory $record) => $record->getTranslation('name', app()->getLocale()))
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name.en')
                                    ->label(__('inventory::inventory.fields.name') . ' (English)')
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('name.ar')
                                    ->label(__('inventory::inventory.fields.name') . ' (Arabic)')
                                    ->required()
                                    ->maxLength(100),
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
                                Forms\Components\TextInput::make('sort_order')
                                    ->label(__('inventory::inventory.fields.sort_order'))
                                    ->numeric()
                                    ->default(0),

                                Forms\Components\Toggle::make('is_active')
                                    ->label(__('inventory::inventory.fields.is_active'))
                                    ->default(true),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('inventory::inventory.fields.name'))
                    ->getStateUsing(fn (ProductCategory $record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable(['name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label(__('inventory::inventory.fields.parent_category'))
                    ->getStateUsing(fn (ProductCategory $record) => $record->parent?->getTranslation('name', app()->getLocale()))
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('products_count')
                    ->label(__('inventory::inventory.fields.products'))
                    ->counts('products')
                    ->sortable(),

                Tables\Columns\TextColumn::make('children_count')
                    ->label(__('inventory::inventory.fields.subcategories'))
                    ->counts('children')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('inventory::inventory.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('inventory::inventory.fields.sort_order'))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label(__('inventory::inventory.fields.parent_category'))
                    ->relationship('parent', 'id')
                    ->getOptionLabelFromRecordUsing(fn (ProductCategory $record) => $record->getTranslation('name', app()->getLocale())),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('inventory::inventory.fields.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (ProductCategory $record) => $record->products()->count() === 0 && $record->children()->count() === 0),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductCategories::route('/'),
            'create' => Pages\CreateProductCategory::route('/create'),
            'edit' => Pages\EditProductCategory::route('/{record}/edit'),
        ];
    }
}
