<?php

namespace Modules\Booking\Filament\Pages;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;
use Modules\Booking\Models\Appointment;
use Modules\Booking\Services\SlotGenerationService;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Room;
use Modules\Equipment\Models\Equipment;
use Modules\Packages\Models\Package;
use Modules\Packages\Models\PackageSubscription;
use Modules\Packages\Models\PackageSessionUsage;
use Modules\Patients\Models\Patient;
use Modules\Services\Models\Service;
use Modules\Auth\Models\User;
use Carbon\Carbon;

class CreateBooking extends Page implements HasForms
{
    use InteractsWithForms;
    use ChecksResourcePermissions;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $permissionKey = 'appointments';

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'booking::filament.pages.create-booking';

    // Form data
    public ?array $data = [];

    // Slot generation state
    public array $availableSlots = [];
    public array $bookingItems = [];

    public static function getNavigationLabel(): string
    {
        return __('booking::booking.navigation.create_booking');
    }

    public function getTitle(): string
    {
        return __('booking::booking.title.create_booking');
    }

    public function getHeading(): string
    {
        return __('booking::booking.heading.create_booking');
    }

    public function mount(): void
    {
        $this->form->fill([
            'branch_id' => current_branch_id(),
            'date_from' => today()->format('Y-m-d'),
            'date_to' => today()->addWeek()->format('Y-m-d'),
            'booking_type' => 'service',
            'source' => Appointment::SOURCE_PHONE,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Main content area - two columns
                Forms\Components\Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        // Left Column - Main Form (2/3 width)
                        Forms\Components\Group::make()
                            ->schema([
                                // Patient & Booking Type Section
                                Forms\Components\Section::make(__('booking::booking.sections.patient_service'))
                                    ->description(__('booking::booking.sections.patient_service_desc'))
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                // Patient Selection
                                                Forms\Components\Select::make('patient_id')
                                                    ->label(__('booking::booking.fields.patient'))
                                                    ->options(function () {
                                                        return Patient::query()
                                                            ->orderBy('first_name')
                                                            ->limit(50)
                                                            ->get()
                                                            ->mapWithKeys(fn (Patient $p) => [
                                                                $p->id => "{$p->full_name} ({$p->code})"
                                                            ]);
                                                    })
                                                    ->searchable()
                                                    ->getSearchResultsUsing(function (string $search): array {
                                                        return Patient::query()
                                                            ->where(function ($q) use ($search) {
                                                                $q->where('first_name', 'ilike', "%{$search}%")
                                                                    ->orWhere('last_name', 'ilike', "%{$search}%")
                                                                    ->orWhere('phone', 'ilike', "%{$search}%")
                                                                    ->orWhere('code', 'ilike', "%{$search}%");
                                                            })
                                                            ->limit(20)
                                                            ->get()
                                                            ->mapWithKeys(fn (Patient $p) => [
                                                                $p->id => "{$p->full_name} ({$p->code}) - {$p->phone}"
                                                            ])
                                                            ->toArray();
                                                    })
                                                    ->required()
                                                    ->live()
                                                    ->createOptionForm([
                                                        Forms\Components\Grid::make(2)
                                                            ->schema([
                                                                Forms\Components\TextInput::make('first_name')
                                                                    ->label(__('patients::patients.fields.first_name'))
                                                                    ->required()
                                                                    ->maxLength(100),
                                                                Forms\Components\TextInput::make('last_name')
                                                                    ->label(__('patients::patients.fields.last_name'))
                                                                    ->required()
                                                                    ->maxLength(100),
                                                            ]),
                                                        Forms\Components\TextInput::make('phone')
                                                            ->label(__('patients::patients.fields.phone'))
                                                            ->required()
                                                            ->tel()
                                                            ->maxLength(20),
                                                        Forms\Components\TextInput::make('email')
                                                            ->label(__('patients::patients.fields.email'))
                                                            ->email()
                                                            ->maxLength(255),
                                                    ])
                                                    ->createOptionUsing(function (array $data): string {
                                                        $patient = Patient::create($data);
                                                        return $patient->id;
                                                    }),

                                                // Booking Type
                                                Forms\Components\ToggleButtons::make('booking_type')
                                                    ->label(__('booking::booking.fields.booking_type'))
                                                    ->options([
                                                        'service' => __('booking::booking.booking_types.service'),
                                                        'package' => __('booking::booking.booking_types.package'),
                                                    ])
                                                    ->icons([
                                                        'service' => 'heroicon-o-sparkles',
                                                        'package' => 'heroicon-o-gift',
                                                    ])
                                                    ->default('service')
                                                    ->inline()
                                                    ->live()
                                                    ->required(),
                                            ]),

                                        // Service Selection (for service booking)
                                        Forms\Components\Fieldset::make(__('booking::booking.fields.services'))
                                            ->schema([
                                                Forms\Components\Repeater::make('services')
                                                    ->label('')
                                                    ->schema([
                                                        Forms\Components\Grid::make(3)
                                                            ->schema([
                                                                Forms\Components\Select::make('service_id')
                                                                    ->label(__('booking::booking.fields.service'))
                                                                    ->options(function () {
                                                                        return Service::query()
                                                                            ->active()
                                                                            ->ordered()
                                                                            ->get()
                                                                            ->mapWithKeys(fn (Service $s) => [
                                                                                $s->id => "{$s->translated_name} ({$s->duration_minutes} min)"
                                                                            ]);
                                                                    })
                                                                    ->searchable()
                                                                    ->required()
                                                                    ->live()
                                                                    ->afterStateUpdated(function ($state, Set $set) {
                                                                        if ($state) {
                                                                            $service = Service::find($state);
                                                                            if ($service) {
                                                                                $set('duration_override', $service->duration_minutes);
                                                                                $set('price_minor', $service->base_price_minor);
                                                                            }
                                                                        }
                                                                    })
                                                                    ->columnSpan(2),

                                                                Forms\Components\TextInput::make('duration_override')
                                                                    ->label(__('booking::booking.fields.duration'))
                                                                    ->numeric()
                                                                    ->suffix(__('booking::booking.minutes'))
                                                                    ->columnSpan(1),
                                                            ]),
                                                    ])
                                                    ->addActionLabel(__('booking::booking.actions.add_service'))
                                                    ->minItems(1)
                                                    ->maxItems(5)
                                                    ->reorderable(false)
                                                    ->defaultItems(1)
                                                    ->itemLabel(fn (array $state): ?string =>
                                                        isset($state['service_id'])
                                                            ? Service::find($state['service_id'])?->translated_name
                                                            : null
                                                    ),
                                            ])
                                            ->visible(fn (Get $get) => $get('booking_type') === 'service'),

                                        // Package Selection (for package booking)
                                        Forms\Components\Fieldset::make(__('booking::booking.fields.package'))
                                            ->schema([
                                                Forms\Components\Select::make('package_subscription_id')
                                                    ->label(__('booking::booking.fields.select_package'))
                                                    ->options(function (Get $get) {
                                                        $patientId = $get('patient_id');
                                                        if (!$patientId) {
                                                            return [];
                                                        }
                                                        return PackageSubscription::query()
                                                            ->forPatient($patientId)
                                                            ->active()
                                                            ->with('package')
                                                            ->get()
                                                            ->mapWithKeys(fn (PackageSubscription $sub) => [
                                                                $sub->id => "{$sub->package->translated_name} ({$sub->sessions_remaining} remaining)"
                                                            ]);
                                                    })
                                                    ->searchable()
                                                    ->live()
                                                    ->required(fn (Get $get) => $get('booking_type') === 'package')
                                                    ->helperText(fn (Get $get) => !$get('patient_id')
                                                        ? __('booking::booking.messages.select_patient_first')
                                                        : null),

                                                Forms\Components\Select::make('package_service_id')
                                                    ->label(__('booking::booking.fields.select_service_from_package'))
                                                    ->options(function (Get $get) {
                                                        $subscriptionId = $get('package_subscription_id');
                                                        if (!$subscriptionId) {
                                                            return [];
                                                        }
                                                        $subscription = PackageSubscription::with('package.items.service')->find($subscriptionId);
                                                        if (!$subscription) {
                                                            return [];
                                                        }
                                                        return $subscription->package->items
                                                            ->filter(fn ($item) => $subscription->hasRemainingSessionsForService($item->service_id))
                                                            ->mapWithKeys(fn ($item) => [
                                                                $item->service_id => "{$item->service->translated_name} ({$subscription->getSessionsRemainingByService($item->service_id)} remaining)"
                                                            ]);
                                                    })
                                                    ->required(fn (Get $get) => $get('booking_type') === 'package')
                                                    ->visible(fn (Get $get) => $get('package_subscription_id'))
                                                    ->live(),

                                                Forms\Components\TextInput::make('package_duration_override')
                                                    ->label(__('booking::booking.fields.duration_override'))
                                                    ->numeric()
                                                    ->suffix(__('booking::booking.minutes'))
                                                    ->helperText(__('booking::booking.fields.duration_override_help'))
                                                    ->visible(fn (Get $get) => $get('package_service_id')),
                                            ])
                                            ->visible(fn (Get $get) => $get('booking_type') === 'package'),
                                    ]),

                                // Schedule Section
                                Forms\Components\Section::make(__('booking::booking.sections.schedule'))
                                    ->description(__('booking::booking.sections.schedule_desc'))
                                    ->schema([
                                        Forms\Components\Grid::make(4)
                                            ->schema([
                                                Forms\Components\Select::make('branch_id')
                                                    ->label(__('booking::booking.fields.branch'))
                                                    ->options(function () {
                                                        return Branch::query()
                                                            ->active()
                                                            ->pluck('name', 'id');
                                                    })
                                                    ->default(fn () => current_branch_id())
                                                    ->required()
                                                    ->live(),

                                                Forms\Components\DatePicker::make('date_from')
                                                    ->label(__('booking::booking.fields.date_from'))
                                                    ->native(false)
                                                    ->minDate(today())
                                                    ->maxDate(today()->addDays(config('booking.max_advance_booking_days', 60)))
                                                    ->default(today())
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                        $dateTo = $get('date_to');
                                                        if ($dateTo && Carbon::parse($state)->gt(Carbon::parse($dateTo))) {
                                                            $set('date_to', Carbon::parse($state)->addWeek()->format('Y-m-d'));
                                                        }
                                                    }),

                                                Forms\Components\DatePicker::make('date_to')
                                                    ->label(__('booking::booking.fields.date_to'))
                                                    ->native(false)
                                                    ->minDate(fn (Get $get) => $get('date_from') ? Carbon::parse($get('date_from')) : today())
                                                    ->maxDate(today()->addDays(config('booking.max_advance_booking_days', 60)))
                                                    ->default(today()->addWeek())
                                                    ->required()
                                                    ->live(),

                                                Forms\Components\Select::make('source')
                                                    ->label(__('booking::booking.fields.source'))
                                                    ->options(Appointment::SOURCES)
                                                    ->default(Appointment::SOURCE_PHONE)
                                                    ->required(),
                                            ]),

                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('generate_slots')
                                                ->label(__('booking::booking.actions.generate_slots'))
                                                ->icon('heroicon-o-magnifying-glass')
                                                ->action(fn () => $this->generateSlots())
                                                ->color('primary')
                                                ->size('lg'),

                                            Forms\Components\Actions\Action::make('find_next_available')
                                                ->label(__('booking::booking.actions.find_next'))
                                                ->icon('heroicon-o-forward')
                                                ->action(fn () => $this->findNextAvailable())
                                                ->color('gray'),
                                        ])->fullWidth(),

                                        // Slot Grid - rendered via Blade in the page view
                                        Forms\Components\Placeholder::make('slot_grid')
                                            ->label('')
                                            ->content(fn () => new HtmlString(
                                                view('booking::components.inline-slot-grid', [
                                                    'slots' => $this->availableSlots,
                                                ])->render()
                                            ))
                                            ->visible(fn () => !empty($this->availableSlots)),

                                        Forms\Components\Placeholder::make('no_slots')
                                            ->label('')
                                            ->content(fn () => new HtmlString(
                                                '<div class="text-center py-8 text-gray-500">
                                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                    <p class="mt-2">' . __('booking::booking.messages.click_generate_slots') . '</p>
                                                </div>'
                                            ))
                                            ->visible(fn () => empty($this->availableSlots)),
                                    ]),

                                // Notes Section
                                Forms\Components\TextInput::make('notes')
                                    ->label(__('booking::booking.fields.notes'))
                                    ->maxLength(1000)
                                    ->placeholder(__('booking::booking.fields.notes'))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(['lg' => 2]),

                        // Right Column - Booking Cart (1/3 width)
                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Section::make(__('booking::booking.sections.booking_cart'))
                                    ->schema([
                                        Forms\Components\View::make('booking::components.inline-booking-cart')
                                            ->viewData([
                                                'items' => $this->bookingItems,
                                            ]),

                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('create_booking')
                                                ->label(__('booking::booking.actions.confirm_booking'))
                                                ->icon('heroicon-o-check')
                                                ->action(fn () => $this->createBookings())
                                                ->color('success')
                                                ->size('lg')
                                                ->disabled(fn () => empty($this->bookingItems)),
                                        ])->fullWidth(),
                                    ])
                                    ->extraAttributes(['class' => 'sticky top-4']),
                            ])
                            ->columnSpan(['lg' => 1]),
                    ]),
            ])
            ->statePath('data');
    }

    // Slot Generation

    public function generateSlots(): void
    {
        $data = $this->form->getState();
        $branchId = $data['branch_id'] ?? null;
        $dateFrom = $data['date_from'] ?? null;
        $dateTo = $data['date_to'] ?? null;
        $bookingType = $data['booking_type'] ?? 'service';

        if (!$branchId || !$dateFrom || !$dateTo) {
            Notification::make()
                ->title(__('booking::booking.validation.branch_date_required'))
                ->warning()
                ->send();
            return;
        }

        // Get service IDs based on booking type
        $serviceIds = [];
        $durations = [];

        if ($bookingType === 'service') {
            $services = $data['services'] ?? [];
            foreach ($services as $service) {
                if (!empty($service['service_id'])) {
                    $serviceIds[] = $service['service_id'];
                    $durations[$service['service_id']] = $service['duration_override'] ?? null;
                }
            }
        } else {
            $packageServiceId = $data['package_service_id'] ?? null;
            if ($packageServiceId) {
                $serviceIds[] = $packageServiceId;
                $durations[$packageServiceId] = $data['package_duration_override'] ?? null;
            }
        }

        if (empty($serviceIds)) {
            Notification::make()
                ->title(__('booking::booking.validation.service_required'))
                ->warning()
                ->send();
            return;
        }

        $slotService = app(SlotGenerationService::class);
        $startDate = Carbon::parse($dateFrom);
        $endDate = Carbon::parse($dateTo);
        $allSlots = [];

        // Iterate through each date in the range
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            foreach ($serviceIds as $serviceId) {
                $durationOverride = $durations[$serviceId] ?? null;
                $slots = $slotService->generateAvailableSlots(
                    $serviceId,
                    $branchId,
                    $currentDate,
                    $durationOverride ? (int) $durationOverride : null
                );

                foreach ($slots as $slot) {
                    $slot['service_id'] = $serviceId;
                    $slot['service_name'] = Service::find($serviceId)?->translated_name;
                    $slot['from_package'] = $bookingType === 'package' ? ($data['package_subscription_id'] ?? null) : null;
                    $allSlots[] = $slot;
                }
            }
            $currentDate->addDay();
        }

        $this->availableSlots = $allSlots;

        if (empty($this->availableSlots)) {
            Notification::make()
                ->title(__('booking::booking.messages.no_slots_available'))
                ->body(__('booking::booking.messages.try_different_date'))
                ->warning()
                ->send();
        } else {
            Notification::make()
                ->title(__('booking::booking.messages.slots_found'))
                ->body(__('booking::booking.messages.slots_count', ['count' => count($allSlots)]))
                ->success()
                ->send();
        }
    }

    public function findNextAvailable(): void
    {
        $data = $this->form->getState();
        $branchId = $data['branch_id'] ?? null;
        $bookingType = $data['booking_type'] ?? 'service';

        $serviceId = null;
        if ($bookingType === 'service') {
            $services = $data['services'] ?? [];
            $serviceId = $services[0]['service_id'] ?? null;
        } else {
            $serviceId = $data['package_service_id'] ?? null;
        }

        if (!$serviceId || !$branchId) {
            Notification::make()
                ->title(__('booking::booking.validation.service_branch_required'))
                ->warning()
                ->send();
            return;
        }

        $slotService = app(SlotGenerationService::class);
        $result = $slotService->findNextAvailableSlot(
            $serviceId,
            $branchId,
            Carbon::parse($data['date_from'] ?? today()),
            30
        );

        if ($result) {
            $foundDate = Carbon::parse($result['date']);
            $this->data['date_from'] = $result['date'];
            $this->data['date_to'] = $foundDate->copy()->addWeek()->format('Y-m-d');
            $this->generateSlots();

            Notification::make()
                ->title(__('booking::booking.messages.next_available_found'))
                ->body(__('booking::booking.messages.date_updated', ['date' => $result['date']]))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('booking::booking.messages.no_availability'))
                ->danger()
                ->send();
        }
    }

    #[On('slot-selected')]
    public function onSlotSelected(array $slot): void
    {
        // Add to booking items
        $item = [
            'service_id' => $slot['service_id'],
            'service_name' => $slot['service_name'] ?? Service::find($slot['service_id'])?->translated_name,
            'date' => $slot['date'],
            'start_time' => $slot['start_time'],
            'end_time' => $slot['end_time'],
            'duration' => $slot['duration'],
            'practitioner_id' => $slot['practitioner_id'] ?? $slot['available_practitioners'][0]['id'] ?? null,
            'practitioner_name' => $slot['practitioner_name'] ?? $slot['available_practitioners'][0]['name'] ?? null,
            'room_id' => $slot['room_id'],
            'room_name' => $slot['room_name'],
            'equipment_id' => $slot['equipment_id'],
            'equipment_name' => $slot['equipment_name'],
            'from_package' => $slot['from_package'] ?? null,
        ];

        $this->bookingItems[] = $item;

        Notification::make()
            ->title(__('booking::booking.messages.slot_added'))
            ->success()
            ->duration(2000)
            ->send();
    }

    public function removeBookingItem(int $index): void
    {
        if (isset($this->bookingItems[$index])) {
            unset($this->bookingItems[$index]);
            $this->bookingItems = array_values($this->bookingItems);
        }
    }

    public function clearCart(): void
    {
        $this->bookingItems = [];
    }

    // Booking Creation

    public function createBookings(): void
    {
        $data = $this->form->getState();

        if (empty($data['patient_id'])) {
            Notification::make()
                ->title(__('booking::booking.validation.patient_required'))
                ->danger()
                ->send();
            return;
        }

        if (empty($this->bookingItems)) {
            Notification::make()
                ->title(__('booking::booking.validation.slot_required'))
                ->danger()
                ->send();
            return;
        }

        $createdAppointments = [];

        try {
            foreach ($this->bookingItems as $item) {
                $service = Service::find($item['service_id']);

                $appointment = Appointment::create([
                    'patient_id' => $data['patient_id'],
                    'service_id' => $item['service_id'],
                    'branch_id' => $data['branch_id'],
                    'practitioner_id' => $item['practitioner_id'],
                    'room_id' => $item['room_id'],
                    'equipment_id' => $item['equipment_id'],
                    'date' => $item['date'],
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                    'duration_minutes' => $item['duration'],
                    'price_minor' => $service?->base_price_minor ?? 0,
                    'status' => Appointment::STATUS_SCHEDULED,
                    'source' => $data['source'] ?? Appointment::SOURCE_PHONE,
                    'notes' => $data['notes'] ?? null,
                ]);

                $createdAppointments[] = $appointment;

                // Record package usage if from package
                if (!empty($item['from_package'])) {
                    PackageSessionUsage::create([
                        'subscription_id' => $item['from_package'],
                        'service_id' => $item['service_id'],
                        'appointment_id' => $appointment->id,
                        'used_at' => now(),
                    ]);

                    // Check if package is now complete
                    $subscription = PackageSubscription::find($item['from_package']);
                    $subscription?->checkAndMarkComplete();
                }
            }

            $count = count($createdAppointments);

            Notification::make()
                ->title(__('booking::booking.messages.booking_created'))
                ->body(__('booking::booking.messages.appointments_created', ['count' => $count]))
                ->success()
                ->send();

            $this->redirect(route('filament.tenant.resources.appointments.index'));

        } catch (\Exception $e) {
            Notification::make()
                ->title(__('booking::booking.messages.booking_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
