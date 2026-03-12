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
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Actions\Action;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Modules\Booking\Models\Appointment;
use Modules\Patients\Models\Patient;
use Modules\Patients\Models\PatientNote;
use Modules\Patients\Models\PatientPhoto;
use Modules\Patients\Models\PatientMedicalHistory;
use Modules\TreatmentPlans\Models\TreatmentPlan;
use Modules\TreatmentPlans\Models\TreatmentPlanItem;
use Modules\TreatmentPlans\Models\TreatmentPlanAppointment;
use Modules\Services\Models\Service;
use Modules\Auth\Models\User;
use Carbon\Carbon;

class DoctorDashboard extends Page implements HasForms
{
    use InteractsWithForms;
    use ChecksResourcePermissions;
    use WithFileUploads;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $permissionKey = 'doctor_dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'doctor-dashboard';

    protected static string $view = 'booking::filament.pages.doctor-dashboard';

    // Active session data
    public ?string $activeAppointmentId = null;
    public ?array $activePatientData = null;
    public string $activeTab = 'info';

    // Session note form
    public ?string $sessionNoteContent = null;
    public ?string $sessionNoteType = 'treatment';

    // Photo upload
    public $photoUpload = null;
    public ?string $photoType = 'progress';
    public ?string $photoBodyArea = null;
    public ?string $photoDescription = null;

    // Treatment plan form
    public ?array $treatmentPlanData = [];

    // Admin practitioner selector
    public ?string $selectedPractitionerId = null;

    // Date selector
    public ?string $selectedDate = null;

    public static function getNavigationLabel(): string
    {
        return __('booking::dashboard.navigation');
    }

    public function getTitle(): string
    {
        return __('booking::dashboard.title');
    }

    public function getHeading(): string
    {
        return __('booking::dashboard.heading');
    }

    protected function getHeaderWidgetsData(): array
    {
        return [
            'selectedPractitionerId' => $this->selectedPractitionerId,
            'selectedDate' => $this->selectedDate,
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \Modules\Booking\Filament\Widgets\DoctorDashboardStatsWidget::class,
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Only show for practitioners
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        return $user->hasAnyRole(['doctor', 'nurse', 'technician', 'admin', 'manager']);
    }

    public function mount(): void
    {
        $this->treatmentPlanData = [
            'name' => '',
            'services' => [
                ['service_id' => null, 'sessions' => 1, 'interval' => 7]
            ],
            'recommended_package_id' => null,
            'notes' => '',
        ];

        // For admins, default to current user (they can change later)
        // For non-admins, always use current user
        $this->selectedPractitionerId = (string) auth()->id();

        // Default to today's date
        $this->selectedDate = today()->format('Y-m-d');
    }

    /**
     * Check if current user can select other practitioners (admin/manager/super_admin).
     */
    public function canSelectPractitioner(): bool
    {
        $user = auth()->user();
        return $user && $user->hasAnyRole(['super_admin', 'admin', 'manager']);
    }

    /**
     * Get list of practitioners for the selector.
     */
    public function getPractitioners(): array
    {
        return User::query()
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['doctor', 'nurse', 'technician']);
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->mapWithKeys(fn ($user) => [$user->id => $user->full_name])
            ->toArray();
    }

    /**
     * Get the currently selected practitioner's name.
     */
    public function getSelectedPractitionerName(): ?string
    {
        if (!$this->selectedPractitionerId) {
            return null;
        }

        $user = User::find($this->selectedPractitionerId);
        return $user?->full_name;
    }

    /**
     * Change the selected practitioner (for admins).
     */
    public function selectPractitioner(string $practitionerId): void
    {
        if (!$this->canSelectPractitioner()) {
            return;
        }

        $this->selectedPractitionerId = $practitionerId;
    }

    /**
     * Get appointments for the selected date and practitioner.
     */
    public function getAppointmentsForDate(): Collection
    {
        // Use selected practitioner for admins, current user for others
        $practitionerId = $this->canSelectPractitioner()
            ? ($this->selectedPractitionerId ?? auth()->id())
            : auth()->id();

        $date = $this->selectedDate ? Carbon::parse($this->selectedDate) : today();

        return Appointment::query()
            ->with(['patient', 'service', 'room', 'treatmentPlanAppointment.item', 'packageSubscription.package.items'])
            ->forDate($date)
            ->forPractitioner($practitionerId)
            ->ordered()
            ->get();
    }

