<?php

namespace Modules\Assets\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Assets\Filament\Resources\AssetResource\Pages;
use Modules\Assets\Filament\Resources\AssetResource\RelationManagers;
use Modules\Assets\Models\Asset;
use Modules\Assets\Models\AssetType;
use Modules\Assets\Services\AssetService;
use Modules\Core\Models\Branch;
use Modules\Auth\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use XLinic\Framework\Core\Filament\RelationManagers\ActivityLogRelationManager;

class AssetResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = Asset::class;

    protected static ?string $moduleCode = 'assets';

    protected static ?string $permissionKey = 'assets';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('assets::assets.nav.assets');
    }

    public static function getModelLabel(): string
    {
        return __('assets::assets.asset.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('assets::assets.asset.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('assets::assets.asset.sections.basic'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('assets::assets.asset.fields.name'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('asset_type_id')
                            ->label(__('assets::assets.asset.fields.asset_type'))
                            ->relationship('assetType', 'name')
                            ->options(fn () => AssetType::active()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('branch_id')
                            ->label(__('assets::assets.asset.fields.branch'))
                            ->relationship('branch', 'name')
                            ->default(fn () => current_branch_id())
                            ->disabled(fn () => current_branch_id() !== null)
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('status')
                            ->label(__('assets::assets.asset.fields.status'))
                            ->options(Asset::STATUSES)
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('assets::assets.asset.sections.acquisition'))
                    ->schema([
                        Forms\Components\DatePicker::make('acquisition_date')
                            ->label(__('assets::assets.asset.fields.acquisition_date'))
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('acquisition_cost_minor')
                            ->label(__('assets::assets.asset.fields.acquisition_cost'))
                            ->numeric()
                            ->required()
                            ->suffix(current_currency())
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => (int) ($state * 100)),

                        Forms\Components\Select::make('acquisition_method')
                            ->label(__('assets::assets.asset.fields.acquisition_method'))
                            ->options(Asset::ACQUISITION_METHODS)
                            ->default(Asset::ACQUISITION_PURCHASE)
                            ->required(),

                        Forms\Components\DatePicker::make('depreciation_start_date')
                            ->label(__('assets::assets.asset.fields.depreciation_start_date'))
                            ->helperText('Leave empty to auto-calculate from acquisition date'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('assets::assets.asset.sections.depreciation'))
                    ->schema([
                        Forms\Components\TextInput::make('salvage_value_minor')
                            ->label(__('assets::assets.asset.fields.salvage_value'))
                            ->numeric()
                            ->disabled()
                            ->suffix(current_currency())
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : 0)
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('depreciable_value_minor')
                            ->label(__('assets::assets.asset.fields.depreciable_value'))
                            ->numeric()
                            ->disabled()
                            ->suffix(current_currency())
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : 0)
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('accumulated_depreciation_minor')
                            ->label(__('assets::assets.asset.fields.accumulated_depreciation'))
                            ->numeric()
                            ->disabled()
                            ->suffix(current_currency())
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : 0)
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('book_value_minor')
                            ->label(__('assets::assets.asset.fields.book_value'))
                            ->numeric()
                            ->disabled()
                            ->suffix(current_currency())
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : 0)
                            ->dehydrated(false),
                    ])
                    ->columns(2)
                    ->visibleOn('edit'),

                Forms\Components\Section::make(__('assets::assets.asset.sections.additional'))
                    ->schema([
                        Forms\Components\TextInput::make('serial_number')
                            ->label(__('assets::assets.asset.fields.serial_number'))
                            ->maxLength(255),

                        Forms\Components\TextInput::make('location')
                            ->label(__('assets::assets.asset.fields.location'))
                            ->maxLength(255),

                        Forms\Components\Select::make('assigned_to_user_id')
                            ->label(__('assets::assets.asset.fields.assigned_to'))
                            ->options(fn () => User::all()->pluck('name', 'id'))
                            ->searchable()
                            ->preload(),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('assets::assets.asset.fields.notes'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('assets::assets.asset.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('assets::assets.asset.fields.name'))
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('assetType.name')
                    ->label(__('assets::assets.asset.fields.asset_type'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('acquisition_date')
                    ->label(__('assets::assets.asset.fields.acquisition_date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('acquisition_cost_minor')
                    ->label(__('assets::assets.asset.fields.acquisition_cost'))
                    ->formatStateUsing(fn ($state) => format_money($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('book_value_minor')
                    ->label(__('assets::assets.asset.fields.book_value'))
                    ->formatStateUsing(fn ($state) => format_money($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('depreciation_percent')
                    ->label('Depreciated')
                    ->formatStateUsing(fn (Asset $record) => $record->depreciation_percent . '%')
                    ->badge()
                    ->color(fn (Asset $record) => match (true) {
                        $record->depreciation_percent >= 100 => 'danger',
                        $record->depreciation_percent >= 75 => 'warning',
                        $record->depreciation_percent >= 50 => 'info',
                        default => 'success',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('assets::assets.asset.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Asset::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => Asset::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('assets::assets.asset.fields.branch'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('assets::assets.asset.fields.status'))
                    ->options(Asset::STATUSES),

                Tables\Filters\SelectFilter::make('asset_type_id')
                    ->label(__('assets::assets.asset.fields.asset_type'))
                    ->relationship('assetType', 'name'),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('assets::assets.asset.fields.branch'))
                    ->relationship('branch', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Asset $record) => $record->isDraft()),

                Tables\Actions\Action::make('activate')
                    ->label(__('assets::assets.actions.activate'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(__('assets::assets.actions.activate_description'))
                    ->visible(fn (Asset $record) => $record->canActivate())
                    ->action(function (Asset $record) {
                        $service = app(AssetService::class);
                        if ($service->activateAsset($record)) {
                            Notification::make()
                                ->title(__('assets::assets.messages.activated'))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('assets::assets.messages.cannot_activate'))
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('dispose')
                    ->label(__('assets::assets.actions.dispose'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (Asset $record) => $record->canDispose())
                    ->form([
                        Forms\Components\Select::make('disposal_method')
                            ->label(__('assets::assets.asset.fields.disposal_method'))
                            ->options(Asset::DISPOSAL_METHODS)
                            ->required(),

                        Forms\Components\TextInput::make('disposal_value')
                            ->label(__('assets::assets.asset.fields.disposal_value'))
                            ->numeric()
                            ->default(0)
                            ->suffix(current_currency())
                            ->helperText('Proceeds from sale (if applicable)'),

                        Forms\Components\DatePicker::make('disposal_date')
                            ->label(__('assets::assets.asset.fields.disposal_date'))
                            ->default(now())
                            ->required(),

                        Forms\Components\Textarea::make('disposal_notes')
                            ->label(__('assets::assets.asset.fields.disposal_notes'))
                            ->rows(2),
                    ])
                    ->action(function (Asset $record, array $data) {
                        $service = app(AssetService::class);
                        $service->disposeAsset(
                            $record,
                            $data['disposal_method'],
                            (int) ($data['disposal_value'] * 100),
                            $data['disposal_notes'],
                            $data['disposal_date'] ? \Carbon\Carbon::parse($data['disposal_date']) : null
                        );

                        Notification::make()
                            ->title(__('assets::assets.messages.disposed'))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => false), // Disable bulk delete for assets
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('assets::assets.asset.sections.basic'))
                    ->schema([
                        Infolists\Components\TextEntry::make('code')
                            ->label(__('assets::assets.asset.fields.code'))
                            ->copyable(),

                        Infolists\Components\TextEntry::make('name')
                            ->label(__('assets::assets.asset.fields.name')),

                        Infolists\Components\TextEntry::make('assetType.name')
                            ->label(__('assets::assets.asset.fields.asset_type')),

                        Infolists\Components\TextEntry::make('branch.name')
                            ->label(__('assets::assets.asset.fields.branch'))
                            ->default('-'),

                        Infolists\Components\TextEntry::make('status')
                            ->label(__('assets::assets.asset.fields.status'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => Asset::STATUSES[$state] ?? $state)
                            ->color(fn (string $state): string => Asset::STATUS_COLORS[$state] ?? 'gray'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make(__('assets::assets.asset.sections.acquisition'))
                    ->schema([
                        Infolists\Components\TextEntry::make('acquisition_date')
                            ->label(__('assets::assets.asset.fields.acquisition_date'))
                            ->date(),

                        Infolists\Components\TextEntry::make('acquisition_cost_minor')
                            ->label(__('assets::assets.asset.fields.acquisition_cost'))
                            ->formatStateUsing(fn ($state) => format_money($state)),

                        Infolists\Components\TextEntry::make('acquisition_method')
                            ->label(__('assets::assets.asset.fields.acquisition_method'))
                            ->formatStateUsing(fn ($state) => Asset::ACQUISITION_METHODS[$state] ?? $state),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make(__('assets::assets.asset.sections.depreciation'))
                    ->schema([
                        Infolists\Components\TextEntry::make('salvage_value_minor')
                            ->label(__('assets::assets.asset.fields.salvage_value'))
                            ->formatStateUsing(fn ($state) => format_money($state)),

                        Infolists\Components\TextEntry::make('depreciable_value_minor')
                            ->label(__('assets::assets.asset.fields.depreciable_value'))
                            ->formatStateUsing(fn ($state) => format_money($state)),

                        Infolists\Components\TextEntry::make('accumulated_depreciation_minor')
                            ->label(__('assets::assets.asset.fields.accumulated_depreciation'))
                            ->formatStateUsing(fn ($state) => format_money($state)),

                        Infolists\Components\TextEntry::make('book_value_minor')
                            ->label(__('assets::assets.asset.fields.book_value'))
                            ->formatStateUsing(fn ($state) => format_money($state)),

                        Infolists\Components\TextEntry::make('depreciation_start_date')
                            ->label(__('assets::assets.asset.fields.depreciation_start_date'))
                            ->date()
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('last_depreciation_date')
                            ->label(__('assets::assets.asset.fields.last_depreciation_date'))
                            ->date()
                            ->placeholder('-'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make(__('assets::assets.asset.sections.additional'))
                    ->schema([
                        Infolists\Components\TextEntry::make('serial_number')
                            ->label(__('assets::assets.asset.fields.serial_number'))
                            ->default('-'),

                        Infolists\Components\TextEntry::make('location')
                            ->label(__('assets::assets.asset.fields.location'))
                            ->default('-'),

                        Infolists\Components\TextEntry::make('assignedTo.name')
                            ->label(__('assets::assets.asset.fields.assigned_to'))
                            ->default('-'),

                        Infolists\Components\TextEntry::make('notes')
                            ->label(__('assets::assets.asset.fields.notes'))
                            ->columnSpanFull()
                            ->default('-'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make(__('assets::assets.asset.sections.disposal'))
                    ->schema([
                        Infolists\Components\TextEntry::make('disposal_date')
                            ->label(__('assets::assets.asset.fields.disposal_date'))
                            ->date(),

                        Infolists\Components\TextEntry::make('disposal_method')
                            ->label(__('assets::assets.asset.fields.disposal_method'))
                            ->formatStateUsing(fn ($state) => Asset::DISPOSAL_METHODS[$state] ?? $state),

                        Infolists\Components\TextEntry::make('disposal_value_minor')
                            ->label(__('assets::assets.asset.fields.disposal_value'))
                            ->formatStateUsing(fn ($state) => format_money($state ?? 0)),

                        Infolists\Components\TextEntry::make('disposal_notes')
                            ->label(__('assets::assets.asset.fields.disposal_notes'))
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->visible(fn (Asset $record) => $record->isDisposed()),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DepreciationEntriesRelationManager::class,
            ActivityLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'view' => Pages\ViewAsset::route('/{record}'),
            'edit' => Pages\EditAsset::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['assetType', 'branch', 'assignedTo']);
    }
}
