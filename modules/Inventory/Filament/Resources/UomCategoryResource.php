<?php

namespace Modules\Inventory\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Filament\Resources\UomCategoryResource\Pages;

class UomCategoryResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = UomCategory::class;

    protected static ?string $moduleCode = 'inventory';

    protected static ?string $permissionKey = 'products';

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 32;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('inventory::inventory.navigation.uom_categories');
    }

    public static function getModelLabel(): string
    {
        return __('inventory::inventory.labels.uom_category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('inventory::inventory.labels.uom_categories');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
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
                    ->getStateUsing(fn (UomCategory $record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable(['name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label(__('inventory::inventory.fields.description'))
                    ->getStateUsing(fn (UomCategory $record) => $record->getTranslation('description', app()->getLocale()))
                    ->limit(50)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('uoms_count')
                    ->label(__('inventory::inventory.fields.uoms'))
                    ->counts('uoms')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('inventory::inventory.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('inventory::inventory.fields.sort_order'))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('inventory::inventory.fields.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (UomCategory $record) => $record->uoms()->count() === 0),
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
            'index' => Pages\ListUomCategories::route('/'),
            'create' => Pages\CreateUomCategory::route('/create'),
            'edit' => Pages\EditUomCategory::route('/{record}/edit'),
        ];
    }
}