    /**
     * Alias for backward compatibility.
     */
    public function getTodayAppointments(): Collection
    {
        return $this->getAppointmentsForDate();
    }

    /**
     * Check if viewing a past date.
     */
    public function isViewingPastDate(): bool
    {
        if (!$this->selectedDate) {
            return false;
        }
        // Compare date strings to avoid timezone issues
        return $this->selectedDate < today()->format('Y-m-d');
    }

    /**
     * Check if viewing today.
     */
    public function isViewingToday(): bool
    {
        if (!$this->selectedDate) {
            return true;
        }
        // Compare date strings to avoid timezone issues
        return $this->selectedDate === today()->format('Y-m-d');
    }

    /**
     * Get the formatted selected date for display.
     */
    public function getFormattedSelectedDate(): string
    {
        $date = $this->selectedDate ? Carbon::parse($this->selectedDate) : today();

        if ($date->isToday()) {
            return __('booking::dashboard.date.today');
        }
        if ($date->isYesterday()) {
            return __('booking::dashboard.date.yesterday');
        }
        if ($date->isTomorrow()) {
            return __('booking::dashboard.date.tomorrow');
        }

        return $date->format('D, M j, Y');
    }

    /**
     * Get appointments by status for the queue.
     */
    public function getAppointmentsByStatus(): array
    {
        $appointments = $this->getTodayAppointments();

        return [
            'checked_in' => $appointments->filter(fn ($a) => $a->status === Appointment::STATUS_CHECKED_IN),
            'confirmed' => $appointments->filter(fn ($a) => $a->status === Appointment::STATUS_CONFIRMED),
            'scheduled' => $appointments->filter(fn ($a) => $a->status === Appointment::STATUS_SCHEDULED),
            'in_progress' => $appointments->filter(fn ($a) => $a->status === Appointment::STATUS_IN_PROGRESS),
            'completed' => $appointments->filter(fn ($a) => $a->status === Appointment::STATUS_COMPLETED),
        ];
    }

    /**
     * Get statistics for the day.
     */
    public function getStatistics(): array
    {
        $appointments = $this->getTodayAppointments();

        return [
            'total' => $appointments->count(),
            'waiting' => $appointments->filter(fn ($a) => $a->status === Appointment::STATUS_CHECKED_IN)->count(),
            'in_progress' => $appointments->filter(fn ($a) => $a->status === Appointment::STATUS_IN_PROGRESS)->count(),
            'completed' => $appointments->filter(fn ($a) => $a->status === Appointment::STATUS_COMPLETED)->count(),
            'upcoming' => $appointments->filter(fn ($a) => in_array($a->status, [
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_CONFIRMED,
            ]))->count(),
        ];
    }

    /**
     * Get the active appointment object.
     */
    public function getActiveAppointment(): ?Appointment
    {
        if (!$this->activeAppointmentId) {
            return null;
        }

        return Appointment::with(['patient.medicalHistory', 'service', 'room', 'treatmentPlanAppointment.item.treatmentPlan'])
            ->find($this->activeAppointmentId);
    }

    /**
     * Start a session for an appointment.
     */
    public function startSession(string $appointmentId): void
    {
        $appointment = Appointment::findOrFail($appointmentId);

        // Allow starting from checked_in or confirmed status
        $allowedStatuses = [
            Appointment::STATUS_CHECKED_IN,
            Appointment::STATUS_CONFIRMED,
        ];

        if (!in_array($appointment->status, $allowedStatuses)) {
            Notification::make()
                ->title(__('booking::dashboard.messages.cannot_start'))
                ->body(__('booking::dashboard.messages.must_be_confirmed'))
                ->danger()
                ->send();
            return;
        }

        // If confirmed, check in first
        if ($appointment->status === Appointment::STATUS_CONFIRMED) {
            $appointment->checkIn();
        }

        $appointment->start();

        Notification::make()
            ->title(__('booking::dashboard.messages.session_started'))
            ->success()
            ->send();

        // Redirect to the Treatment Session page
        $this->redirect(TreatmentSession::getUrl() . '?appointment_id=' . $appointment->id);
    }

