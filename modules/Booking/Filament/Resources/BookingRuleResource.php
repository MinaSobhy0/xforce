<?php

namespace Modules\Booking\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Booking\Filament\Resources\BookingRuleResource\Pages;
use Modules\Booking\Models\BookingRule;
use Modules\Core\Models\Branch;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Modules\Auth\Models\User;
use Modules\Resources\Models\Room;
use Modules\Equipment\Models\Equipment;
use App\Traits\ChecksResourcePermissions;
use Illuminate\Support\HtmlString;

class BookingRuleResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = BookingRule::class;
    protected static ?string $moduleCode = 'booking';
    protected static ?string $permissionKey = 'booking_rules';

    protected static ?string $navigationIcon = 'heroicon-o-funnel';
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
                // Rule Identification
                Forms\Components\Section::make(__('booking::config.rule_identification'))
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('booking::config.rule_name'))
                                    ->required()
                                    ->maxLength(100)
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('code')
                                    ->label(__('booking::config.rule_code'))
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true)
                                    ->helperText(__('booking::config.rule_code_help')),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label(__('booking::config.description'))
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('rule_type')
                                    ->label(__('booking::config.rule_type'))
                                    ->options(BookingRule::getRuleTypesGrouped())
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('actions', []))
                                    ->columnSpan(2)
                                    ->helperText(fn (Get $get) => BookingRule::RULE_TYPE_DESCRIPTIONS[$get('rule_type')] ?? ''),

                                Forms\Components\TextInput::make('priority')
                                    ->label(__('booking::config.priority'))
                                    ->helperText(__('booking::config.priority_help'))
                                    ->numeric()
                                    ->default(50)
                                    ->minValue(0)
                                    ->maxValue(100),
                            ]),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('booking::config.active'))
                            ->default(true),
                    ]),

                // Actions Section - Dynamic based on rule type (moved directly after Rule Identification)
                Forms\Components\Section::make(__('booking::config.actions'))
                    ->description(fn (Get $get) => BookingRule::RULE_TYPE_DESCRIPTIONS[$get('rule_type')] ?? __('booking::config.actions_desc'))
                    ->schema([
                        // =====================
                        // SLOT GENERATION RULES
                        // =====================

                        // Slot Duration Override
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('actions.duration_minutes')
                                ->label(__('booking::config.duration_minutes'))
                                ->numeric()
                                ->suffix(__('booking::config.minutes'))
                                ->required()
                                ->helperText(__('booking::config.duration_override_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_SLOT_DURATION),

                        // Slot Interval Override
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('actions.interval_minutes')
                                ->label(__('booking::config.interval_minutes'))
                                ->numeric()
                                ->suffix(__('booking::config.minutes'))
                                ->required()
                                ->helperText(__('booking::config.interval_override_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_SLOT_INTERVAL),

                        // Buffer Override
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('actions.buffer_minutes')
                                ->label(__('booking::config.buffer_minutes'))
                                ->numeric()
                                ->suffix(__('booking::config.minutes'))
                                ->required()
                                ->helperText(__('booking::config.buffer_override_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_SLOT_BUFFER),

                        // Block Slots
                        Forms\Components\Group::make([
                            Forms\Components\Toggle::make('actions.block')
                                ->label(__('booking::config.block_slots'))
                                ->default(true),
                            Forms\Components\TextInput::make('actions.reason')
                                ->label(__('booking::config.block_reason'))
                                ->helperText(__('booking::config.block_reason_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_SLOT_BLOCK),

                        // =====================
                        // TIME CONFIGURATION
                        // =====================

                        // Working Hours / Time Restriction / Special Hours
                        Forms\Components\Group::make([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TimePicker::make('actions.start_time')
                                        ->label(__('booking::config.start_time'))
                                        ->seconds(false)
                                        ->required(),
                                    Forms\Components\TimePicker::make('actions.end_time')
                                        ->label(__('booking::config.end_time'))
                                        ->seconds(false)
                                        ->required(),
                                ]),
                            Forms\Components\TextInput::make('actions.reason')
                                ->label(__('booking::config.reason'))
                                ->visible(fn (Get $get) => in_array($get('rule_type'), [
                                    BookingRule::TYPE_SPECIAL_HOURS,
                                    BookingRule::TYPE_TIME_RESTRICTION,
                                ])),
                        ])->visible(fn (Get $get) => in_array($get('rule_type'), [
                            BookingRule::TYPE_WORKING_HOURS,
                            BookingRule::TYPE_BREAK_TIME,
                            BookingRule::TYPE_TIME_RESTRICTION,
                            BookingRule::TYPE_SPECIAL_HOURS,
                        ])),

                        // =====================
                        // CAPACITY RULES
                        // =====================

                        // Daily Capacity
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('actions.max_appointments')
                                ->label(__('booking::config.max_appointments_daily'))
                                ->numeric()
                                ->required()
                                ->helperText(__('booking::config.max_appointments_daily_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_CAPACITY_DAILY),

                        // Hourly Capacity
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('actions.max_per_hour')
                                ->label(__('booking::config.max_per_hour'))
                                ->numeric()
                                ->required()
                                ->helperText(__('booking::config.max_per_hour_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_CAPACITY_HOURLY),

                        // Practitioner Capacity
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('actions.max_per_practitioner')
                                ->label(__('booking::config.max_per_practitioner'))
                                ->numeric()
                                ->required()
                                ->helperText(__('booking::config.max_per_practitioner_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_CAPACITY_PRACTITIONER),

                        // Service Capacity
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('actions.max_appointments')
                                ->label(__('booking::config.max_service_daily'))
                                ->numeric()
                                ->required()
                                ->helperText(__('booking::config.max_service_daily_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_CAPACITY_SERVICE),

                        // Overbooking
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('actions.overbooking_limit')
                                ->label(__('booking::config.overbooking_limit'))
                                ->numeric()
                                ->required()
                                ->helperText(__('booking::config.overbooking_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_OVERBOOKING),

                        // =====================
                        // ADVANCE BOOKING
                        // =====================

                        // Minimum Advance
                        Forms\Components\Group::make([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('actions.min_hours')
                                        ->label(__('booking::config.min_hours'))
                                        ->numeric()
                                        ->suffix(__('booking::config.hours')),
                                    Forms\Components\TextInput::make('actions.min_days')
                                        ->label(__('booking::config.min_days'))
                                        ->numeric()
                                        ->suffix(__('booking::config.days')),
                                ]),
                            Forms\Components\Placeholder::make('min_advance_help')
                                ->content(__('booking::config.min_advance_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_MIN_ADVANCE),

                        // Maximum Advance
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('actions.max_days')
                                ->label(__('booking::config.max_days'))
                                ->numeric()
                                ->required()
                                ->suffix(__('booking::config.days'))
                                ->helperText(__('booking::config.max_advance_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_MAX_ADVANCE),

                        // Same Day Booking
                        Forms\Components\Group::make([
                            Forms\Components\Toggle::make('actions.allow_same_day')
                                ->label(__('booking::config.allow_same_day'))
                                ->helperText(__('booking::config.same_day_help')),
                            Forms\Components\TimePicker::make('actions.cutoff_time')
                                ->label(__('booking::config.cutoff_time'))
                                ->seconds(false)
                                ->helperText(__('booking::config.cutoff_time_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_SAME_DAY),

                        // =====================
                        // ONLINE BOOKING
                        // =====================

                        // Online Enabled
                        Forms\Components\Group::make([
                            Forms\Components\Toggle::make('actions.enabled')
                                ->label(__('booking::config.online_enabled'))
                                ->helperText(__('booking::config.online_enabled_help')),
                            Forms\Components\TextInput::make('actions.reason')
                                ->label(__('booking::config.restriction_reason'))
                                ->visible(fn (Get $get) => !($get('actions.enabled') ?? true)),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_ONLINE_ENABLED),

                        // Online Practitioner Selection
                        Forms\Components\Group::make([
                            Forms\Components\Toggle::make('actions.allow_practitioner_selection')
                                ->label(__('booking::config.allow_practitioner_selection'))
                                ->helperText(__('booking::config.allow_practitioner_selection_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_ONLINE_PRACTITIONER),

                        // Online Hours
                        Forms\Components\Group::make([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TimePicker::make('actions.online_start')
                                        ->label(__('booking::config.online_start'))
                                        ->seconds(false),
                                    Forms\Components\TimePicker::make('actions.online_end')
                                        ->label(__('booking::config.online_end'))
                                        ->seconds(false),
                                ]),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_ONLINE_HOURS),

                        // =====================
                        // PATIENT RULES
                        // =====================

                        // New Patient Rules
                        Forms\Components\Group::make([
                            Forms\Components\Toggle::make('actions.require_extra_time')
                                ->label(__('booking::config.require_extra_time')),
                            Forms\Components\TextInput::make('actions.extra_minutes')
                                ->label(__('booking::config.extra_minutes'))
                                ->numeric()
                                ->suffix(__('booking::config.minutes'))
                                ->visible(fn (Get $get) => $get('actions.require_extra_time')),
                            Forms\Components\Toggle::make('actions.require_consultation')
                                ->label(__('booking::config.require_consultation')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_PATIENT_NEW),

                        // Age Restriction
                        Forms\Components\Group::make([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('actions.min_age')
                                        ->label(__('booking::config.min_age'))
                                        ->numeric()
                                        ->suffix(__('booking::config.years')),
                                    Forms\Components\TextInput::make('actions.max_age')
                                        ->label(__('booking::config.max_age'))
                                        ->numeric()
                                        ->suffix(__('booking::config.years')),
                                ]),
                            Forms\Components\TextInput::make('actions.restriction_message')
                                ->label(__('booking::config.restriction_message'))
                                ->columnSpanFull(),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_PATIENT_AGE),

                        // Gender Restriction
                        Forms\Components\Group::make([
                            Forms\Components\Select::make('actions.allowed_genders')
                                ->label(__('booking::config.allowed_genders'))
                                ->options([
                                    'male' => __('booking::config.male'),
                                    'female' => __('booking::config.female'),
                                ])
                                ->multiple()
                                ->required(),
                            Forms\Components\TextInput::make('actions.restriction_message')
                                ->label(__('booking::config.restriction_message')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_PATIENT_GENDER),

                        // =====================
                        // PRICING RULES
                        // =====================

                        // Price Modifier / Peak Pricing
                        Forms\Components\Group::make([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\Select::make('actions.modifier_type')
                                        ->label(__('booking::config.modifier_type'))
                                        ->options(BookingRule::PRICE_MODIFIER_TYPES)
                                        ->required(),
                                    Forms\Components\TextInput::make('actions.modifier_value')
                                        ->label(__('booking::config.modifier_value'))
                                        ->numeric()
                                        ->required()
                                        ->suffix(fn (Get $get) => $get('actions.modifier_type') === 'percentage' ? '%' : ''),
                                ]),
                            Forms\Components\TextInput::make('actions.reason')
                                ->label(__('booking::config.modifier_reason')),
                        ])->visible(fn (Get $get) => in_array($get('rule_type'), [
                            BookingRule::TYPE_PRICE_MODIFIER,
                            BookingRule::TYPE_PEAK_PRICING,
                        ])),

                        // Discount
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('actions.discount_percentage')
                                ->label(__('booking::config.discount_percentage'))
                                ->numeric()
                                ->required()
                                ->suffix('%'),
                            Forms\Components\TextInput::make('actions.discount_code')
                                ->label(__('booking::config.discount_code')),
                            Forms\Components\TextInput::make('actions.discount_reason')
                                ->label(__('booking::config.discount_reason')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_DISCOUNT),

                        // =====================
                        // CONFIRMATION RULES
                        // =====================

                        // Auto Confirm
                        Forms\Components\Group::make([
                            Forms\Components\Toggle::make('actions.auto_confirm')
                                ->label(__('booking::config.auto_confirm'))
                                ->helperText(__('booking::config.auto_confirm_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_AUTO_CONFIRM),

                        // Require Deposit
                        Forms\Components\Group::make([
                            Forms\Components\Toggle::make('actions.deposit_required')
                                ->label(__('booking::config.deposit_required'))
                                ->live(),
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('actions.deposit_percentage')
                                        ->label(__('booking::config.deposit_percentage'))
                                        ->numeric()
                                        ->suffix('%'),
                                    Forms\Components\TextInput::make('actions.deposit_amount')
                                        ->label(__('booking::config.deposit_fixed_amount'))
                                        ->numeric()
                                        ->helperText(__('booking::config.deposit_fixed_help')),
                                ])
                                ->visible(fn (Get $get) => $get('actions.deposit_required')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_REQUIRE_DEPOSIT),

                        // Require Approval
                        Forms\Components\Group::make([
                            Forms\Components\Toggle::make('actions.require_approval')
                                ->label(__('booking::config.require_approval'))
                                ->helperText(__('booking::config.require_approval_help')),
                            Forms\Components\Select::make('actions.approval_roles')
                                ->label(__('booking::config.approval_roles'))
                                ->options([
                                    'admin' => 'Admin',
                                    'manager' => 'Manager',
                                    'doctor' => 'Doctor',
                                ])
                                ->multiple(),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_REQUIRE_APPROVAL),

                        // =====================
                        // CANCELLATION RULES
                        // =====================

                        // Cancellation Policy
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('actions.cancellation_hours')
                                ->label(__('booking::config.cancellation_hours'))
                                ->numeric()
                                ->required()
                                ->suffix(__('booking::config.hours'))
                                ->helperText(__('booking::config.cancellation_hours_help')),
                            Forms\Components\TextInput::make('actions.cancellation_fee_percentage')
                                ->label(__('booking::config.cancellation_fee'))
                                ->numeric()
                                ->suffix('%')
                                ->helperText(__('booking::config.cancellation_fee_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_CANCELLATION_POLICY),

                        // Reschedule Policy
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('actions.reschedule_limit')
                                ->label(__('booking::config.reschedule_limit'))
                                ->numeric()
                                ->helperText(__('booking::config.reschedule_limit_help')),
                            Forms\Components\TextInput::make('actions.reschedule_hours')
                                ->label(__('booking::config.reschedule_hours'))
                                ->numeric()
                                ->suffix(__('booking::config.hours'))
                                ->helperText(__('booking::config.reschedule_hours_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_RESCHEDULE_POLICY),

                        // No Show Policy
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('actions.no_show_fee_percentage')
                                ->label(__('booking::config.no_show_fee'))
                                ->numeric()
                                ->suffix('%'),
                            Forms\Components\TextInput::make('actions.no_show_limit')
                                ->label(__('booking::config.no_show_limit'))
                                ->numeric()
                                ->helperText(__('booking::config.no_show_limit_help')),
                            Forms\Components\Toggle::make('actions.block_after_no_shows')
                                ->label(__('booking::config.block_after_no_shows')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_NO_SHOW_POLICY),

                        // =====================
                        // RESOURCE RULES
                        // =====================

                        // Room Preference
                        Forms\Components\Group::make([
                            Forms\Components\Select::make('actions.room_source')
                                ->label(__('booking::config.room_source'))
                                ->options([
                                    'service' => __('booking::config.use_service_rooms'),
                                    'manual' => __('booking::config.specify_manually'),
                                ])
                                ->default('service')
                                ->live()
                                ->helperText(__('booking::config.room_source_help')),
                            Forms\Components\Select::make('actions.preferred_rooms')
                                ->label(__('booking::config.preferred_rooms'))
                                ->options(fn () => class_exists(Room::class) ? Room::where('is_active', true)->pluck('name', 'id') : [])
                                ->multiple()
                                ->searchable()
                                ->visible(fn (Get $get) => $get('actions.room_source') === 'manual'),
                            Forms\Components\Toggle::make('actions.strict_room')
                                ->label(__('booking::config.strict_room'))
                                ->helperText(__('booking::config.strict_room_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_ROOM_PREFERENCE),

                        // Equipment Required
                        Forms\Components\Group::make([
                            Forms\Components\Select::make('actions.equipment_source')
                                ->label(__('booking::config.equipment_source'))
                                ->options([
                                    'service' => __('booking::config.use_service_equipment'),
                                    'manual' => __('booking::config.specify_manually'),
                                ])
                                ->default('service')
                                ->live()
                                ->helperText(__('booking::config.equipment_source_help')),
                            Forms\Components\Select::make('actions.required_equipment')
                                ->label(__('booking::config.required_equipment'))
                                ->options(fn () => class_exists(Equipment::class) ? Equipment::where('status', 'active')->pluck('name', 'id') : [])
                                ->multiple()
                                ->searchable()
                                ->visible(fn (Get $get) => $get('actions.equipment_source') === 'manual'),
                            Forms\Components\Toggle::make('actions.strict_equipment')
                                ->label(__('booking::config.strict_equipment'))
                                ->helperText(__('booking::config.strict_equipment_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_EQUIPMENT_REQUIRED),

                        // Required Practitioner
                        Forms\Components\Group::make([
                            Forms\Components\Select::make('actions.practitioner_source')
                                ->label(__('booking::config.practitioner_source'))
                                ->options([
                                    'service' => __('booking::config.use_service_practitioners'),
                                    'manual' => __('booking::config.specify_manually'),
                                ])
                                ->default('service')
                                ->live()
                                ->helperText(__('booking::config.practitioner_source_help')),
                            Forms\Components\Select::make('actions.required_practitioners')
                                ->label(__('booking::config.required_practitioners'))
                                ->options(fn () => User::whereHas('roles', fn ($q) => $q->whereIn('name', ['doctor', 'practitioner', 'therapist']))
                                    ->get()
                                    ->pluck('full_name', 'id'))
                                ->multiple()
                                ->searchable()
                                ->visible(fn (Get $get) => $get('actions.practitioner_source') === 'manual'),
                            Forms\Components\Select::make('actions.required_qualifications')
                                ->label(__('booking::config.required_qualifications'))
                                ->options([
                                    'licensed' => __('booking::config.qualification_licensed'),
                                    'certified' => __('booking::config.qualification_certified'),
                                    'senior' => __('booking::config.qualification_senior'),
                                ])
                                ->multiple()
                                ->helperText(__('booking::config.required_qualifications_help')),
                            Forms\Components\Toggle::make('actions.strict_practitioner')
                                ->label(__('booking::config.strict_practitioner'))
                                ->helperText(__('booking::config.strict_practitioner_help')),
                        ])->visible(fn (Get $get) => $get('rule_type') === BookingRule::TYPE_PRACTITIONER_REQUIRED),
                    ]),

                // Scope Section
                Forms\Components\Section::make(__('booking::config.scope'))
                    ->description(__('booking::config.scope_desc'))
                    ->collapsible()
                    ->schema([
                        Forms\Components\Grid::make(3)
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
                                    ->visible(fn (Get $get) => in_array($get('scope_level'), [
                                        BookingRule::SCOPE_BRANCH,
                                        BookingRule::SCOPE_SERVICE,
                                    ]))
                                    ->searchable(),

                                Forms\Components\Select::make('service_id')
                                    ->label(__('booking::config.service'))
                                    ->options(fn () => Service::where('is_active', true)->pluck('name', 'id'))
                                    ->visible(fn (Get $get) => $get('scope_level') === BookingRule::SCOPE_SERVICE)
                                    ->searchable(),
                            ]),
                    ]),

                // Conditions Section
                Forms\Components\Section::make(__('booking::config.conditions'))
                    ->description(__('booking::config.conditions_desc'))
                    ->schema([
                        // Days of Week
                        Forms\Components\CheckboxList::make('conditions.days_of_week')
                            ->label(__('booking::config.days_of_week'))
                            ->options(BookingRule::DAYS_OF_WEEK)
                            ->columns(7)
                            ->columnSpanFull()
                            ->helperText(__('booking::config.leave_empty_all')),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                // Time Range
                                Forms\Components\Fieldset::make(__('booking::config.time_range'))
                                    ->schema([
                                        Forms\Components\TimePicker::make('conditions.time_range.start')
                                            ->label(__('booking::config.start_time'))
                                            ->seconds(false),
                                        Forms\Components\TimePicker::make('conditions.time_range.end')
                                            ->label(__('booking::config.end_time'))
                                            ->seconds(false),
                                    ]),

                                // Date Range
                                Forms\Components\Fieldset::make(__('booking::config.date_range'))
                                    ->schema([
                                        Forms\Components\DatePicker::make('conditions.date_range.start')
                                            ->label(__('booking::config.start_date')),
                                        Forms\Components\DatePicker::make('conditions.date_range.end')
                                            ->label(__('booking::config.end_date')),
                                    ]),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
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

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('conditions.booking_source')
                                    ->label(__('booking::config.booking_source'))
                                    ->options(BookingRule::BOOKING_SOURCES)
                                    ->multiple()
                                    ->helperText(__('booking::config.leave_empty_all')),

                                Forms\Components\Select::make('conditions.patient_type')
                                    ->label(__('booking::config.patient_type'))
                                    ->options([
                                        'new' => __('booking::config.new_patient'),
                                        'returning' => __('booking::config.returning_patient'),
                                    ])
                                    ->placeholder(__('booking::config.all_patients')),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('booking::config.rule_name'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (BookingRule $record) => $record->description),

                Tables\Columns\TextColumn::make('rule_category_label')
                    ->label(__('booking::config.category'))
                    ->badge()
                    ->color(fn (BookingRule $record) => BookingRule::RULE_CATEGORIES[$record->rule_category]['color'] ?? 'gray'),

                Tables\Columns\TextColumn::make('rule_type')
                    ->label(__('booking::config.type'))
                    ->formatStateUsing(fn (BookingRule $record) => $record->rule_type_label)
                    ->description(fn (BookingRule $record) => $record->rule_type_description)
                    ->wrap(),

                Tables\Columns\TextColumn::make('scope_description')
                    ->label(__('booking::config.scope'))
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('priority')
                    ->label(__('booking::config.priority'))
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

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
                Tables\Filters\SelectFilter::make('category')
                    ->label(__('booking::config.category'))
                    ->options(collect(BookingRule::RULE_CATEGORIES)->mapWithKeys(fn ($config, $key) => [$key => $config['label']]))
                    ->query(function ($query, array $data) {
                        if ($data['value']) {
                            $types = BookingRule::RULE_CATEGORIES[$data['value']]['types'] ?? [];
                            $query->whereIn('rule_type', $types);
                        }
                    }),

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
                    ->label(fn (BookingRule $record) => $record->is_active ? __('booking::config.deactivate') : __('booking::config.activate'))
                    ->action(fn (BookingRule $record) => $record->update(['is_active' => !$record->is_active]))
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->action(function (BookingRule $record) {
                        $new = $record->replicate();
                        $new->name = $record->name . ' (Copy)';
                        $new->code = $record->code . '_copy_' . now()->timestamp;
                        $new->is_active = false;
                        $new->save();
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activate')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->icon('heroicon-o-x-mark')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
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
