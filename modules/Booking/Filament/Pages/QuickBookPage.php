<?php

namespace Modules\Booking\Filament\Pages;

use Modules\Booking\Models\Appointment;
use Modules\Booking\Services\AvailabilityService;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;
use Modules\Patients\Models\Patient;
use Modules\Treatments\Models\Treatment;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QuickBookPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'booking::filament.pages.quick-book';

    // Form state
    public ?string $patient_id = null;
    public ?string $treatment_id = null;
    public ?string $branch_id = null;
    public ?string $practitioner_id = null;
    public ?string $selected_date = null;

    // Selected slot
    public ?string $selected_slot_start = null;
    public ?string $selected_slot_end = null;
    public ?string $selected_slot_equipment_id = null;
    public ?string $selected_slot_room_id = null;
    public ?string $selected_slot_practitioner_id = null;

    // Available slots
    public array $availableSlots = [];

    public static function getNavigationLabel(): string
    {
        return __('booking::appointments.quick_book');
    }

    public function getTitle(): string
    {
        return __('booking::appointments.quick_book');
    }

    public function mount(): void
    {
        $this->selected_date = today()->format('Y-m-d');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('booking::appointments.sections.patient'))
                    ->schema([
                        Select::make('patient_id')
                            ->label(__('booking::appointments.fields.patient'))
                            ->options(function () {
                                return Patient::query()
                                    ->orderBy('first_name')
                                    ->limit(100)
                                    ->get()
                                    ->mapWithKeys(fn ($patient) => [
                                        $patient->id => $patient->full_name . ' (' . $patient->code . ')',
                                    ]);
                            })
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                return Patient::query()
                                    ->where(function ($q) use ($search) {
                                        $q->where('first_name', 'ilike', "%{$search}%")
                                            ->orWhere('last_name', 'ilike', "%{$search}%")
                                            ->orWhere('phone', 'like', "%{$search}%")
                                            ->orWhere('code', 'ilike', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn ($patient) => [
                                        $patient->id => $patient->full_name . ' (' . $patient->code . ')',
                                    ]);
                            })
                            ->preload()
                            ->required()
                            ->live(),
                    ]),

                Section::make(__('booking::appointments.sections.details'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('treatment_id')
                                    ->label(__('booking::appointments.fields.treatment'))
                                    ->options(function () {
                                        return Treatment::query()
                                            ->active()
                                            ->ordered()
                                            ->get()
                                            ->mapWithKeys(fn ($treatment) => [
                                                $treatment->id => $treatment->translated_name . ' (' . $treatment->duration_minutes . ' min)',
                                            ]);
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->loadSlots()),

                                Select::make('branch_id')
                                    ->label(__('booking::appointments.fields.branch'))
                                    ->options(Branch::pluck('name', 'id'))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->loadSlots()),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('practitioner_id')
                                    ->label(__('booking::appointments.fields.practitioner'))
                                    ->options(function () {
                                        $options = [
                                            'any' => __('booking::appointments.any_available'),
                                        ];

                                        $practitioners = User::query()
                                            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['doctor', 'nurse', 'technician']))
                                            ->orderBy('first_name')
                                            ->orderBy('last_name')
                                            ->get()
                                            ->mapWithKeys(fn ($user) => [
                                                $user->id => $user->full_name,
                                            ])
                                            ->toArray();

                                        return $options + $practitioners;
                                    })
                                    ->default('any')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->loadSlots()),

                                DatePicker::make('selected_date')
                                    ->label(__('booking::appointments.fields.date'))
                                    ->native(false)
                                    ->minDate(today())
                                    ->default(today())
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->loadSlots()),
                            ]),
                    ]),
            ]);
    }

    public function loadSlots(): void
    {
        $this->availableSlots = [];
        $this->clearSelectedSlot();

        if (!$this->treatment_id || !$this->branch_id || !$this->selected_date) {
            return;
        }

        $date = Carbon::parse($this->selected_date);
        $availabilityService = app(AvailabilityService::class);

        if ($this->practitioner_id === 'any' || empty($this->practitioner_id)) {
            // Get slots from any available practitioner
            $this->availableSlots = $availabilityService->getAvailableSlotsAnyPractitioner(
                $this->branch_id,
                $date,
                $this->treatment_id
            );
        } else {
            // Get slots for specific practitioner
            $slots = $availabilityService->getAvailableSlotsForTreatment(
                $this->practitioner_id,
                $this->branch_id,
                $date,
                $this->treatment_id
            );

            // Add practitioner info to slots
            $practitioner = User::find($this->practitioner_id);
            foreach ($slots as &$slot) {
                $slot['practitioner_id'] = $this->practitioner_id;
                $slot['practitioner_name'] = $practitioner?->full_name ?? $practitioner?->name ?? '';
            }

            $this->availableSlots = $slots;
        }
    }

    public function selectSlot(string $start, string $end, ?string $practitionerId = null, ?string $equipmentId = null, ?string $roomId = null): void
    {
        $this->selected_slot_start = $start;
        $this->selected_slot_end = $end;
        $this->selected_slot_practitioner_id = $practitionerId;
        $this->selected_slot_equipment_id = $equipmentId;
        $this->selected_slot_room_id = $roomId;
    }

    public function clearSelectedSlot(): void
    {
        $this->selected_slot_start = null;
        $this->selected_slot_end = null;
        $this->selected_slot_practitioner_id = null;
        $this->selected_slot_equipment_id = null;
        $this->selected_slot_room_id = null;
    }

    public function bookAppointment(): void
    {
        // Validate required fields
        if (!$this->patient_id) {
            Notification::make()
                ->title(__('booking::appointments.validation.patient_required'))
                ->danger()
                ->send();
            return;
        }

        if (!$this->treatment_id) {
            Notification::make()
                ->title(__('booking::appointments.validation.treatment_required'))
                ->danger()
                ->send();
            return;
        }

        if (!$this->selected_slot_start) {
            Notification::make()
                ->title(__('booking::appointments.select_slot'))
                ->danger()
                ->send();
            return;
        }

        $practitionerId = $this->selected_slot_practitioner_id;
        if (!$practitionerId) {
            Notification::make()
                ->title(__('booking::appointments.validation.practitioner_required'))
                ->danger()
                ->send();
            return;
        }

        try {
            DB::beginTransaction();

            $treatment = Treatment::find($this->treatment_id);
            $date = Carbon::parse($this->selected_date);

            // Calculate end time
            $startTime = Carbon::parse($this->selected_slot_start);
            $endTime = $startTime->copy()->addMinutes($treatment->duration_minutes);

            // Create the appointment
            $appointment = Appointment::create([
                'patient_id' => $this->patient_id,
                'treatment_id' => $this->treatment_id,
                'branch_id' => $this->branch_id,
                'practitioner_id' => $practitionerId,
                'room_id' => $this->selected_slot_room_id,
                'equipment_id' => $this->selected_slot_equipment_id,
                'date' => $date,
                'start_time' => $this->selected_slot_start,
                'end_time' => $endTime->format('H:i'),
                'duration_minutes' => $treatment->duration_minutes,
                'price_minor' => $treatment->getPriceForBranch($this->branch_id),
                'status' => Appointment::STATUS_SCHEDULED,
                'source' => Appointment::SOURCE_PHONE,
            ]);

            DB::commit();

            Notification::make()
                ->title(__('booking::appointments.booking_success'))
                ->body(__('booking::appointments.booking_success_body', ['code' => $appointment->code]))
                ->success()
                ->send();

            // Redirect to the appointment view
            $this->redirect(
                route('filament.tenant.resources.appointments.view', ['record' => $appointment->id])
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->title(__('booking::appointments.booking_error'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getSelectedSlotInfo(): ?array
    {
        if (!$this->selected_slot_start) {
            return null;
        }

        $practitioner = $this->selected_slot_practitioner_id
            ? User::find($this->selected_slot_practitioner_id)
            : null;

        $treatment = $this->treatment_id ? Treatment::find($this->treatment_id) : null;

        return [
            'start' => $this->selected_slot_start,
            'end' => $this->selected_slot_end,
            'practitioner_name' => $practitioner?->full_name ?? $practitioner?->name ?? '',
            'treatment_name' => $treatment?->translated_name ?? '',
            'duration' => $treatment?->duration_minutes ?? 0,
        ];
    }

    public function getFormattedDate(): string
    {
        if (!$this->selected_date) {
            return '';
        }
        return Carbon::parse($this->selected_date)->format('l, F j, Y');
    }
}
