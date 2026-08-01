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
use Illuminate\Database\Eloquent\Builder;
use Modules\Inventory\Filament\Resources\StockLocationResource\Pages;
use Modules\Inventory\Models\StockLocation;

class StockLocationResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = StockLocation::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Inventory';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.stock');
    }

    protected static ?int $navigationSort = 10;

    protected static ?string $moduleCode = 'inventory';

    protected static ?string $permissionKey = 'stock_locations';

    public static function getNavigationLabel(): string
    {
        return __('inventory::inventory.navigation.stock_locations');
    }

    public static function getModelLabel(): string
    {
        return __('inventory::inventory.labels.stock_location');
    }

    public static function getPluralModelLabel(): string
    {
        return __('inventory::inventory.navigation.stock_locations');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('inventory::inventory.sections.basic_info'))
                    ->schema([
                        Forms\Components\Select::make('branch_id')
                            ->label(__('inventory::inventory.fields.branch'))
                            ->relationship('branch', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(fn () => BranchContext::currentId()),

                        Forms\Components\Select::make('parent_id')
                            ->label(__('inventory::inventory.fields.parent_location'))
                            ->relationship(
                                'parent',
                                'code',
                                fn (Builder $query, $get) => $query
                                    ->where('branch_id', $get('branch_id'))
                                    ->whereIn('location_type', [StockLocation::TYPE_VIEW, StockLocation::TYPE_INTERNAL])
                            )
                            ->getOptionLabelFromRecordUsing(fn (StockLocation $record) => $record->indented_name)
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\TextInput::make('code')
                            ->label(__('inventory::inventory.fields.code'))
                            ->required()
                            ->maxLength(30)
                            ->unique(
                                table: 'stock_locations',
                                column: 'code',
                                ignoreRecord: true,
                                modifyRuleUsing: fn ($rule, $get) => $rule->where('branch_id', $get('branch_id'))
                            ),

                        Forms\Components\TextInput::make('name.en')
                            ->label(__('inventory::inventory.fields.name') . ' (EN)')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('name.ar')
                            ->label(__('inventory::inventory.fields.name') . ' (AR)')
                            ->maxLength(100),

                        Forms\Components\Select::make('location_type')
                            ->label(__('inventory::inventory.fields.location_type'))
                            ->options(fn () => collect(StockLocation::TYPES)->mapWithKeys(
                                fn ($label, $value) => [$value => __("inventory::inventory.location_types.{$value}")]
                            ))
                            ->required()
                            ->default(StockLocation::TYPE_INTERNAL),

                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('inventory::inventory.fields.sort_order'))
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('inventory::inventory.sections.settings'))
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('inventory::inventory.fields.is_active'))
                            ->default(true),

                        Forms\Components\Toggle::make('is_scrap_location')
                            ->label(__('inventory::inventory.fields.is_scrap_location'))
                            ->default(false),

                        Forms\Components\Toggle::make('is_return_location')
                            ->label(__('inventory::inventory.fields.is_return_location'))
                            ->default(false),

                        Forms\Components\Toggle::make('is_treatment_default')
                            ->label(__('inventory::inventory.fields.is_treatment_default'))
                            ->helperText(__('inventory::inventory.fields.is_treatment_default_help'))
                            ->default(false),
                    ])
                    ->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('inventory::inventory.fields.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('full_path_name')
                    ->label(__('inventory::inventory.fields.name'))
                    ->searchable(query: fn (Builder $query, string $search) =>
                        $query->whereRaw("name->>'en' ILIKE ?", ["%{$search}%"])
                            ->orWhereRaw("name->>'ar' ILIKE ?", ["%{$search}%"])
                    ),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('inventory::inventory.fields.branch'))
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('location_type')
                    ->label(__('inventory::inventory.fields.location_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("inventory::inventory.location_types.{$state}"))
                    ->color(fn (string $state): string => StockLocation::TYPE_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('total_stock')
                    ->label(__('inventory::inventory.fields.total_stock'))
                    ->numeric()
                    ->alignEnd()
                    ->visible(fn () => true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('inventory::inventory.fields.is_active'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_scrap_location')
                    ->label(__('inventory::inventory.fields.is_scrap_location'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_treatment_default')
                    ->label(__('inventory::inventory.fields.is_treatment_default'))
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('inventory::inventory.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('branch_id')
                    ->label(__('inventory::inventory.fields.branch'))
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('location_type')
                    ->label(__('inventory::inventory.fields.location_type'))
                    ->options(fn () => collect(StockLocation::TYPES)->mapWithKeys(
                        fn ($label, $value) => [$value => __("inventory::inventory.location_types.{$value}")]
                    ))
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('inventory::inventory.fields.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \Modules\Inventory\Filament\Resources\StockLocationResource\RelationManagers\StockLevelsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockLocations::route('/'),
            'create' => Pages\CreateStockLocation::route('/create'),
            'edit' => Pages\EditStockLocation::route('/{record}/edit'),
            'view' => Pages\ViewStockLocation::route('/{record}'),
        ];
    }
}
