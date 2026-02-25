<?php

namespace Modules\Booking\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Booking\Filament\Resources\BookingRuleResource\Pages;
use Modules\Booking\Models\BookingRule;
use Modules\Core\Models\Branch;
use Modules\Services\Models\Service;
use Modules\Auth\Models\User;
use App\Traits\ChecksResourcePermissions;

class BookingRuleResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = BookingRule::class;
    protected static ?string $moduleCode = 'booking';
    protected static ?string $permissionKey = 'booking_rules';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 51;

    public static function getNavigationLabel(): string
    {
        return __('booking::config.booking_rules');
    }

    public static function getModelLabel(): string
    {
        return __('booking::config.rule');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking::config.rules');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('booking::config.rule_identification'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('booking::config.rule_name'))
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('code')
                            ->label(__('booking::config.rule_code'))
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText(__('booking::config.rule_code_help')),

                        Forms\Components\Textarea::make('description')
                            ->label(__('booking::config.description'))
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('rule_type')
                            ->label(__('booking::config.rule_type'))
                            ->options(BookingRule::RULE_TYPES)
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('priority')
                            ->label(__('booking::config.priority'))
                            ->helperText(__('booking::config.priority_help'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('booking::config.active'))
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('booking::config.scope'))
                    ->description(__('booking::config.scope_desc'))
                    ->schema([
                        Forms\Components\Select::make('scope_level')
                            ->label(__('booking::config.scope_level'))
                            ->options(BookingRule::SCOPE_LEVELS)
                            ->default(BookingRule::SCOPE_TENANT)
                            ->live()
                            ->required(),

                        Forms\Components\Select::make('branch_id')
                            ->label(__('booking::config.branch'))
                            ->options(fn () => Branch::pluck('name', 'id'))
                            ->visible(fn (Get $get) => in_array($get('scope_level'), [BookingRule::SCOPE_BRANCH, BookingRule::SCOPE_SERVICE]))
                            ->searchable(),

                        Forms\Components\Select::make('service_id')
                            ->label(__('booking::config.service'))
                            ->options(fn () => Service::where('is_active', true)->pluck('name', 'id'))
                            ->visible(fn (Get $get) => $get('scope_level') === BookingRule::SCOPE_SERVICE)
                            ->searchable(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make(__('booking::config.conditions'))
                    ->description(__('booking::config.conditions_desc'))
                    ->schema([
                        Forms\Components\CheckboxList::make('conditions.days_of_week')
                            ->label(__('booking::config.days_of_week'))
                            ->options(BookingRule::DAYS_OF_WEEK)
                            ->columns(7)
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TimePicker::make('conditions.time_range.start')
                                    ->label(__('booking::config.time_range_start'))
                                    ->seconds(false),

                                Forms\Components\TimePicker::make('conditions.time_range.end')
                                    ->label(__('booking::config.time_range_end'))
                                    ->seconds(false),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('conditions.date_range.start')
                                    ->label(__('booking::config.date_range_start')),

                                Forms\Components\DatePicker::make('conditions.date_range.end')
                                    ->label(__('booking::config.date_range_end')),
                            ]),

                        Forms\Components\Select::make('conditions.services')
                            ->label(__('booking::config.apply_to_services'))
                            ->options(fn () => Service::where('is_active', true)->pluck('name', 'id'))
                            ->multiple()
                            ->searchable()
                            ->helperText(__('booking::config.leave_empty_all')),

                        Forms\Components\Select::make('conditions.practitioners')
                            ->label(__('booking::config.apply_to_practitioners'))
                            ->options(fn () => User::whereHas('roles', fn ($q) => $q->whereIn('name', ['doctor', 'practitioner', 'therapist']))
                                ->get()
                                ->pluck('full_name', 'id'))
                            ->multiple()
                            ->searchable()
                            ->helperText(__('booking::config.leave_empty_all')),
                    ]),

                // Actions Section - Dynamic based on rule type
                Forms\Components\Section::make(__('booking::config.actions'))
                    ->description(__('booking::config.actions_desc'))
                    ->schema([
                        // Slot Block Actions
                        Forms\Components\Group::make([
                            Forms\Components\Toggle::make('actions.block')
                                ->label(__('booking::config.block_slots'))
                                ->default(true),
                            Forms\Components\TextInput::make('actions.reason')
                                ->label(__('booking::config.block_reason'))
                                ->helperText(__('booking::config.block_reason_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_SLOT_BLOCK),

                        // Time Restriction Actions
                        Forms\Components\Group::make([
                            Forms\Components\TimePicker::make('actions.allowed_start')
                                ->label(__('booking::config.allowed_start'))
                                ->seconds(false),
                            Forms\Components\TimePicker::make('actions.allowed_end')
                                ->label(__('booking::config.allowed_end'))
                                ->seconds(false),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_TIME_RESTRICTION)
                            ->columns(2),

                        // Capacity Limit Actions
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('actions.max_appointments')
                                ->label(__('booking::config.max_appointments'))
                                ->numeric()
                                ->required(),
                            Forms\Components\Select::make('actions.scope')
                                ->label(__('booking::config.limit_scope'))
                                ->options([
                                    'day' => __('booking::config.per_day'),
                                    'practitioner' => __('booking::config.per_practitioner_per_day'),
                                ])
                                ->default('day'),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_CAPACITY_LIMIT)
                            ->columns(2),

                        // Buffer Override Actions
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('actions.buffer_minutes')
                                ->label(__('booking::config.buffer_minutes'))
                                ->numeric()
                                ->suffix(__('booking::config.minutes'))
                                ->required(),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_BUFFER_OVERRIDE),

                        // Advance Booking Actions
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('actions.min_hours')
                                ->label(__('booking::config.min_hours_advance'))
                                ->numeric()
                                ->suffix(__('booking::config.hours')),
                            Forms\Components\TextInput::make('actions.max_days')
                                ->label(__('booking::config.max_days_advance'))
                                ->numeric()
                                ->suffix(__('booking::config.days')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_ADVANCE_BOOKING)
                            ->columns(2),

                        // Online Restriction Actions
                        Forms\Components\Group::make([
                            Forms\Components\Toggle::make('actions.allow')
                                ->label(__('booking::config.allow_online'))
                                ->default(false),
                            Forms\Components\TextInput::make('actions.reason')
                                ->label(__('booking::config.restriction_reason')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_ONLINE_RESTRICTION),

                        // Practitioner Limit Actions
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('actions.max_appointments')
                                ->label(__('booking::config.max_appointments_practitioner'))
                                ->numeric()
                                ->required(),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_PRACTITIONER_LIMIT),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('booking::config.rule_name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label(__('booking::config.code'))
                    ->searchable()
                    ->fontFamily('mono')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('rule_type')
                    ->label(__('booking::config.type'))
                    ->badge()
                    ->formatStateUsing(fn (BookingRule $record) => $record->rule_type_label)
                    ->color(fn (BookingRule $record) => $record->rule_type_color),

                Tables\Columns\TextColumn::make('scope_level')
                    ->label(__('booking::config.scope'))
                    ->formatStateUsing(fn (BookingRule $record) => $record->scope_description),

                Tables\Columns\TextColumn::make('priority')
                    ->label(__('booking::config.priority'))
                    ->numeric()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('booking::config.active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('booking::config.updated'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('rule_type')
                    ->label(__('booking::config.type'))
                    ->options(BookingRule::RULE_TYPES),

                Tables\Filters\SelectFilter::make('scope_level')
                    ->label(__('booking::config.scope'))
                    ->options(BookingRule::SCOPE_LEVELS),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('booking::config.active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle')
                    ->icon(fn (BookingRule $record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (BookingRule $record) => $record->is_active ? 'warning' : 'success')
                    ->action(fn (BookingRule $record) => $record->update(['is_active' => !$record->is_active]))
                    ->requiresConfirmation(),
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
            'index' => Pages\ListBookingRules::route('/'),
            'create' => Pages\CreateBookingRule::route('/create'),
            'edit' => Pages\EditBookingRule::route('/{record}/edit'),
        ];
    }
}