    /**
     * Resume an in-progress session.
     */
    public function resumeSession(string $appointmentId): void
    {
        $appointment = Appointment::findOrFail($appointmentId);

        if ($appointment->status !== Appointment::STATUS_IN_PROGRESS) {
            Notification::make()
                ->title(__('booking::dashboard.messages.cannot_resume'))
                ->danger()
                ->send();
            return;
        }

        // Redirect to the Treatment Session page
        $this->redirect(TreatmentSession::getUrl() . '?appointment_id=' . $appointment->id);
    }

    /**
     * View a completed session (read-only).
     */
    public function viewSession(string $appointmentId): void
    {
        $appointment = Appointment::findOrFail($appointmentId);

        // Redirect to the Treatment Session page in view mode
        $this->redirect(TreatmentSession::getUrl() . '?appointment_id=' . $appointment->id . '&view_mode=1');
    }

    /**
     * Reschedule an appointment - redirect to booking page with appointment data.
     */
    public function rescheduleAppointment(string $appointmentId): void
    {
        $appointment = Appointment::findOrFail($appointmentId);

        // Only allow rescheduling for appointments that haven't started or completed
        $allowedStatuses = [
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_CONFIRMED,
            Appointment::STATUS_CHECKED_IN,
        ];

        if (!in_array($appointment->status, $allowedStatuses)) {
            Notification::make()
                ->title(__('booking::dashboard.messages.cannot_reschedule'))
                ->body(__('booking::dashboard.messages.appointment_already_started'))
                ->danger()
                ->send();
            return;
        }

        // Redirect to the booking page with reschedule parameter
        $this->redirect(CreateBooking::getUrl() . '?reschedule_appointment_id=' . $appointment->id);
    }

    /**
     * Load patient data for the session workspace.
     */
    protected function loadPatientData(string $patientId): void
    {
        $patient = Patient::with(['medicalHistory'])->find($patientId);

        if (!$patient) {
            return;
        }

        $this->activePatientData = [
            'id' => $patient->id,
            'name' => $patient->full_name,
            'code' => $patient->code,
            'phone' => $patient->phone,
            'email' => $patient->email,
            'gender' => $patient->gender,
            'age' => $patient->age,
            'member_since' => $patient->created_at?->format('M Y'),
        ];
    }

    /**
     * Complete the active session.
     */
    public function completeSession(): void
    {
        $appointment = $this->getActiveAppointment();

        if (!$appointment || $appointment->status !== Appointment::STATUS_IN_PROGRESS) {
            Notification::make()
                ->title(__('booking::dashboard.messages.cannot_complete'))
                ->danger()
                ->send();
            return;
        }

        DB::transaction(function () use ($appointment) {
            $appointment->complete();

            // Update treatment plan progress if linked
            if ($planAppointment = $appointment->treatmentPlanAppointment) {
                $planAppointment->update(['status' => Appointment::STATUS_COMPLETED]);
                $planAppointment->item->incrementCompletedSessions();
                $planAppointment->item->treatmentPlan->checkAndMarkComplete();
            }
        });

        $this->activeAppointmentId = null;
        $this->activePatientData = null;
        $this->sessionNoteContent = null;

        Notification::make()
            ->title(__('booking::dashboard.messages.session_completed'))
            ->success()
            ->send();
    }

    /**
     * Cancel the active session and return to queue.
     */
    public function cancelActiveSession(): void
    {
        $this->activeAppointmentId = null;
        $this->activePatientData = null;
        $this->sessionNoteContent = null;
        $this->activeTab = 'info';
    }

    /**
     * Set the active tab.
     */
    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    /**
     * Add a session note.
     */
    public function addSessionNote(): void
    {
        if (empty($this->sessionNoteContent)) {
            Notification::make()
                ->title(__('booking::dashboard.messages.note_required'))
                ->warning()
                ->send();
            return;
        }

        $appointment = $this->getActiveAppointment();
        if (!$appointment) {
            return;
        }

        PatientNote::create([
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'type' => $this->sessionNoteType,
            'subject' => 'Session Note - ' . $appointment->service?->translated_name,
            'content' => $this->sessionNoteContent,
            'created_by' => auth()->id(),
        ]);

        $this->sessionNoteContent = null;

        Notification::make()
            ->title(__('booking::dashboard.messages.note_added'))
            ->success()
            ->send();
    }

