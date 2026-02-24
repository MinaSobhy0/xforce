<?php

namespace Modules\Booking\Filament\Widgets;

use App\Services\BranchContext;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;
use Modules\Booking\Services\ReceptionService;

class PatientFlowWidget extends Widget
{
    protected static string $view = 'booking::filament.widgets.patient-flow';

    protected static ?string $pollingInterval = '15s';

    protected int|string|array $columnSpan = 'full';

    public ?string $selectedDate = null;

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

        if (!$appointment) {
            \Filament\Notifications\Notification::make()
                ->title(__('booking::reception.messages.appointment_not_found'))
                ->danger()
                ->send();
            return;
        }

        if (!in_array($appointment->status, [
            \Modules\Booking\Models\Appointment::STATUS_SCHEDULED,
            \Modules\Booking\Models\Appointment::STATUS_CONFIRMED,
        ])) {
            \Filament\Notifications\Notification::make()
                ->title(__('booking::reception.messages.cannot_check_in'))
                ->danger()
                ->send();
            return;
        }

        if ($appointment->checkIn()) {
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
            \Modules\Booking\Models\Appointment::STATUS_SCHEDULED,
            \Modules\Booking\Models\Appointment::STATUS_CONFIRMED,
        ]);
    }
}
