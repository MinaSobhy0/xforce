<?php

namespace Modules\Booking\Filament\Pages;

use App\Traits\ChecksResourcePermissions;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Booking\Models\BookingBlackoutDate;
use Modules\Booking\Models\BookingConfig;
use Modules\Booking\Services\SlotGenerationService;
use Modules\Core\Models\Branch;
use Modules\Services\Models\Service;

class BookingSlotConfigPage extends Page implements Forms\Contracts\HasForms
{
    use ChecksResourcePermissions;
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $permissionKey = 'booking_configuration';

    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static string $view = 'booking::filament.pages.booking-slot-config';

    protected static ?string $navigationGroup = 'Settings';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.booking');
    }

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'booking-configuration';

    public ?array $data = [];

    public ?string $selectedBranchId = null;

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
        $this->previewDate = now()->addDay()->format('Y-m-d');
        $this->loadConfiguration();
    }

    protected function loadConfiguration(): void
    {
        $this->loadConfigurationForBranch(null);
    }

    protected function loadConfigurationForBranch(?string $branchId): void
    {
        $config = BookingConfig::getForBranch($branchId);

        $this->form->fill([
            'selectedBranchId' => $branchId,
            'slot_duration' => $config->slot_duration,
            'slot_interval' => $config->slot_interval,
            'buffer_minutes' => $config->buffer_minutes,
            'check_doctor_schedule' => $config->check_doctor_schedule,
            'check_doctor_timeoff' => $config->check_doctor_timeoff,
            'max_per_doctor_daily' => $config->max_per_doctor_daily,
            'allow_doctor_overlap' => $config->allow_doctor_overlap,
            'allow_any_available_doctor' => $config->allow_any_available_doctor,
            'room_assignment' => $config->room_assignment,
            'check_room_availability' => $config->check_room_availability,
            'allow_room_overlap' => $config->allow_room_overlap,
            'equipment_assignment' => $config->equipment_assignment,
            'check_equipment_availability' => $config->check_equipment_availability,
            'allow_equipment_overlap' => $config->allow_equipment_overlap,
            'min_advance_hours' => $config->min_advance_hours,
            'max_advance_days' => $config->max_advance_days,
            'allow_same_day' => $config->allow_same_day,
            'same_day_cutoff' => $config->same_day_cutoff,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Branch Selector (for multi-branch override)
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('selectedBranchId')
                            ->label(__('booking::config.branch'))
                            ->placeholder(__('booking::config.all_branches'))
                            ->options(fn () => Branch::pluck('name', 'id'))
                            ->live()
                            ->afterStateUpdated(fn ($state) => $this->loadConfigurationForBranch($state))
                            ->helperText(__('booking::config.branch_config_help')),
                    ])
                    ->columns(1),

                // Step 1: Service & Time Configuration
                Forms\Components\Section::make(__('booking::config.step1_title'))
                    ->description(__('booking::config.step1_description'))
                    ->icon('heroicon-o-clock')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('slot_duration')
                                    ->label(__('booking::config.slot_duration'))
                                    ->helperText(__('booking::config.slot_duration_help'))
                                    ->numeric()
                                    ->required()
                                    ->suffix(__('booking::config.minutes'))
                                    ->default(30),

                                Forms\Components\TextInput::make('slot_interval')
                                    ->label(__('booking::config.slot_interval'))
                                    ->helperText(__('booking::config.slot_interval_help'))
                                    ->numeric()
                                    ->suffix(__('booking::config.minutes'))
                                    ->placeholder(__('booking::config.use_slot_duration')),

                                Forms\Components\TextInput::make('buffer_minutes')
                                    ->label(__('booking::config.buffer_minutes'))
                                    ->helperText(__('booking::config.buffer_help'))
                                    ->numeric()
                                    ->required()
                                    ->suffix(__('booking::config.minutes'))
                                    ->default(5),
                            ]),

                        Forms\Components\Placeholder::make('working_hours_info')
                            ->label(__('booking::config.working_hours'))
                            ->content(__('booking::config.working_hours_from_branch'))
                            ->columnSpanFull(),
                    ]),

                // Step 2: Doctor/Practitioner Configuration
                Forms\Components\Section::make(__('booking::config.step2_title'))
                    ->description(__('booking::config.step2_description'))
                    ->icon('heroicon-o-user')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('doctor_schedule_info')
                            ->label(__('booking::config.doctor_schedule'))
                            ->content(__('booking::config.doctor_schedule_from_work_schedule'))
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('check_doctor_schedule')
                                    ->label(__('booking::config.check_doctor_schedule'))
                                    ->helperText(__('booking::config.check_doctor_schedule_help'))
                                    ->default(true),

                                Forms\Components\Toggle::make('check_doctor_timeoff')
                                    ->label(__('booking::config.check_doctor_timeoff'))
                                    ->helperText(__('booking::config.check_doctor_timeoff_help'))
                                    ->default(true),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('max_per_doctor_daily')
                                    ->label(__('booking::config.max_per_doctor_daily'))
                                    ->helperText(__('booking::config.max_per_doctor_daily_help'))
                                    ->numeric()
                                    ->placeholder(__('booking::config.unlimited')),

                                Forms\Components\Toggle::make('allow_doctor_overlap')
                                    ->label(__('booking::config.allow_doctor_overlap'))
                                    ->helperText(__('booking::config.allow_doctor_overlap_help'))
                                    ->default(false),
                            ]),

                        Forms\Components\Toggle::make('allow_any_available_doctor')
                            ->label(__('booking::config.allow_any_available_doctor'))
                            ->helperText(__('booking::config.allow_any_available_doctor_help'))
                            ->default(false),
                    ]),

                // Step 3: Room Configuration
                Forms\Components\Section::make(__('booking::config.step3_title'))
                    ->description(__('booking::config.step3_description'))
                    ->icon('heroicon-o-building-office')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('room_assignment')
                                    ->label(__('booking::config.room_assignment'))
                                    ->options(BookingConfig::ROOM_ASSIGNMENT_OPTIONS)
                                    ->required()
                                    ->default(BookingConfig::ROOM_FROM_SERVICE),

                                Forms\Components\Toggle::make('check_room_availability')
                                    ->label(__('booking::config.check_room_availability'))
                                    ->helperText(__('booking::config.check_room_availability_help'))
                                    ->default(true),

                                Forms\Components\Toggle::make('allow_room_overlap')
                                    ->label(__('booking::config.allow_room_overlap'))
                                    ->helperText(__('booking::config.allow_room_overlap_help'))
                                    ->default(false),
                            ]),
                    ]),

                // Step 4: Equipment Configuration
                Forms\Components\Section::make(__('booking::config.step4_title'))
                    ->description(__('booking::config.step4_description'))
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('equipment_assignment')
                                    ->label(__('booking::config.equipment_assignment'))
                                    ->options(BookingConfig::EQUIPMENT_ASSIGNMENT_OPTIONS)
                                    ->required()
                                    ->default(BookingConfig::EQUIPMENT_FROM_SERVICE),

                                Forms\Components\Toggle::make('check_equipment_availability')
                                    ->label(__('booking::config.check_equipment_availability'))
                                    ->helperText(__('booking::config.check_equipment_availability_help'))
                                    ->default(true),

                                Forms\Components\Toggle::make('allow_equipment_overlap')
                                    ->label(__('booking::config.allow_equipment_overlap'))
                                    ->helperText(__('booking::config.allow_equipment_overlap_help'))
                                    ->default(false),
                            ]),
                    ]),

                // Advance Booking Section
                Forms\Components\Section::make(__('booking::config.advance_booking'))
                    ->description(__('booking::config.advance_booking_description'))
                    ->icon('heroicon-o-calendar-days')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('min_advance_hours')
                                    ->label(__('booking::config.min_advance_hours'))
                                    ->helperText(__('booking::config.min_advance_hours_help'))
                                    ->numeric()
                                    ->required()
                                    ->suffix(__('booking::config.hours'))
                                    ->default(2),

                                Forms\Components\TextInput::make('max_advance_days')
                                    ->label(__('booking::config.max_advance_days'))
                                    ->helperText(__('booking::config.max_advance_days_help'))
                                    ->numeric()
                                    ->required()
                                    ->suffix(__('booking::config.days'))
                                    ->default(60),

                                Forms\Components\Toggle::make('allow_same_day')
                                    ->label(__('booking::config.allow_same_day'))
                                    ->helperText(__('booking::config.allow_same_day_help'))
                                    ->live()
                                    ->default(true),
                            ]),

                        Forms\Components\TimePicker::make('same_day_cutoff')
                            ->label(__('booking::config.same_day_cutoff'))
                            ->helperText(__('booking::config.same_day_cutoff_help'))
                            ->seconds(false)
                            ->visible(fn (Get $get) => $get('allow_same_day'))
                            ->columnSpan(1),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('booking::config.save_configuration'))
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action('saveConfiguration'),

            Action::make('manage_blackouts')
                ->label(__('booking::config.manage_blackouts'))
                ->icon('heroicon-o-calendar-days')
                ->url(route('filament.tenant.resources.booking-blackout-dates.index'))
                ->color('gray'),
        ];
    }

    public function saveConfiguration(): void
    {
        $data = $this->form->getState();

        // Get branch ID from form data
        $branchId = $data['selectedBranchId'] ?? null;

        // Check if config exists for this branch
        // Note: No tenant_id needed - in schema-per-tenant, data is isolated by schema
        $existingQuery = \DB::table('booking_configs');
        if ($branchId) {
            $existingQuery->where('branch_id', $branchId);
        } else {
            $existingQuery->whereNull('branch_id');
        }
        $existing = $existingQuery->first();

        $configData = [
            'slot_duration' => $data['slot_duration'],
            'slot_interval' => $data['slot_interval'] ?: null,
            'buffer_minutes' => $data['buffer_minutes'],
            'check_doctor_schedule' => $data['check_doctor_schedule'] ? true : false,
            'check_doctor_timeoff' => $data['check_doctor_timeoff'] ? true : false,
            'max_per_doctor_daily' => $data['max_per_doctor_daily'] ?: null,
            'allow_doctor_overlap' => $data['allow_doctor_overlap'] ? true : false,
            'allow_any_available_doctor' => $data['allow_any_available_doctor'] ? true : false,
            'room_assignment' => $data['room_assignment'],
            'check_room_availability' => $data['check_room_availability'] ? true : false,
            'allow_room_overlap' => $data['allow_room_overlap'] ? true : false,
            'equipment_assignment' => $data['equipment_assignment'],
            'check_equipment_availability' => $data['check_equipment_availability'] ? true : false,
            'allow_equipment_overlap' => $data['allow_equipment_overlap'] ? true : false,
            'min_advance_hours' => $data['min_advance_hours'],
            'max_advance_days' => $data['max_advance_days'],
            'allow_same_day' => $data['allow_same_day'] ? true : false,
            'same_day_cutoff' => $data['allow_same_day'] ? $data['same_day_cutoff'] : null,
            'updated_at' => now(),
        ];

        if ($existing) {
            // Update existing
            \DB::table('booking_configs')
                ->where('id', $existing->id)
                ->update($configData);
        } else {
            // Insert new
            \DB::table('booking_configs')->insert(array_merge($configData, [
                'branch_id' => $branchId,
                'created_at' => now(),
            ]));
        }

        Notification::make()
            ->title(__('booking::config.configuration_saved'))
            ->success()
            ->send();
    }

    public function generatePreview(): void
    {
        if (! $this->previewServiceId || ! $this->previewDate) {
            Notification::make()
                ->title(__('booking::config.select_service_date'))
                ->warning()
                ->send();

            return;
        }

        try {
            $service = Service::find($this->previewServiceId);
            if (! $service) {
                $this->previewSlots = [];

                return;
            }

            $branch = $this->selectedBranchId
                ? Branch::find($this->selectedBranchId)
                : Branch::first();

            if (! $branch) {
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
                    'available' => ! empty($slot['available_practitioners']),
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

    public function getBlackoutDatesCount(): int
    {
        return BookingBlackoutDate::active()->upcoming()->count();
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
            'blackoutDatesCount' => $this->getBlackoutDatesCount(),
            'serviceOptions' => $this->getServiceOptions(),
        ];
    }
}
