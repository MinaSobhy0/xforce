<?php

namespace Modules\Booking\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\DB;
use Modules\Booking\Models\BookingRule;
use Modules\Booking\Models\BookingBlackoutDate;
use Modules\Core\Models\Setting;
use Modules\Core\Models\Branch;
use Modules\Services\Models\Service;
use Modules\Booking\Services\SlotGenerationService;
use Carbon\Carbon;
use App\Traits\ChecksResourcePermissions;

class BookingSlotConfigPage extends Page implements Forms\Contracts\HasForms, HasTable
{
    use Forms\Concerns\InteractsWithForms;
    use InteractsWithTable;
    use ChecksResourcePermissions;

    protected static ?string $moduleCode = 'booking';
    protected static ?string $permissionKey = 'appointments';

    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';
    protected static string $view = 'booking::filament.pages.booking-slot-config';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 50;
    protected static ?string $slug = 'booking-configuration';

    public ?array $data = [];

    // Preview properties
    public ?string $previewServiceId = null;
    public ?string $previewDate = null;
    public ?array $previewSlots = null;

    public static function getNavigationLabel(): string
    {
        return __('booking::config.booking_configuration');
    }

    public function getTitle(): string
    {
        return __('booking::config.booking_configuration');
    }

    public function getSubheading(): ?string
    {
        return __('booking::config.booking_config_description');
    }

