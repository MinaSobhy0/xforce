<?php

namespace Modules\Inventory\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Filament\Resources\UomResource\Pages;

class UomResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = Uom::class;

    protected static ?string $moduleCode = 'inventory';

    protected static ?string $permissionKey = 'uoms';

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Inventory';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.units');
    }

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('inventory::inventory.navigation.uoms');
    }

    public static function getModelLabel(): string
    {
        return __('inventory::inventory.labels.uom');
    }

    public static function getPluralModelLabel(): string
    {
        return __('inventory::inventory.labels.uoms');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->label(__('inventory::inventory.fields.category'))
                            ->relationship('category', 'id')
                            ->getOptionLabelFromRecordUsing(fn (UomCategory $record) => $record->getTranslation('name', app()->getLocale()))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),

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

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('abbreviation')
                                    ->label(__('inventory::inventory.fields.abbreviation'))
                                    ->required()
                                    ->maxLength(20)
                                    ->unique(ignoreRecord: true),

                                Forms\Components\Select::make('uom_type')
                                    ->label(__('inventory::inventory.fields.uom_type'))
                                    ->options([
                                        Uom::TYPE_BIGGER => __('inventory::inventory.uom_types.bigger'),
                                        Uom::TYPE_REFERENCE => __('inventory::inventory.uom_types.reference'),
                                        Uom::TYPE_SMALLER => __('inventory::inventory.uom_types.smaller'),
                                    ])
                                    ->default(Uom::TYPE_REFERENCE)
                                    ->required()
                                    ->live(),

                                Forms\Components\TextInput::make('ratio')
                                    ->label(__('inventory::inventory.fields.ratio'))
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->helperText(__('inventory::inventory.helpers.ratio'))
                                    ->disabled(fn (Forms\Get $get) => $get('uom_type') === Uom::TYPE_REFERENCE),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Toggle::make('is_reference')
                                    ->label(__('inventory::inventory.fields.is_reference'))
                                    ->helperText(__('inventory::inventory.helpers.is_reference'))
                                    ->disabled(fn (Forms\Get $get) => $get('uom_type') !== Uom::TYPE_REFERENCE)
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                                        if ($state) {
                                            $set('ratio', 1);
                                        }
                                    }),

                                Forms\Components\TextInput::make('rounding_precision')
                                    ->label(__('inventory::inventory.fields.rounding_precision'))
                                    ->numeric()
                                    ->default(0.01)
                                    ->required()
                                    ->step(0.000001),

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
                    ->getStateUsing(fn (Uom $record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable(['name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('abbreviation')
                    ->label(__('inventory::inventory.fields.abbreviation'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label(__('inventory::inventory.fields.category'))
                    ->getStateUsing(fn (Uom $record) => $record->category?->getTranslation('name', app()->getLocale()))
                    ->sortable(),

                Tables\Columns\TextColumn::make('uom_type')
                    ->label(__('inventory::inventory.fields.uom_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("inventory::inventory.uom_types.{$state}"))
                    ->color(fn (string $state): string => Uom::TYPE_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('ratio')
                    ->label(__('inventory::inventory.fields.ratio'))
                    ->numeric(decimalPlaces: 6)
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_reference')
                    ->label(__('inventory::inventory.fields.is_reference'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('inventory::inventory.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label(__('inventory::inventory.fields.category'))
                    ->relationship('category', 'id')
                    ->getOptionLabelFromRecordUsing(fn (UomCategory $record) => $record->getTranslation('name', app()->getLocale())),

                Tables\Filters\SelectFilter::make('uom_type')
                    ->label(__('inventory::inventory.fields.uom_type'))
                    ->options([
                        Uom::TYPE_BIGGER => __('inventory::inventory.uom_types.bigger'),
                        Uom::TYPE_REFERENCE => __('inventory::inventory.uom_types.reference'),
                        Uom::TYPE_SMALLER => __('inventory::inventory.uom_types.smaller'),
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('inventory::inventory.fields.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Uom $record) => $record->products()->count() === 0 && $record->productsPurchaseUom()->count() === 0),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('category_id');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUoms::route('/'),
            'create' => Pages\CreateUom::route('/create'),
            'edit' => Pages\EditUom::route('/{record}/edit'),
        ];
    }
}
