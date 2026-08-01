<?php

namespace Modules\Assets\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Assets\Filament\Resources\AssetTypeResource\Pages;
use Modules\Assets\Models\AssetType;
use Modules\Accounting\Models\ChartOfAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use XLinic\Framework\Core\Filament\RelationManagers\ActivityLogRelationManager;

class AssetTypeResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = AssetType::class;

    protected static ?string $moduleCode = 'assets';

    protected static ?string $permissionKey = 'asset_types';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Finance';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.assets');
    }

    protected static ?int $navigationSort = 11;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('assets::assets.nav.asset_types');
    }

    public static function getModelLabel(): string
    {
        return __('assets::assets.asset_type.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('assets::assets.asset_type.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('assets::assets.asset_type.sections.basic'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('assets::assets.asset_type.fields.name'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label(__('assets::assets.asset_type.fields.description'))
                            ->rows(2)
                            ->maxLength(1000),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('assets::assets.asset_type.fields.is_active'))
                            ->default(true),
                    ])
                    ->columns(1),

                Forms\Components\Section::make(__('assets::assets.asset_type.sections.depreciation'))
                    ->schema([
                        Forms\Components\Select::make('depreciation_method')
                            ->label(__('assets::assets.asset_type.fields.depreciation_method'))
                            ->options(AssetType::DEPRECIATION_METHODS)
                            ->required()
                            ->default(AssetType::METHOD_STRAIGHT_LINE)
                            ->live(),

                        Forms\Components\TextInput::make('useful_life_years')
                            ->label(__('assets::assets.asset_type.fields.useful_life_years'))
                            ->numeric()
                            ->required()
                            ->default(5)
                            ->minValue(1)
                            ->maxValue(100)
                            ->suffix(__('years'))
                            ->hidden(fn (Forms\Get $get) => $get('depreciation_method') === AssetType::METHOD_NO_DEPRECIATION),

                        Forms\Components\TextInput::make('salvage_value_percent')
                            ->label(__('assets::assets.asset_type.fields.salvage_value_percent'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->hidden(fn (Forms\Get $get) => $get('depreciation_method') === AssetType::METHOD_NO_DEPRECIATION),

                        Forms\Components\TextInput::make('declining_balance_rate')
                            ->label(__('assets::assets.asset_type.fields.declining_balance_rate'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText('Leave empty to use double declining balance rate')
                            ->visible(fn (Forms\Get $get) => $get('depreciation_method') === AssetType::METHOD_DECLINING_BALANCE),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('assets::assets.asset_type.sections.accounts'))
                    ->schema([
                        Forms\Components\Select::make('fixed_asset_account_id')
                            ->label(__('assets::assets.asset_type.fields.fixed_asset_account'))
                            ->options(fn () => ChartOfAccount::where('type', ChartOfAccount::TYPE_FIXED_ASSET)
                                ->where('is_active', true)
                                ->get()
                                ->pluck('display_name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('accumulated_depreciation_account_id')
                            ->label(__('assets::assets.asset_type.fields.accumulated_depreciation_account'))
                            ->options(fn () => ChartOfAccount::where('type', ChartOfAccount::TYPE_FIXED_ASSET)
                                ->where('is_active', true)
                                ->get()
                                ->pluck('display_name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(fn (Forms\Get $get) => $get('depreciation_method') !== AssetType::METHOD_NO_DEPRECIATION),

                        Forms\Components\Select::make('depreciation_expense_account_id')
                            ->label(__('assets::assets.asset_type.fields.depreciation_expense_account'))
                            ->options(fn () => ChartOfAccount::whereIn('type', [ChartOfAccount::TYPE_DEPRECIATION, ChartOfAccount::TYPE_EXPENSE])
                                ->where('is_active', true)
                                ->get()
                                ->pluck('display_name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(fn (Forms\Get $get) => $get('depreciation_method') !== AssetType::METHOD_NO_DEPRECIATION),

                        Forms\Components\Select::make('gain_loss_account_id')
                            ->label(__('assets::assets.asset_type.fields.gain_loss_account'))
                            ->options(fn () => ChartOfAccount::whereIn('type', [ChartOfAccount::TYPE_OTHER_INCOME, ChartOfAccount::TYPE_EXPENSE])
                                ->where('is_active', true)
                                ->get()
                                ->pluck('display_name', 'id'))
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('assets::assets.asset_type.sections.behavior'))
                    ->schema([
                        Forms\Components\Toggle::make('auto_create_on_purchase')
                            ->label(__('assets::assets.asset_type.fields.auto_create_on_purchase'))
                            ->default(true)
                            ->helperText('Automatically create assets when products with this type are purchased'),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('assets::assets.asset_type.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('assets::assets.asset_type.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('depreciation_method')
                    ->label(__('assets::assets.asset_type.fields.depreciation_method'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AssetType::DEPRECIATION_METHODS[$state] ?? $state),

                Tables\Columns\TextColumn::make('useful_life_years')
                    ->label(__('assets::assets.asset_type.fields.useful_life_years'))
                    ->suffix(' years')
                    ->sortable(),

                Tables\Columns\TextColumn::make('salvage_value_percent')
                    ->label(__('assets::assets.asset_type.fields.salvage_value_percent'))
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('assets_count')
                    ->counts('assets')
                    ->label(__('assets::assets.asset.plural')),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('assets::assets.asset_type.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('assets::assets.asset_type.fields.is_active')),

                Tables\Filters\SelectFilter::make('depreciation_method')
                    ->label(__('assets::assets.asset_type.fields.depreciation_method'))
                    ->options(AssetType::DEPRECIATION_METHODS),
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

    public static function getRelations(): array
    {
        return [
            ActivityLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetTypes::route('/'),
            'create' => Pages\CreateAssetType::route('/create'),
            'edit' => Pages\EditAssetType::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['fixedAssetAccount', 'accumulatedDepreciationAccount', 'depreciationExpenseAccount']);
    }
}
