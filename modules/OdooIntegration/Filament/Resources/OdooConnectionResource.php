<?php

namespace Modules\OdooIntegration\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\OdooIntegration\Filament\Resources\OdooConnectionResource\Pages;
use Modules\OdooIntegration\Filament\Resources\OdooConnectionResource\RelationManagers;
use Modules\OdooIntegration\Models\OdooConnection;
use Modules\OdooIntegration\Enums\ApiProtocol;
use Modules\OdooIntegration\Services\Api\OdooApiFactory;
use Modules\OdooIntegration\Services\Transform\TimezoneConverter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class OdooConnectionResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = OdooConnection::class;

    protected static ?string $moduleCode = 'odoo-integration';

    protected static ?string $permissionKey = 'odoo';

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 91;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('odoo-integration::odoo.connections');
    }

    public static function getModelLabel(): string
    {
        return __('odoo-integration::odoo.connection');
    }

    public static function getPluralModelLabel(): string
    {
        return __('odoo-integration::odoo.connections');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('odoo-integration::odoo.sections.connection_details'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('odoo-integration::odoo.fields.name'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('code')
                            ->label(__('odoo-integration::odoo.fields.code'))
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText(__('odoo-integration::odoo.helpers.code')),

                        Forms\Components\TextInput::make('host')
                            ->label(__('odoo-integration::odoo.fields.host'))
                            ->required()
                            ->placeholder('your-odoo.com')
                            ->helperText('Enter hostname only, without http:// or https://'),

                        Forms\Components\TextInput::make('port')
                            ->label(__('odoo-integration::odoo.fields.port'))
                            ->numeric()
                            ->default(8069)
                            ->required(),

                        Forms\Components\TextInput::make('database_name')
                            ->label(__('odoo-integration::odoo.fields.database'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('protocol')
                            ->label(__('odoo-integration::odoo.fields.protocol'))
                            ->options(ApiProtocol::options())
                            ->default(ApiProtocol::XMLRPC->value)
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('odoo-integration::odoo.sections.authentication'))
                    ->schema([
                        Forms\Components\TextInput::make('username')
                            ->label(__('odoo-integration::odoo.fields.username'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('password')
                            ->label(__('odoo-integration::odoo.fields.password'))
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn ($state) => filled($state)),

                        Forms\Components\TextInput::make('api_key')
                            ->label(__('odoo-integration::odoo.fields.api_key'))
                            ->password()
                            ->helperText(__('odoo-integration::odoo.helpers.api_key'))
                            ->dehydrated(fn ($state) => filled($state)),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('odoo-integration::odoo.sections.settings'))
                    ->schema([
                        Forms\Components\Toggle::make('use_ssl')
                            ->label(__('odoo-integration::odoo.fields.use_ssl'))
                            ->default(true),

                        Forms\Components\TextInput::make('timeout')
                            ->label(__('odoo-integration::odoo.fields.timeout'))
                            ->numeric()
                            ->default(30)
                            ->suffix(__('odoo-integration::odoo.units.seconds')),

                        Forms\Components\TextInput::make('rate_limit_per_minute')
                            ->label(__('odoo-integration::odoo.fields.rate_limit'))
                            ->numeric()
                            ->default(60)
                            ->suffix(__('odoo-integration::odoo.units.per_minute')),

                        Forms\Components\Select::make('timezone')
                            ->label(__('odoo-integration::odoo.fields.timezone'))
                            ->options(TimezoneConverter::getTimezoneOptions())
                            ->default('UTC')
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('odoo-integration::odoo.fields.is_active'))
                            ->default(true),

                        Forms\Components\Toggle::make('is_default')
                            ->label(__('odoo-integration::odoo.fields.is_default'))
                            ->default(false)
                            ->helperText(__('odoo-integration::odoo.helpers.is_default')),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('odoo-integration::odoo.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label(__('odoo-integration::odoo.fields.code'))
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('host')
                    ->label(__('odoo-integration::odoo.fields.host'))
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('database_name')
                    ->label(__('odoo-integration::odoo.fields.database'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('protocol')
                    ->label(__('odoo-integration::odoo.fields.protocol'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof ApiProtocol ? $state->label() : $state),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('odoo-integration::odoo.fields.is_active'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label(__('odoo-integration::odoo.fields.is_default'))
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('last_connected_at')
                    ->label(__('odoo-integration::odoo.fields.last_connected'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('last_sync_at')
                    ->label(__('odoo-integration::odoo.fields.last_sync'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('entity_mappings_count')
                    ->label(__('odoo-integration::odoo.fields.mappings'))
                    ->counts('entityMappings'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('odoo-integration::odoo.fields.is_active')),

                Tables\Filters\TernaryFilter::make('is_default')
                    ->label(__('odoo-integration::odoo.fields.is_default')),

                Tables\Filters\SelectFilter::make('protocol')
                    ->label(__('odoo-integration::odoo.fields.protocol'))
                    ->options(ApiProtocol::options()),
            ])
            ->actions([
                Tables\Actions\Action::make('test_connection')
                    ->label(__('odoo-integration::odoo.actions.test_connection'))
                    ->icon('heroicon-o-signal')
                    ->color('info')
                    ->action(fn (OdooConnection $record) => static::runTestConnection($record)),

                Tables\Actions\Action::make('sync_all')
                    ->label(__('odoo-integration::odoo.actions.sync_all'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (OdooConnection $record) {
                        dispatch(new \Modules\OdooIntegration\Jobs\BatchSyncJob(
                            connectionId: $record->id,
                            syncType: 'delta',
                            triggeredBy: auth()->id(),
                        ));

                        Notification::make()
                            ->title(__('odoo-integration::odoo.messages.sync_queued'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
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
                Infolists\Components\Section::make(__('odoo-integration::odoo.sections.connection_details'))
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label(__('odoo-integration::odoo.fields.name')),
                                Infolists\Components\TextEntry::make('code')
                                    ->label(__('odoo-integration::odoo.fields.code'))
                                    ->placeholder('-')
                                    ->badge()
                                    ->color('gray'),
                                Infolists\Components\TextEntry::make('base_url')
                                    ->label(__('odoo-integration::odoo.fields.url'))
                                    ->copyable(),
                            ]),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('database_name')
                                    ->label(__('odoo-integration::odoo.fields.database')),
                                Infolists\Components\TextEntry::make('username')
                                    ->label(__('odoo-integration::odoo.fields.username')),
                                Infolists\Components\TextEntry::make('protocol')
                                    ->label(__('odoo-integration::odoo.fields.protocol'))
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => $state instanceof ApiProtocol ? $state->label() : strtoupper($state)),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('odoo-integration::odoo.sections.status'))
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\IconEntry::make('is_active')
                                    ->label(__('odoo-integration::odoo.fields.is_active'))
                                    ->boolean(),
                                Infolists\Components\IconEntry::make('is_default')
                                    ->label(__('odoo-integration::odoo.fields.is_default'))
                                    ->boolean(),
                                Infolists\Components\TextEntry::make('last_connected_at')
                                    ->label(__('odoo-integration::odoo.fields.last_connected'))
                                    ->dateTime()
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('last_sync_at')
                                    ->label(__('odoo-integration::odoo.fields.last_sync'))
                                    ->dateTime()
                                    ->placeholder('-'),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('odoo-integration::odoo.sections.settings'))
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\IconEntry::make('use_ssl')
                                    ->label(__('odoo-integration::odoo.fields.use_ssl'))
                                    ->boolean(),
                                Infolists\Components\TextEntry::make('timeout')
                                    ->label(__('odoo-integration::odoo.fields.timeout'))
                                    ->suffix(' ' . __('odoo-integration::odoo.units.seconds')),
                                Infolists\Components\TextEntry::make('rate_limit_per_minute')
                                    ->label(__('odoo-integration::odoo.fields.rate_limit'))
                                    ->suffix(' ' . __('odoo-integration::odoo.units.per_minute')),
                                Infolists\Components\TextEntry::make('timezone')
                                    ->label(__('odoo-integration::odoo.fields.timezone')),
                            ]),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EntityMappingsRelationManager::class,
            RelationManagers\SyncLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOdooConnections::route('/'),
            'create' => Pages\CreateOdooConnection::route('/create'),
            'view' => Pages\ViewOdooConnection::route('/{record}'),
            'edit' => Pages\EditOdooConnection::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('entityMappings');
    }

    /**
     * Try to reach the Odoo server + authenticate, then surface the result
     * as a Filament notification. Callable from anywhere (table row action,
     * Edit page footer, Create page footer) — passing a transient
     * OdooConnection also works, since the API client only reads the
     * connection's URL/db/username/password.
     */
    public static function runTestConnection(OdooConnection $record): void
    {
        try {
            $client = app(OdooApiFactory::class)->make($record);

            if ($client->testConnection()) {
                $client->authenticate();

                Notification::make()
                    ->title(__('odoo-integration::odoo.messages.connection_success'))
                    ->success()
                    ->send();

                return;
            }

            Notification::make()
                ->title(__('odoo-integration::odoo.messages.connection_failed'))
                ->danger()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('odoo-integration::odoo.messages.connection_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