    public function mount(): void
    {
        $this->form->fill($this->loadSettings());
        $this->previewDate = now()->addDay()->format('Y-m-d');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Settings')
                    ->tabs([
                        // Tab 1: Time & Duration Settings
                        Forms\Components\Tabs\Tab::make(__('booking::config.time_duration'))
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Forms\Components\Section::make(__('booking::config.slot_generation'))
                                    ->description(__('booking::config.slot_generation_desc'))
                                    ->schema([
                                        Forms\Components\TextInput::make('default_slot_duration')
                                            ->label(__('booking::config.default_slot_duration'))
                                            ->helperText(__('booking::config.default_slot_duration_help'))
                                            ->numeric()
                                            ->suffix(__('booking::config.minutes'))
                                            ->default(30)
                                            ->required(),

                                        Forms\Components\TextInput::make('slot_interval_minutes')
                                            ->label(__('booking::config.slot_interval'))
                                            ->helperText(__('booking::config.slot_interval_help'))
                                            ->numeric()
                                            ->suffix(__('booking::config.minutes'))
                                            ->nullable()
                                            ->placeholder(__('booking::config.use_service_duration')),

                                        Forms\Components\TextInput::make('buffer_minutes')
                                            ->label(__('booking::config.buffer_between_appointments'))
                                            ->helperText(__('booking::config.buffer_help'))
                                            ->numeric()
                                            ->suffix(__('booking::config.minutes'))
                                            ->default(5),
                                    ])
                                    ->columns(3),

                                Forms\Components\Section::make(__('booking::config.working_hours'))
                                    ->description(__('booking::config.working_hours_desc'))
                                    ->schema([
                                        Forms\Components\TimePicker::make('default_start_time')
                                            ->label(__('booking::config.default_start_time'))
                                            ->default('09:00')
                                            ->seconds(false),

                                        Forms\Components\TimePicker::make('default_end_time')
                                            ->label(__('booking::config.default_end_time'))
                                            ->default('21:00')
                                            ->seconds(false),
                                    ])
                                    ->columns(2),
                            ]),

                        // Tab 2: Booking Restrictions
                        Forms\Components\Tabs\Tab::make(__('booking::config.booking_restrictions'))
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Forms\Components\Section::make(__('booking::config.advance_booking'))
                                    ->schema([
                                        Forms\Components\TextInput::make('min_advance_hours')
                                            ->label(__('booking::config.min_advance_booking'))
                                            ->helperText(__('booking::config.min_advance_help'))
                                            ->numeric()
                                            ->suffix(__('booking::config.hours'))
                                            ->default(2),

                                        Forms\Components\TextInput::make('max_advance_booking_days')
                                            ->label(__('booking::config.max_advance_booking'))
                                            ->helperText(__('booking::config.max_advance_help'))
                                            ->numeric()
                                            ->suffix(__('booking::config.days'))
                                            ->default(60),

                                        Forms\Components\TextInput::make('cancellation_policy_hours')
                                            ->label(__('booking::config.cancellation_notice'))
                                            ->helperText(__('booking::config.cancellation_help'))
                                            ->numeric()
                                            ->suffix(__('booking::config.hours'))
                                            ->default(24),

                                        Forms\Components\Toggle::make('allow_same_day_booking')
                                            ->label(__('booking::config.allow_same_day'))
                                            ->default(true),
                                    ])
                                    ->columns(2),
                            ]),

                        // Tab 3: Online Booking
                        Forms\Components\Tabs\Tab::make(__('booking::config.online_booking'))
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Forms\Components\Section::make(__('booking::config.online_booking_settings'))
                                    ->schema([
                                        Forms\Components\Toggle::make('allow_online_booking')
                                            ->label(__('booking::config.enable_online_booking'))
                                            ->default(true)
                                            ->live(),

                                        Forms\Components\Toggle::make('auto_confirm_appointments')
                                            ->label(__('booking::config.auto_confirm'))
                                            ->helperText(__('booking::config.auto_confirm_help'))
                                            ->default(false),

                                        Forms\Components\Toggle::make('show_practitioner_selection')
                                            ->label(__('booking::config.show_practitioner_selection'))
                                            ->helperText(__('booking::config.practitioner_selection_help'))
                                            ->default(true),

                                        Forms\Components\Toggle::make('require_deposit')
                                            ->label(__('booking::config.require_deposit'))
                                            ->default(false)
                                            ->live(),

                                        Forms\Components\TextInput::make('deposit_percentage')
                                            ->label(__('booking::config.deposit_percentage'))
                                            ->numeric()
                                            ->suffix('%')
                                            ->default(25)
                                            ->visible(fn (Get $get) => $get('require_deposit')),
                                    ])
                                    ->columns(2),
                            ]),

                        // Tab 4: Capacity Settings
                        Forms\Components\Tabs\Tab::make(__('booking::config.capacity'))
                            ->icon('heroicon-o-user-group')
                            ->schema([
                                Forms\Components\Section::make(__('booking::config.practitioner_limits'))
                                    ->schema([
                                        Forms\Components\TextInput::make('max_daily_appointments_per_practitioner')
                                            ->label(__('booking::config.max_daily_per_practitioner'))
                                            ->helperText(__('booking::config.max_daily_help'))
                                            ->numeric()
                                            ->nullable()
                                            ->placeholder(__('booking::config.unlimited')),

                                        Forms\Components\TextInput::make('max_weekly_appointments_per_practitioner')
                                            ->label(__('booking::config.max_weekly_per_practitioner'))
                                            ->numeric()
                                            ->nullable()
                                            ->placeholder(__('booking::config.unlimited')),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make(__('booking::config.branch_limits'))
                                    ->schema([
                                        Forms\Components\TextInput::make('max_concurrent_appointments_per_branch')
                                            ->label(__('booking::config.max_concurrent_per_branch'))
                                            ->helperText(__('booking::config.max_concurrent_help'))
                                            ->numeric()
                                            ->nullable()
                                            ->placeholder(__('booking::config.unlimited')),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(BookingRule::query()->orderByDesc('priority'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('booking::config.rule_name'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('rule_type')
                    ->label(__('booking::config.type'))
                    ->badge()
                    ->color(fn (BookingRule $record) => $record->rule_type_color),

                Tables\Columns\TextColumn::make('scope_level')
                    ->label(__('booking::config.scope'))
                    ->formatStateUsing(fn (BookingRule $record) => $record->scope_description),

                Tables\Columns\TextColumn::make('priority')
                    ->label(__('booking::config.priority'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('booking::config.active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('rule_type')
                    ->options(BookingRule::RULE_TYPES),
                Tables\Filters\SelectFilter::make('scope_level')
                    ->options(BookingRule::SCOPE_LEVELS),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->icon('heroicon-o-pencil')
                    ->url(fn (BookingRule $record) => route('filament.tenant.resources.booking-rules.edit', $record)),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create')
                    ->label(__('booking::config.add_rule'))
                    ->icon('heroicon-o-plus')
                    ->url(route('filament.tenant.resources.booking-rules.create')),
                Tables\Actions\Action::make('view_all')
                    ->label(__('booking::config.view_all_rules'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(route('filament.tenant.resources.booking-rules.index'))
                    ->color('gray'),
            ])
            ->emptyStateHeading(__('booking::config.no_rules'))
            ->emptyStateDescription(__('booking::config.no_rules_desc'))
            ->emptyStateIcon('heroicon-o-document-text')
            ->paginated([5]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => is_array($value) ? json_encode($value) : $value,
                        'group' => 'booking',
                    ]
                );
            }
        });

        Notification::make()
            ->title(__('booking::config.settings_saved'))
            ->success()
            ->send();
    }

    public function generatePreview(): void
    {
        if (!$this->previewServiceId || !$this->previewDate) {
            Notification::make()
                ->title(__('booking::config.select_service_date'))
                ->warning()
                ->send();
            return;
        }

        try {
            $service = Service::find($this->previewServiceId);
            if (!$service) {
                $this->previewSlots = [];
                return;
            }

            // Get current tenant's first branch for preview
            $branch = Branch::first();
            if (!$branch) {
                $this->previewSlots = [];
                return;
            }

            $slotService = app(SlotGenerationService::class);
            $slots = $slotService->generateAvailableSlots(
                $this->previewServiceId,
                $branch->id,
                Carbon::parse($this->previewDate)
            );

            $this->previewSlots = $slots->map(function ($slot) {
                return [
                    'time' => $slot['start_time'] ?? 'N/A',
                    'available' => !empty($slot['available_practitioners']),
                    'practitioners' => collect($slot['available_practitioners'] ?? [])->pluck('name')->implode(', '),
                    'room' => $slot['room']['name'] ?? null,
                    'blocked_reason' => $slot['blocked_reason'] ?? null,
                ];
            })->take(20)->toArray();

            Notification::make()
                ->title(__('booking::config.preview_generated'))
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title(__('booking::config.preview_error'))
                ->body($e->getMessage())
                ->danger()
                ->send();
            $this->previewSlots = [];
        }
    }

    public function getUpcomingBlackouts(): \Illuminate\Support\Collection
    {
        return BookingBlackoutDate::active()
            ->upcoming()
            ->orderByDate()
            ->limit(5)
            ->get();
    }

    public function getActiveRulesCount(): int
    {
        return BookingRule::active()->count();
    }

    public function getBlackoutDatesCount(): int
    {
        return BookingBlackoutDate::active()->upcoming()->count();
    }

    protected function loadSettings(): array
    {
        $settings = Setting::where('group', 'booking')->pluck('value', 'key')->toArray();

        // Decode JSON values
        foreach ($settings as $key => $value) {
            if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
                $settings[$key] = json_decode($value, true) ?? $value;
            }
        }

        // Apply defaults
        return array_merge([
            'default_slot_duration' => 30,
            'buffer_minutes' => 5,
            'min_advance_hours' => 2,
            'max_advance_booking_days' => 60,
            'default_start_time' => '09:00',
            'default_end_time' => '21:00',
            'allow_same_day_booking' => true,
            'allow_online_booking' => true,
            'auto_confirm_appointments' => false,
            'show_practitioner_selection' => true,
            'require_deposit' => false,
            'deposit_percentage' => 25,
            'cancellation_policy_hours' => 24,
        ], $settings);
    }

    public function getServiceOptions(): array
    {
        return Service::where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function getViewData(): array
    {
        return [
            'upcomingBlackouts' => $this->getUpcomingBlackouts(),
            'activeRulesCount' => $this->getActiveRulesCount(),
            'blackoutDatesCount' => $this->getBlackoutDatesCount(),
            'serviceOptions' => $this->getServiceOptions(),
        ];
    }
}
