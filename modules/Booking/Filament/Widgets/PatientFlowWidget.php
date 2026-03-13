<?php

namespace Modules\Booking\Filament\Widgets;

use App\Services\BranchContext;
use Carbon\Carbon;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Modules\Booking\Models\Appointment;
use Modules\Booking\Services\ReceptionService;

class PatientFlowWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'booking::filament.widgets.patient-flow';

    protected static ?string $pollingInterval = '15s';

    protected int|string|array $columnSpan = 'full';

    public ?string $selectedDate = null;

    // Modal state
    public bool $showRoomModal = false;

    public bool $showDoctorModal = false;

    public ?string $editingAppointmentId = null;

    public ?string $selectedRoomId = null;

    public ?string $selectedDoctorId = null;

    public function mount(?string $selectedDate = null): void
    {
        $this->selectedDate = $selectedDate ?? today()->format('Y-m-d');
    }

    #[On('dateChanged')]
    public function handleDateChange(string $date): void
    {
        $this->selectedDate = $date;
    }

    public function getPatientFlowData(): array
    {
        $receptionService = app(ReceptionService::class);
        $branchId = BranchContext::currentId();
        $date = Carbon::parse($this->selectedDate);

        return $receptionService->getPatientFlowData($branchId, $date);
    }

    public function getPatientsByRoom(): array
    {
        $receptionService = app(ReceptionService::class);
        $branchId = BranchContext::currentId();
        $date = Carbon::parse($this->selectedDate);

        return $receptionService->getPatientsByRoom($branchId, $date);
    }

    public function calculateWaitTime($appointment): ?array
    {
        $receptionService = app(ReceptionService::class);

        return $receptionService->calculateWaitTime($appointment);
    }

    public function getFlowLanes(): array
    {
        return [
            'arriving' => [
                'label' => __('booking::reception.flow.arriving'),
                'icon' => 'heroicon-o-clock',
                'color' => 'info',
                'description' => __('booking::reception.flow.arriving_desc'),
            ],
            'waiting' => [
                'label' => __('booking::reception.flow.waiting'),
                'icon' => 'heroicon-o-user-group',
                'color' => 'warning',
                'description' => __('booking::reception.flow.waiting_desc'),
            ],
            // Individual room columns are rendered dynamically
            'done' => [
                'label' => __('booking::reception.flow.done'),
                'icon' => 'heroicon-o-check-circle',
                'color' => 'success',
                'description' => __('booking::reception.flow.done_desc'),
            ],
        ];
    }

    public function isToday(): bool
    {
        return Carbon::parse($this->selectedDate)->isToday();
    }

    public function getSelectedDateFormatted(): string
    {
        return Carbon::parse($this->selectedDate)->format('l, M d, Y');
    }

    public function checkInAppointment(string $appointmentId): void
    {
        $appointment = \Modules\Booking\Models\Appointment::find($appointmentId);

        if (! $appointment) {
            \Filament\Notifications\Notification::make()
                ->title(__('booking::reception.messages.appointment_not_found'))
                ->danger()
                ->send();

            return;
        }

        if (! in_array($appointment->status, [
            \Modules\Booking\Models\Appointment::STATUS_SCHEDULED,
            \Modules\Booking\Models\Appointment::STATUS_CONFIRMED,
        ])) {
            \Filament\Notifications\Notification::make()
                ->title(__('booking::reception.messages.cannot_check_in'))
                ->danger()
                ->send();

            return;
        }

        // Check if practitioner is assigned - if not, prompt to assign first
        if (! $appointment->hasPractitionerAssigned()) {
            \Filament\Notifications\Notification::make()
                ->title(__('booking::reception.practitioner_required_for_checkin'))
                ->body(__('booking::reception.assign_doctor_first'))
                ->warning()
                ->send();
            // Open the doctor modal
            $this->openDoctorModal($appointmentId);

            return;
        }

        // Auto-confirm if still scheduled (state machine requires: scheduled -> confirmed -> checked_in)
        if ($appointment->status === \Modules\Booking\Models\Appointment::STATUS_SCHEDULED) {
            if (! $appointment->confirm()) {
                \Filament\Notifications\Notification::make()
                    ->title(__('booking::reception.messages.cannot_check_in'))
                    ->danger()
                    ->send();

                return;
            }
        }

        if ($appointment->checkIn()) {
            // Check if this is a package session with unpaid balance
            if ($appointment->isPackageSession() && $appointment->packageSubscription) {
                $subscription = $appointment->packageSubscription;

                if ($subscription->hasBalance()) {
                    $packageName = $subscription->package?->getTranslation('name', app()->getLocale()) ?? 'Package';
                    $balance = format_money($subscription->balance_remaining_minor);
                    $patientName = $appointment->patient?->full_name ?? 'Patient';

                    $notification = \Filament\Notifications\Notification::make()
                        ->title(__('booking::appointments.notifications.package_balance_due'))
                        ->body(__('booking::appointments.notifications.package_balance_message', [
                            'patient' => $patientName,
                            'package' => $packageName,
                            'balance' => $balance,
                        ]))
                        ->warning()
                        ->persistent();

                    // Add pay action only if invoice exists
                    if ($subscription->invoice_id) {
                        $notification->actions([
                            \Filament\Notifications\Actions\Action::make('pay')
                                ->label(__('booking::appointments.actions.pay_balance'))
                                ->url(route('filament.tenant.resources.invoices.view', ['record' => $subscription->invoice_id]))
                                ->button()
                                ->color('success'),
                            \Filament\Notifications\Actions\Action::make('dismiss')
                                ->label(__('booking::appointments.actions.dismiss'))
                                ->close(),
                        ]);
                    } else {
                        $notification->actions([
                            \Filament\Notifications\Actions\Action::make('dismiss')
                                ->label(__('booking::appointments.actions.dismiss'))
                                ->close(),
                        ]);
                    }

                    $notification->send();

                    return;
                }
            }

            \Filament\Notifications\Notification::make()
                ->title(__('booking::reception.messages.checked_in'))
                ->body(__('booking::reception.messages.checked_in_body', [
                    'patient' => $appointment->patient?->full_name ?? 'Patient',
                ]))
                ->success()
                ->send();
        } else {
            \Filament\Notifications\Notification::make()
                ->title(__('booking::reception.messages.cannot_check_in'))
                ->danger()
                ->send();
        }
    }

    public function canCheckIn($appointment): bool
    {
        return in_array($appointment->status, [
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_CONFIRMED,
        ]);
    }

    /**
     * Check if appointment can have room/doctor changed.
     */
    public function canChangeRoomOrDoctor($appointment): bool
    {
        return in_array($appointment->status, [
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_CONFIRMED,
            Appointment::STATUS_CHECKED_IN,
        ]);
    }

    /**
     * Open the room assignment modal.
     */
    public function openRoomModal(string $appointmentId): void
    {
        $appointment = Appointment::find($appointmentId);
        if (! $appointment) {
            return;
        }

        $this->editingAppointmentId = $appointmentId;
        $this->selectedRoomId = $appointment->room_id;
        $this->showRoomModal = true;
    }

    /**
     * Close the room modal.
     */
    public function closeRoomModal(): void
    {
        $this->showRoomModal = false;
        $this->editingAppointmentId = null;
        $this->selectedRoomId = null;
    }

    /**
     * Save the room assignment.
     */
    public function saveRoom(): void
    {
        $appointment = Appointment::find($this->editingAppointmentId);

        if (! $appointment) {
            \Filament\Notifications\Notification::make()
                ->title(__('booking::reception.messages.appointment_not_found'))
                ->danger()
                ->send();
            $this->closeRoomModal();

            return;
        }

        $appointment->update(['room_id' => $this->selectedRoomId ?: null]);

        $roomName = __('booking::reception.unassigned');
        if ($this->selectedRoomId) {
            $name = DB::table('rooms')->where('id', $this->selectedRoomId)->value('name');
            if ($name && is_string($name) && str_starts_with($name, '{')) {
                $decoded = json_decode($name, true);
                if (is_array($decoded)) {
                    $locale = app()->getLocale();
                    $fallbackLocale = config('app.fallback_locale', 'en');
                    $roomName = $decoded[$locale] ?? $decoded[$fallbackLocale] ?? reset($decoded) ?: $name;
                } else {
                    $roomName = $name;
                }
            } elseif ($name) {
                $roomName = $name;
            }
        }

        \Filament\Notifications\Notification::make()
            ->title(__('booking::reception.messages.room_assigned'))
            ->body(__('booking::reception.messages.room_assigned_body', [
                'patient' => $appointment->patient?->full_name ?? 'Patient',
                'room' => $roomName,
            ]))
            ->success()
            ->send();

        $this->closeRoomModal();
    }

    /**
     * Open the doctor assignment modal.
     */
    public function openDoctorModal(string $appointmentId): void
    {
        $appointment = Appointment::find($appointmentId);
        if (! $appointment) {
            return;
        }

        $this->editingAppointmentId = $appointmentId;
        $this->selectedDoctorId = $appointment->practitioner_id;
        $this->showDoctorModal = true;
    }

    /**
     * Close the doctor modal.
     */
    public function closeDoctorModal(): void
    {
        $this->showDoctorModal = false;
        $this->editingAppointmentId = null;
        $this->selectedDoctorId = null;
    }

    /**
     * Save the doctor assignment.
     */
    public function saveDoctor(): void
    {
        $appointment = Appointment::find($this->editingAppointmentId);

        if (! $appointment) {
            \Filament\Notifications\Notification::make()
                ->title(__('booking::reception.messages.appointment_not_found'))
                ->danger()
                ->send();
            $this->closeDoctorModal();

            return;
        }

        if (! $this->selectedDoctorId) {
            \Filament\Notifications\Notification::make()
                ->title(__('booking::reception.messages.practitioner_not_found'))
                ->danger()
                ->send();

            return;
        }

        $appointment->update(['practitioner_id' => $this->selectedDoctorId]);

        $doctorName = DB::table('users')->where('id', $this->selectedDoctorId)->value('first_name');

        \Filament\Notifications\Notification::make()
            ->title(__('booking::reception.messages.doctor_assigned'))
            ->body(__('booking::reception.messages.doctor_assigned_body', [
                'patient' => $appointment->patient?->full_name ?? 'Patient',
                'doctor' => $doctorName,
            ]))
            ->success()
            ->send();

        $this->closeDoctorModal();
    }

    /**
     * Get available rooms for the current branch.
     */
    public function getAvailableRooms(): array
    {
        $branchId = BranchContext::currentId();

        if (! $branchId) {
            return [];
        }

        $locale = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');

        return DB::table('rooms')
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->where('is_bookable', true)
            ->whereIn('room_type', ['treatment', 'consultation'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->select('id', 'name')
            ->get()
            ->mapWithKeys(function ($room) use ($locale, $fallbackLocale) {
                $name = $room->name;

                // Handle JSON translatable field
                if (is_string($name) && str_starts_with($name, '{')) {
                    $decoded = json_decode($name, true);
                    if (is_array($decoded)) {
                        $name = $decoded[$locale] ?? $decoded[$fallbackLocale] ?? reset($decoded) ?: $name;
                    }
                }

                return [(string) $room->id => $name];
            })
            ->toArray();
    }

    /**
     * Get available practitioners for the current branch.
     */
    public function getAvailableDoctors(): array
    {
        // Get practitioners with doctor/nurse/technician roles via Spatie permissions
        return DB::table('users')
            ->join('model_has_roles', function ($join) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', 'Modules\\Auth\\Models\\User');
            })
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('users.status', 'active')
            ->whereIn('roles.name', ['doctor', 'nurse', 'technician'])
            ->select('users.id', 'users.first_name', 'users.last_name')
            ->distinct()
            ->orderBy('users.first_name')
            ->get()
            ->mapWithKeys(fn ($user) => [$user->id => trim($user->first_name.' '.$user->last_name)])
            ->toArray();
    }

    /**
     * Get the appointment being edited.
     */
    public function getEditingAppointment(): ?Appointment
    {
        if (! $this->editingAppointmentId) {
            return null;
        }

        return Appointment::with(['patient', 'room', 'practitioner'])->find($this->editingAppointmentId);
    }

    /**
     * Navigate to visit checkout page.
     */
    public function goToCheckout(string $appointmentId): void
    {
        $appointment = Appointment::with('visits')->find($appointmentId);

        if (! $appointment) {
            \Filament\Notifications\Notification::make()
                ->title(__('booking::reception.messages.appointment_not_found'))
                ->danger()
                ->send();

            return;
        }

        // Get the current visit for this appointment
        $visit = $appointment->current_visit;

        if (! $visit) {
            \Filament\Notifications\Notification::make()
                ->title(__('booking::reception.messages.no_visit'))
                ->danger()
                ->send();

            return;
        }

        $this->redirect(
            \Modules\Booking\Filament\Pages\Checkout::getUrl(['visit_id' => $visit->id])
        );
    }

    /**
     * Navigate to package subscription invoice for balance payment.
     * Creates the invoice if it doesn't exist.
     */
    public function goToPackageInvoice(string $subscriptionId): void
    {
        $subscription = \Modules\Packages\Models\PackageSubscription::with('invoice')->find($subscriptionId);

        if (! $subscription) {
            \Filament\Notifications\Notification::make()
                ->title(__('booking::reception.messages.subscription_not_found'))
                ->danger()
                ->send();

            return;
        }

        // Check if invoice exists (not deleted)
        $invoiceExists = $subscription->invoice_id &&
            \Modules\Billing\Models\Invoice::where('id', $subscription->invoice_id)->exists();

        // If no invoice exists or was deleted, create a new one
        if (! $invoiceExists) {
            try {
                // Clear old invoice_id if it was deleted
                if ($subscription->invoice_id) {
                    $subscription->update(['invoice_id' => null]);
                }

                $packageService = app(\Modules\Packages\Services\PackageService::class);
                $packageService->createPackageInvoice($subscription, auth()->id());
                $subscription->refresh();
            } catch (\Exception $e) {
                \Filament\Notifications\Notification::make()
                    ->title(__('booking::reception.messages.invoice_creation_failed'))
                    ->danger()
                    ->send();

                return;
            }
        }

        $this->redirect(
            route('filament.tenant.resources.invoices.view', ['record' => $subscription->invoice_id])
        );
    }
}