    /**
     * Upload a patient photo.
     */
    public function uploadPhoto(): void
    {
        if (!$this->photoUpload) {
            Notification::make()
                ->title(__('booking::dashboard.messages.photo_required'))
                ->warning()
                ->send();
            return;
        }

        $appointment = $this->getActiveAppointment();
        if (!$appointment) {
            return;
        }

        $photo = PatientPhoto::create([
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'service_id' => $appointment->service_id,
            'type' => $this->photoType,
            'body_area' => $this->photoBodyArea,
            'description' => $this->photoDescription ?? $this->getSessionDescription(),
            'taken_at' => now(),
            'taken_by' => auth()->id(),
        ]);

        $photo->addMedia($this->photoUpload->getRealPath())
            ->usingName($this->getPhotoName())
            ->toMediaCollection('photos');

        $this->photoUpload = null;
        $this->photoDescription = null;

        Notification::make()
            ->title(__('booking::dashboard.messages.photo_uploaded'))
            ->success()
            ->send();
    }

    /**
     * Get a description for the session.
     */
    protected function getSessionDescription(): string
    {
        $appointment = $this->getActiveAppointment();
        if (!$appointment) {
            return 'Session photo';
        }

        $sessionNum = '';
        if ($planAppointment = $appointment->treatmentPlanAppointment) {
            $sessionNum = ' - Session ' . $planAppointment->session_number;
        }

        return $appointment->service?->translated_name . $sessionNum . ' - ' . now()->format('M d, Y');
    }

    /**
     * Get the photo filename.
     */
    protected function getPhotoName(): string
    {
        $appointment = $this->getActiveAppointment();
        return 'patient_' . ($appointment?->patient_id ?? 'unknown') . '_' . now()->format('Y-m-d_His');
    }

    /**
     * Get patient notes for the active appointment.
     */
    public function getPatientNotes(): Collection
    {
        $appointment = $this->getActiveAppointment();
        if (!$appointment) {
            return collect();
        }

        return PatientNote::where('patient_id', $appointment->patient_id)
            ->with('createdBy')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }

    /**
     * Get patient photos for the active appointment.
     */
    public function getPatientPhotos(): Collection
    {
        $appointment = $this->getActiveAppointment();
        if (!$appointment) {
            return collect();
        }

        return PatientPhoto::where('patient_id', $appointment->patient_id)
            ->with('media')
            ->orderByDesc('taken_at')
            ->limit(20)
            ->get();
    }

    /**
     * Get patient's medical history.
     */
    public function getPatientMedicalHistory(): ?PatientMedicalHistory
    {
        $appointment = $this->getActiveAppointment();
        if (!$appointment) {
            return null;
        }

        return PatientMedicalHistory::where('patient_id', $appointment->patient_id)->first();
    }

    /**
     * Get patient's active treatment plans.
     */
    public function getPatientTreatmentPlans(): Collection
    {
        $appointment = $this->getActiveAppointment();
        if (!$appointment) {
            return collect();
        }

        return TreatmentPlan::query()
            ->forPatient($appointment->patient_id)
            ->active()
            ->with('items.service')
            ->get();
    }

