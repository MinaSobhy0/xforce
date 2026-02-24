<?php

namespace Modules\Booking\Filament\Pages;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Modules\Booking\Models\Appointment;
use Modules\Patients\Models\Patient;
use Modules\Patients\Models\PatientNote;
use Modules\Patients\Models\PatientPhoto;
use Modules\Patients\Models\PatientMedicalHistory;
use Modules\TreatmentPlans\Models\TreatmentPlan;
use Modules\TreatmentPlans\Models\TreatmentPlanItem;
use Modules\Services\Models\Service;

class TreatmentSession extends Page implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;
    use ChecksResourcePermissions;
    use WithFileUploads;

    protected static ?string $moduleCode = 'booking';
    protected static ?string $permissionKey = 'appointments';
    protected static ?string $navigationIcon = 'heroicon-o-play-circle';
    protected static ?string $slug = 'treatment-session';
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'booking::filament.pages.treatment-session';

    // Query string parameter for appointment
    #[Url]
    public ?string $appointment_id = null;

    public ?Appointment $appointment = null;
    public ?Patient $patient = null;
    public ?PatientMedicalHistory $medicalHistory = null;

    // Forms
    public ?string $noteContent = null;
    public ?string $noteType = 'treatment';
    public $photoUpload = null;
    public ?string $photoType = 'progress';
    public ?string $photoBodyArea = null;
    public ?string $photoDescription = null;

    // Treatment plan form
    public ?array $treatmentPlanData = [];

    public function mount(): void
    {
        $this->loadAppointment();

        if (!$this->appointment) {
            Notification::make()
                ->title(__('booking::session.messages.appointment_not_found'))
                ->danger()
                ->send();
            $this->redirect(DoctorDashboard::getUrl());
            return;
        }

        // Ensure appointment is in progress
        if ($this->appointment->status !== Appointment::STATUS_IN_PROGRESS) {
            Notification::make()
                ->title(__('booking::session.messages.session_not_active'))
                ->danger()
                ->send();
            $this->redirect(DoctorDashboard::getUrl());
            return;
        }

        $this->treatmentPlanData = [
            'name' => '',
            'services' => [
                ['service_id' => null, 'sessions' => 1, 'interval' => 7]
            ],
            'notes' => '',
        ];
    }

    protected function loadAppointment(): void
    {
        $this->appointment = Appointment::with([
            'patient.medicalHistory',
            'service',
            'practitioner',
            'room',
            'branch',
            'treatmentPlanAppointment.item.treatmentPlan',
        ])->find($this->appointment_id);

        if ($this->appointment) {
            $this->patient = $this->appointment->patient;
            $this->medicalHistory = $this->patient?->medicalHistory;
        }
    }

    public function getTitle(): string
    {
        return __('booking::session.title');
    }

    public function getHeading(): string
    {
        if ($this->patient) {
            return $this->patient->full_name . ' - ' . $this->appointment?->service?->translated_name;
        }
        return __('booking::session.heading');
    }

    public function getSubheading(): ?string
    {
        if ($this->appointment?->treatmentPlanAppointment) {
            $planAppt = $this->appointment->treatmentPlanAppointment;
            return __('booking::session.session_of', [
                'current' => $planAppt->session_number,
                'total' => $planAppt->item->recommended_sessions,
            ]);
        }
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('complete')
                ->label(__('booking::session.actions.complete_session'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->size('lg')
                ->requiresConfirmation()
                ->modalHeading(__('booking::session.modals.complete_session'))
                ->modalDescription(__('booking::session.modals.complete_session_desc'))
                ->action(fn () => $this->completeSession()),

            Action::make('back')
                ->label(__('booking::session.actions.back_to_dashboard'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(DoctorDashboard::getUrl()),
        ];
    }

    public function completeSession(): void
    {
        if (!$this->appointment) {
            return;
        }

        DB::transaction(function () {
            $this->appointment->complete();

            // Update treatment plan progress if linked
            if ($planAppointment = $this->appointment->treatmentPlanAppointment) {
                $planAppointment->update(['status' => Appointment::STATUS_COMPLETED]);
                $planAppointment->item->incrementCompletedSessions();
                $planAppointment->item->treatmentPlan->checkAndMarkComplete();
            }
        });

        Notification::make()
            ->title(__('booking::session.messages.session_completed'))
            ->success()
            ->send();

        $this->redirect(DoctorDashboard::getUrl());
    }

    public function patientInfolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->patient)
            ->schema([
                Components\Grid::make(4)
                    ->schema([
                        Components\TextEntry::make('code')
                            ->label(__('booking::session.patient.code'))
                            ->copyable(),
                        Components\TextEntry::make('full_name')
                            ->label(__('booking::session.patient.name')),
                        Components\TextEntry::make('phone')
                            ->label(__('booking::session.patient.phone'))
                            ->copyable(),
                        Components\TextEntry::make('age')
                            ->label(__('booking::session.patient.age'))
                            ->suffix(' ' . __('booking::session.patient.years')),
                    ]),
            ]);
    }

    public function medicalInfolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->medicalHistory)
            ->schema([
                Components\Grid::make(4)
                    ->schema([
                        Components\TextEntry::make('fitzpatrick_type')
                            ->label(__('booking::session.medical.fitzpatrick'))
                            ->badge()
                            ->formatStateUsing(fn ($state) => PatientMedicalHistory::FITZPATRICK_TYPES[$state] ?? $state),
                        Components\TextEntry::make('blood_type')
                            ->label(__('booking::session.medical.blood_type')),
                        Components\TextEntry::make('bmi')
                            ->label(__('booking::session.medical.bmi')),
                        Components\TextEntry::make('is_smoker')
                            ->label(__('booking::session.medical.smoker'))
                            ->badge()
                            ->color(fn ($state) => $state ? 'danger' : 'success')
                            ->formatStateUsing(fn ($state) => $state ? __('Yes') : __('No')),
                    ]),
            ]);
    }

    public function getAllergies(): array
    {
        return $this->medicalHistory?->allergies ?? [];
    }

    public function getContraindications(): array
    {
        return $this->medicalHistory?->contraindications ?? [];
    }

    public function getMedications(): array
    {
        return $this->medicalHistory?->current_medications ?? [];
    }

    public function getMedicalConditions(): array
    {
        return $this->medicalHistory?->medical_conditions ?? [];
    }

    public function getPatientNotes(): Collection
    {
        if (!$this->patient) {
            return collect();
        }

        return PatientNote::where('patient_id', $this->patient->id)
            ->with('createdBy')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    public function getPatientPhotos(): Collection
    {
        if (!$this->patient) {
            return collect();
        }

        return PatientPhoto::where('patient_id', $this->patient->id)
            ->with('media')
            ->orderByDesc('taken_at')
            ->limit(12)
            ->get();
    }

    public function getActiveTreatmentPlans(): Collection
    {
        if (!$this->patient) {
            return collect();
        }

        return TreatmentPlan::query()
            ->forPatient($this->patient->id)
            ->active()
            ->with('items.service')
            ->get();
    }

    public function getCurrentTreatmentPlan(): ?TreatmentPlan
    {
        if (!$this->appointment?->treatmentPlanAppointment) {
            return null;
        }

        return $this->appointment->treatmentPlanAppointment->item->treatmentPlan;
    }

    public function addNote(): void
    {
        if (empty($this->noteContent)) {
            Notification::make()
                ->title(__('booking::session.messages.note_required'))
                ->warning()
                ->send();
            return;
        }

        PatientNote::create([
            'patient_id' => $this->patient->id,
            'appointment_id' => $this->appointment->id,
            'type' => $this->noteType,
            'subject' => __('booking::session.notes.session_note') . ' - ' . $this->appointment->service?->translated_name,
            'content' => $this->noteContent,
            'created_by' => auth()->id(),
        ]);

        $this->noteContent = null;

        Notification::make()
            ->title(__('booking::session.messages.note_added'))
            ->success()
            ->send();
    }

    public function uploadPhoto(): void
    {
        if (!$this->photoUpload) {
            Notification::make()
                ->title(__('booking::session.messages.photo_required'))
                ->warning()
                ->send();
            return;
        }

        $photo = PatientPhoto::create([
            'patient_id' => $this->patient->id,
            'appointment_id' => $this->appointment->id,
            'service_id' => $this->appointment->service_id,
            'type' => $this->photoType,
            'body_area' => $this->photoBodyArea,
            'description' => $this->photoDescription ?? $this->getPhotoDescription(),
            'taken_at' => now(),
            'taken_by' => auth()->id(),
        ]);

        $photo->addMedia($this->photoUpload->getRealPath())
            ->usingName($this->getPhotoName())
            ->toMediaCollection('photos');

        $this->photoUpload = null;
        $this->photoDescription = null;

        Notification::make()
            ->title(__('booking::session.messages.photo_uploaded'))
            ->success()
            ->send();
    }

    protected function getPhotoDescription(): string
    {
        $sessionNum = '';
        if ($planAppointment = $this->appointment?->treatmentPlanAppointment) {
            $sessionNum = ' - Session ' . $planAppointment->session_number;
        }

        return $this->appointment?->service?->translated_name . $sessionNum . ' - ' . now()->format('M d, Y');
    }

    protected function getPhotoName(): string
    {
        return 'patient_' . ($this->patient?->id ?? 'unknown') . '_' . now()->format('Y-m-d_His');
    }

    public function createTreatmentPlan(): void
    {
        $this->validate([
            'treatmentPlanData.name' => 'required|string|max:255',
            'treatmentPlanData.services' => 'required|array|min:1',
            'treatmentPlanData.services.*.service_id' => 'required|exists:services,id',
            'treatmentPlanData.services.*.sessions' => 'required|integer|min:1|max:100',
            'treatmentPlanData.services.*.interval' => 'required|integer|min:1|max:365',
        ]);

        try {
            DB::transaction(function () {
                $plan = TreatmentPlan::create([
                    'patient_id' => $this->patient->id,
                    'branch_id' => $this->appointment->branch_id,
                    'created_by_user_id' => auth()->id(),
                    'name' => ['en' => $this->treatmentPlanData['name'], 'ar' => $this->treatmentPlanData['name']],
                    'status' => TreatmentPlan::STATUS_ACTIVE,
                    'source' => TreatmentPlan::SOURCE_CONSULTATION,
                    'start_date' => today(),
                    'notes' => $this->treatmentPlanData['notes'] ?? null,
                ]);

                foreach ($this->treatmentPlanData['services'] as $index => $serviceData) {
                    $service = Service::find($serviceData['service_id']);

                    TreatmentPlanItem::create([
                        'tenant_id' => $plan->tenant_id,
                        'treatment_plan_id' => $plan->id,
                        'service_id' => $serviceData['service_id'],
                        'item_type' => TreatmentPlanItem::TYPE_SERVICE,
                        'recommended_sessions' => $serviceData['sessions'],
                        'session_interval_days' => $serviceData['interval'],
                        'unit_price_minor' => $service?->base_price_minor ?? 0,
                        'sort_order' => $index,
                    ]);
                }

                $plan->recalculateFinancials();
            });

            $this->treatmentPlanData = [
                'name' => '',
                'services' => [['service_id' => null, 'sessions' => 1, 'interval' => 7]],
                'notes' => '',
            ];

            Notification::make()
                ->title(__('booking::session.messages.plan_created'))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('booking::session.messages.plan_creation_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function addServiceRow(): void
    {
        $this->treatmentPlanData['services'][] = [
            'service_id' => null,
            'sessions' => 1,
            'interval' => 7,
        ];
    }

    public function removeServiceRow(int $index): void
    {
        if (count($this->treatmentPlanData['services']) > 1) {
            unset($this->treatmentPlanData['services'][$index]);
            $this->treatmentPlanData['services'] = array_values($this->treatmentPlanData['services']);
        }
    }

    public function getAvailableServices(): array
    {
        return Service::query()
            ->active()
            ->ordered()
            ->get()
            ->mapWithKeys(fn ($s) => [$s->id => $s->translated_name])
            ->toArray();
    }

    public function getPreviousAppointments(): Collection
    {
        if (!$this->patient) {
            return collect();
        }

        return Appointment::where('patient_id', $this->patient->id)
            ->where('id', '!=', $this->appointment->id)
            ->where('status', Appointment::STATUS_COMPLETED)
            ->with(['service', 'practitioner'])
            ->orderByDesc('date')
            ->limit(5)
            ->get();
    }
}
