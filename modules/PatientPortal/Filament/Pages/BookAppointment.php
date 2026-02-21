<?php

namespace Modules\PatientPortal\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Services\Models\Service;
use Modules\Core\Models\Branch;
use Modules\Booking\Models\Appointment;
use Modules\Auth\Models\User;
use Carbon\Carbon;

class BookAppointment extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-plus';

    protected static string $view = 'patientportal::filament.pages.book-appointment';

    protected static ?int $navigationSort = 2;

    public ?array $data = [];
    public ?string $selectedService = null;
    public ?string $selectedBranch = null;
    public ?string $selectedDate = null;
    public ?string $selectedTime = null;
    public ?string $selectedPractitioner = null;
    public array $availableSlots = [];

    public static function getNavigationLabel(): string
    {
        return __('patientportal::portal.book_appointment');
    }

    public function getTitle(): string
    {
        return __('patientportal::portal.book_appointment');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    Step::make(__('patientportal::portal.select_service'))
                        ->icon('heroicon-o-heart')
                        ->schema([
                            Select::make('service_id')
                                ->label(__('patientportal::portal.service'))
                                ->options($this->getServiceOptions())
                                ->searchable()
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(fn ($state) => $this->selectedService = $state),

                            Placeholder::make('service_info')
                                ->label('')
                                ->content(function ($get) {
                                    $serviceId = $get('service_id');
                                    if (!$serviceId) return '';

                                    $service = Service::find($serviceId);
                                    if (!$service) return '';

                                    return view('patientportal::components.service-info', [
                                        'service' => $service,
                                    ]);
                                })
                                ->visible(fn ($get) => filled($get('service_id'))),
                        ]),

                    Step::make(__('patientportal::portal.select_branch'))
                        ->icon('heroicon-o-building-office-2')
                        ->schema([
                            Select::make('branch_id')
                                ->label(__('patientportal::portal.branch'))
                                ->options($this->getBranchOptions())
                                ->searchable()
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(fn ($state) => $this->selectedBranch = $state),
                        ]),

                    Step::make(__('patientportal::portal.select_datetime'))
                        ->icon('heroicon-o-clock')
                        ->schema([
                            DatePicker::make('date')
                                ->label(__('patientportal::portal.date'))
                                ->minDate(now()->addHours(config('patientportal.booking.min_hours_before', 2)))
                                ->maxDate(now()->addDays(config('patientportal.booking.advance_days', 30)))
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(fn ($state) => $this->loadAvailableSlots($state)),

                            Radio::make('time_slot')
                                ->label(__('patientportal::portal.available_times'))
                                ->options(fn () => $this->getTimeSlotOptions())
                                ->required()
                                ->visible(fn ($get) => filled($get('date')) && count($this->availableSlots) > 0)
                                ->reactive(),

                            Placeholder::make('no_slots')
                                ->label('')
                                ->content(__('patientportal::portal.no_slots_available'))
                                ->visible(fn ($get) => filled($get('date')) && count($this->availableSlots) === 0),
                        ]),

                    Step::make(__('patientportal::portal.confirm'))
                        ->icon('heroicon-o-check-circle')
                        ->schema([
                            Placeholder::make('summary')
                                ->label(__('patientportal::portal.booking_summary'))
                                ->content(fn ($get) => view('patientportal::components.booking-summary', [
                                    'service' => Service::find($get('service_id')),
                                    'branch' => Branch::find($get('branch_id')),
                                    'date' => $get('date'),
                                    'time' => $get('time_slot'),
                                ])),
                        ]),
                ])
                ->submitAction(view('patientportal::components.confirm-button'))
                ->persistStepInQueryString(),
            ])
            ->statePath('data');
    }

    public function book(): void
    {
        $data = $this->form->getState();
        $patient = Auth::guard('patient')->user();

        // Parse the time slot
        $slot = $this->availableSlots[$data['time_slot']] ?? null;
        if (!$slot) {
            Notification::make()
                ->title(__('patientportal::portal.invalid_slot'))
                ->danger()
                ->send();
            return;
        }

        try {
            DB::beginTransaction();

            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'service_id' => $data['service_id'],
                'branch_id' => $data['branch_id'],
                'practitioner_id' => $slot['practitioner_id'],
                'date' => $data['date'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'duration_minutes' => $slot['duration'],
                'status' => 'scheduled',
                'booked_via' => 'portal',
                'notes' => __('patientportal::portal.booked_online'),
            ]);

            DB::commit();

            Notification::make()
                ->title(__('patientportal::portal.booking_success'))
                ->body(__('patientportal::portal.booking_confirmation', [
                    'date' => Carbon::parse($data['date'])->format('M d, Y'),
                    'time' => $slot['start_time'],
                ]))
                ->success()
                ->send();

            $this->redirect(MyAppointments::getUrl());

        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->title(__('patientportal::portal.booking_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getServiceOptions(): array
    {
        return Service::where('is_active', true)
            ->where('is_bookable_online', true)
            ->get()
            ->mapWithKeys(fn ($s) => [
                $s->id => $s->name . ' (' . number_format($s->price_minor / 100, 2) . ' EGP)',
            ])
            ->toArray();
    }

    protected function getBranchOptions(): array
    {
        return Branch::where('is_active', true)
            ->get()
            ->mapWithKeys(fn ($b) => [$b->id => $b->name])
            ->toArray();
    }

    protected function loadAvailableSlots(?string $date): void
    {
        if (!$date || !$this->data['service_id'] || !$this->data['branch_id']) {
            $this->availableSlots = [];
            return;
        }

        $service = Service::find($this->data['service_id']);
        $branchId = $this->data['branch_id'];
        $duration = $service->duration_minutes ?? 30;

        // Get practitioners who can perform this service at this branch
        $practitioners = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['practitioner', 'doctor', 'therapist']))
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->get();

        $slots = [];

        foreach ($practitioners as $practitioner) {
            // Get practitioner's working hours for this day
            $dayOfWeek = Carbon::parse($date)->dayOfWeek;
            $workingHours = $practitioner->working_hours[$dayOfWeek] ?? null;

            if (!$workingHours || !($workingHours['is_working'] ?? false)) {
                continue;
            }

            $startTime = Carbon::parse($date . ' ' . $workingHours['start']);
            $endTime = Carbon::parse($date . ' ' . $workingHours['end']);

            // Get existing appointments for this practitioner on this day
            $existingAppointments = Appointment::where('practitioner_id', $practitioner->id)
                ->where('date', $date)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->get(['start_time', 'end_time']);

            // Generate available slots
            $current = $startTime->copy();
            while ($current->copy()->addMinutes($duration)->lte($endTime)) {
                $slotEnd = $current->copy()->addMinutes($duration);

                // Check if slot conflicts with existing appointments
                $conflict = false;
                foreach ($existingAppointments as $existing) {
                    $existingStart = Carbon::parse($date . ' ' . $existing->start_time);
                    $existingEnd = Carbon::parse($date . ' ' . $existing->end_time);

                    if ($current->lt($existingEnd) && $slotEnd->gt($existingStart)) {
                        $conflict = true;
                        break;
                    }
                }

                if (!$conflict && $current->gt(now()->addHours(config('patientportal.booking.min_hours_before', 2)))) {
                    $key = $current->format('H:i') . '_' . $practitioner->id;
                    $slots[$key] = [
                        'practitioner_id' => $practitioner->id,
                        'practitioner_name' => $practitioner->name,
                        'start_time' => $current->format('H:i'),
                        'end_time' => $slotEnd->format('H:i'),
                        'duration' => $duration,
                    ];
                }

                $current->addMinutes(15); // 15-minute intervals
            }
        }

        // Sort by time
        ksort($slots);
        $this->availableSlots = $slots;
    }

    protected function getTimeSlotOptions(): array
    {
        return collect($this->availableSlots)
            ->mapWithKeys(fn ($slot, $key) => [
                $key => $slot['start_time'] . ' - ' . $slot['end_time'] . ' (' . $slot['practitioner_name'] . ')',
            ])
            ->toArray();
    }
}