    /**
     * Create a new treatment plan from the session.
     */
    public function createTreatmentPlan(): void
    {
        $appointment = $this->getActiveAppointment();
        if (!$appointment) {
            return;
        }

        $this->validate([
            'treatmentPlanData.name' => 'required|string|max:255',
            'treatmentPlanData.services' => 'required|array|min:1',
            'treatmentPlanData.services.*.service_id' => 'required|exists:services,id',
            'treatmentPlanData.services.*.sessions' => 'required|integer|min:1|max:100',
            'treatmentPlanData.services.*.interval' => 'required|integer|min:1|max:365',
        ]);

        try {
            DB::transaction(function () use ($appointment) {
                $plan = TreatmentPlan::create([
                    'patient_id' => $appointment->patient_id,
                    'branch_id' => $appointment->branch_id,
                    'created_by_user_id' => auth()->id(),
                    'name' => ['en' => $this->treatmentPlanData['name'], 'ar' => $this->treatmentPlanData['name']],
                    'status' => TreatmentPlan::STATUS_ACTIVE,
                    'source' => TreatmentPlan::SOURCE_CONSULTATION,
                    'start_date' => today(),
                    'recommended_package_id' => $this->treatmentPlanData['recommended_package_id'] ?? null,
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

            // Reset form
            $this->treatmentPlanData = [
                'name' => '',
                'services' => [
                    ['service_id' => null, 'sessions' => 1, 'interval' => 7]
                ],
                'recommended_package_id' => null,
                'notes' => '',
            ];

            Notification::make()
                ->title(__('booking::dashboard.messages.plan_created'))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('booking::dashboard.messages.plan_creation_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Add a service row to treatment plan form.
     */
    public function addServiceRow(): void
    {
        $this->treatmentPlanData['services'][] = [
            'service_id' => null,
            'sessions' => 1,
            'interval' => 7,
        ];
    }

    /**
     * Remove a service row from treatment plan form.
     */
    public function removeServiceRow(int $index): void
    {
        if (count($this->treatmentPlanData['services']) > 1) {
            unset($this->treatmentPlanData['services'][$index]);
            $this->treatmentPlanData['services'] = array_values($this->treatmentPlanData['services']);
        }
    }

    /**
     * Mark an appointment as checked-in.
     */
    public function checkInAppointment(string $appointmentId): void
    {
        $appointment = Appointment::findOrFail($appointmentId);

        if (!$appointment->canTransitionTo(Appointment::STATUS_CHECKED_IN)) {
            Notification::make()
                ->title(__('booking::dashboard.messages.cannot_check_in'))
                ->danger()
                ->send();
            return;
        }

        $appointment->checkIn();

        // Check if this is a package session with unpaid balance
        if ($appointment->isPackageSession() && $appointment->packageSubscription) {
            $subscription = $appointment->packageSubscription;

            if ($subscription->hasBalance()) {
                $packageName = $subscription->package?->getTranslation('name', app()->getLocale()) ?? 'Package';
                $balance = format_money($subscription->balance_remaining_minor);
                $patientName = $appointment->patient?->full_name ?? 'Patient';

                Notification::make()
                    ->title(__('booking::appointments.notifications.package_balance_due'))
                    ->body(__('booking::appointments.notifications.package_balance_message', [
                        'patient' => $patientName,
                        'package' => $packageName,
                        'balance' => $balance,
                    ]))
                    ->warning()
                    ->persistent()
                    ->actions([
                        NotificationAction::make('pay')
                            ->label(__('booking::appointments.actions.pay_balance'))
                            ->url(route('filament.tenant.resources.package-subscriptions.view', $subscription->id))
                            ->button()
                            ->color('success'),
                        NotificationAction::make('dismiss')
                            ->label(__('booking::appointments.actions.dismiss'))
                            ->close(),
                    ])
                    ->send();

                return;
            }
        }

        Notification::make()
            ->title(__('booking::dashboard.messages.patient_checked_in'))
            ->success()
            ->send();
    }

    /**
     * Confirm an appointment.
     */
    public function confirmAppointment(string $appointmentId): void
    {
        $appointment = Appointment::findOrFail($appointmentId);

        if (!$appointment->canTransitionTo(Appointment::STATUS_CONFIRMED)) {
            Notification::make()
                ->title(__('booking::dashboard.messages.cannot_confirm'))
                ->danger()
                ->send();
            return;
        }

        $appointment->confirm();

        Notification::make()
            ->title(__('booking::dashboard.messages.appointment_confirmed'))
            ->success()
            ->send();
    }

    /**
     * Get the list of available services for treatment plan.
     */
    public function getAvailableServices(): array
    {
        return Service::query()
            ->active()
            ->ordered()
            ->get()
            ->mapWithKeys(fn ($s) => [$s->id => $s->translated_name])
            ->toArray();
    }

    /**
     * Get the session number for the active appointment.
     */
    public function getSessionNumber(): ?string
    {
        $appointment = $this->getActiveAppointment();
        if (!$appointment || !$appointment->treatmentPlanAppointment) {
            return null;
        }

        $planAppointment = $appointment->treatmentPlanAppointment;
        $item = $planAppointment->item;

        return "Session {$planAppointment->session_number} of {$item->recommended_sessions}";
    }
}
