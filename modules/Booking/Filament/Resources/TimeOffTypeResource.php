<?php

namespace Modules\Booking\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Booking\Filament\Resources\TimeOffTypeResource\Pages;
use Modules\Booking\Models\TimeOffType;

class TimeOffTypeResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = TimeOffType::class;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $permissionKey = 'time_off_types';

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'HR';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.time_off');
    }

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('booking::time_off.types.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('booking::time_off.types.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking::time_off.types.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('booking::time_off.types.sections.basic'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name.en')
                                    ->label(__('booking::time_off.types.fields.name') . ' (English)')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('name.ar')
                                    ->label(__('booking::time_off.types.fields.name') . ' (Arabic)')
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Forms\Components\TextInput::make('code')
                            ->label(__('booking::time_off.types.fields.code'))
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText(__('booking::time_off.types.help.code')),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('description.en')
                                    ->label(__('booking::time_off.types.fields.description') . ' (English)')
                                    ->rows(2),

                                Forms\Components\Textarea::make('description.ar')
                                    ->label(__('booking::time_off.types.fields.description') . ' (Arabic)')
                                    ->rows(2),
                            ]),

                        Forms\Components\Select::make('color')
                            ->label(__('booking::time_off.types.fields.color'))
                            ->options(TimeOffType::COLORS)
                            ->default('gray')
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('booking::time_off.types.fields.is_active'))
                            ->default(true),

                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('booking::time_off.types.fields.sort_order'))
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('booking::time_off.types.sections.unit_settings'))
                    ->schema([
                        Forms\Components\Select::make('request_unit')
                            ->label(__('booking::time_off.types.fields.request_unit'))
                            ->options([
                                TimeOffType::REQUEST_UNIT_DAY => __('booking::time_off.request_units.day'),
                                TimeOffType::REQUEST_UNIT_HALF_DAY => __('booking::time_off.request_units.half_day'),
                                TimeOffType::REQUEST_UNIT_HOUR => __('booking::time_off.request_units.hour'),
                            ])
                            ->default(TimeOffType::REQUEST_UNIT_DAY)
                            ->required()
                            ->live()
                            ->helperText(__('booking::time_off.types.help.request_unit')),

                        Forms\Components\Select::make('allocation_period')
                            ->label(__('booking::time_off.types.fields.allocation_period'))
                            ->options([
                                TimeOffType::ALLOCATION_PERIOD_YEARLY => __('booking::time_off.allocation_periods.yearly'),
                                TimeOffType::ALLOCATION_PERIOD_MONTHLY => __('booking::time_off.allocation_periods.monthly'),
                            ])
                            ->default(TimeOffType::ALLOCATION_PERIOD_YEARLY)
                            ->required()
                            ->live()
                            ->helperText(__('booking::time_off.types.help.allocation_period')),

                        Forms\Components\TextInput::make('hours_per_day')
                            ->label(__('booking::time_off.types.fields.hours_per_day'))
                            ->numeric()
                            ->step(0.5)
                            ->default(8)
                            ->visible(fn (Forms\Get $get) => $get('request_unit') === TimeOffType::REQUEST_UNIT_HOUR)
                            ->helperText(__('booking::time_off.types.help.hours_per_day')),

                        Forms\Components\TextInput::make('default_allocation')
                            ->label(fn (Forms\Get $get) => $get('request_unit') === TimeOffType::REQUEST_UNIT_HOUR
                                ? __('booking::time_off.types.fields.default_hours')
                                : __('booking::time_off.types.fields.default_days'))
                            ->numeric()
                            ->step(0.5)
                            ->nullable()
                            ->helperText(fn (Forms\Get $get) => $get('allocation_period') === TimeOffType::ALLOCATION_PERIOD_MONTHLY
                                ? __('booking::time_off.types.help.default_allocation_monthly')
                                : __('booking::time_off.types.help.default_allocation_yearly')),

                        Forms\Components\TextInput::make('max_per_request')
                            ->label(fn (Forms\Get $get) => $get('request_unit') === TimeOffType::REQUEST_UNIT_HOUR
                                ? __('booking::time_off.types.fields.max_hours_per_request')
                                : __('booking::time_off.types.fields.max_days_per_request'))
                            ->numeric()
                            ->step(0.5)
                            ->nullable()
                            ->helperText(__('booking::time_off.types.help.max_per_request')),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('booking::time_off.types.sections.settings'))
                    ->schema([
                        Forms\Components\Toggle::make('is_paid')
                            ->label(__('booking::time_off.types.fields.is_paid'))
                            ->default(true)
                            ->helperText(__('booking::time_off.types.help.is_paid')),

                        Forms\Components\Toggle::make('requires_approval')
                            ->label(__('booking::time_off.types.fields.requires_approval'))
                            ->default(true)
                            ->live(),

                        Forms\Components\Select::make('approval_type')
                            ->label(__('booking::time_off.types.fields.approval_type'))
                            ->options([
                                TimeOffType::APPROVAL_TYPE_ANY => __('booking::time_off.types.approval_types.any'),
                                TimeOffType::APPROVAL_TYPE_ROLES => __('booking::time_off.types.approval_types.roles'),
                                TimeOffType::APPROVAL_TYPE_USERS => __('booking::time_off.types.approval_types.users'),
                                TimeOffType::APPROVAL_TYPE_ROLES_OR_USERS => __('booking::time_off.types.approval_types.roles_or_users'),
                            ])
                            ->default(TimeOffType::APPROVAL_TYPE_ANY)
                            ->visible(fn (Forms\Get $get) => $get('requires_approval'))
                            ->live(),

                        Forms\Components\Select::make('approval_role_ids')
                            ->label(__('booking::time_off.types.fields.approval_roles'))
                            ->options(fn () => Role::pluck('name', 'id'))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get) => $get('requires_approval') && in_array($get('approval_type'), [
                                TimeOffType::APPROVAL_TYPE_ROLES,
                                TimeOffType::APPROVAL_TYPE_ROLES_OR_USERS,
                            ]))
                            ->helperText(__('booking::time_off.types.help.approval_roles')),

                        Forms\Components\Select::make('approval_user_ids')
                            ->label(__('booking::time_off.types.fields.approval_users'))
                            ->options(fn () => User::pluck('email', 'id')->mapWithKeys(fn ($email, $id) => [$id => User::find($id)?->full_name ?? $email]))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get) => $get('requires_approval') && in_array($get('approval_type'), [
                                TimeOffType::APPROVAL_TYPE_USERS,
                                TimeOffType::APPROVAL_TYPE_ROLES_OR_USERS,
                            ]))
                            ->helperText(__('booking::time_off.types.help.approval_users')),

                        Forms\Components\TextInput::make('min_days_notice')
                            ->label(__('booking::time_off.types.fields.min_days_notice'))
                            ->numeric()
                            ->default(0)
                            ->helperText(__('booking::time_off.types.help.min_notice')),

                        Forms\Components\Toggle::make('allow_half_day')
                            ->label(__('booking::time_off.types.fields.allow_half_day'))
                            ->default(true)
                            ->visible(fn (Forms\Get $get) => $get('request_unit') === TimeOffType::REQUEST_UNIT_DAY),

                        Forms\Components\Toggle::make('allow_partial_day')
                            ->label(__('booking::time_off.types.fields.allow_partial_day'))
                            ->default(false)
                            ->visible(fn (Forms\Get $get) => $get('request_unit') === TimeOffType::REQUEST_UNIT_DAY)
                            ->helperText(__('booking::time_off.types.help.partial_day')),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('translated_name')
                    ->label(__('booking::time_off.types.fields.name'))
                    ->searchable(['name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label(__('booking::time_off.types.fields.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('color')
                    ->label(__('booking::time_off.types.fields.color'))
                    ->badge()
                    ->color(fn (string $state): string => $state),

                Tables\Columns\IconColumn::make('is_paid')
                    ->label(__('booking::time_off.types.fields.is_paid'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('request_unit')
                    ->label(__('booking::time_off.types.fields.request_unit'))
                    ->formatStateUsing(fn (string $state): string => __('booking::time_off.request_units.' . $state))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        TimeOffType::REQUEST_UNIT_HOUR => 'info',
                        TimeOffType::REQUEST_UNIT_HALF_DAY => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('allocation_period')
                    ->label(__('booking::time_off.types.fields.allocation_period'))
                    ->formatStateUsing(fn (string $state): string => __('booking::time_off.allocation_periods.' . $state))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('default_allocation')
                    ->label(__('booking::time_off.types.fields.default_allocation'))
                    ->formatStateUsing(fn ($record) => $record->default_allocation !== null
                        ? $record->formatValue($record->default_allocation)
                        : ($record->default_days_per_year . ' ' . __('booking::time_off.request_units.day')))
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('requires_approval')
                    ->label(__('booking::time_off.types.fields.requires_approval'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('booking::time_off.types.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('booking::time_off.types.fields.sort_order'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('booking::time_off.types.fields.is_active')),

                Tables\Filters\TernaryFilter::make('is_paid')
                    ->label(__('booking::time_off.types.fields.is_paid')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListTimeOffTypes::route('/'),
            'create' => Pages\CreateTimeOffType::route('/create'),
            'edit' => Pages\EditTimeOffType::route('/{record}/edit'),
        ];
    }
}
