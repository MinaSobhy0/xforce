<?php

namespace Modules\MobileApi\Observers;

use Illuminate\Support\Facades\Log;
use Modules\Booking\Models\Appointment;
use Modules\MobileApi\Services\PushNotificationService;

class AppointmentObserver
{
    /**
     * Handle the Appointment "created" event.
     *
     * Send push notification to practitioner when assigned to a new appointment.
     */
    public function created(Appointment $appointment): void
    {
        // Only notify if a practitioner is assigned
        if (! $appointment->practitioner_id) {
            return;
        }

        $this->notifyPractitioner($appointment);
    }

    /**
     * Handle the Appointment "updated" event.
     *
     * Send push notification when practitioner is newly assigned to an existing appointment.
     */
    public function updated(Appointment $appointment): void
    {
        // Check if practitioner was just assigned (changed from null/different to a value)
        if (! $appointment->isDirty('practitioner_id')) {
            return;
        }

        $originalPractitionerId = $appointment->getOriginal('practitioner_id');
        $newPractitionerId = $appointment->practitioner_id;

        // Only notify if a new practitioner is being assigned (not removed or unchanged)
        if (! $newPractitionerId) {
            return;
        }

        // Don't notify if it's the same practitioner
        if ($originalPractitionerId === $newPractitionerId) {
            return;
        }

        $this->notifyPractitioner($appointment);
    }

    /**
     * Send push notification to the assigned practitioner.
     */
    protected function notifyPractitioner(Appointment $appointment): void
    {
        try {
            // Load practitioner if not already loaded
            $practitioner = $appointment->practitioner;

            if (! $practitioner) {
                Log::debug('AppointmentObserver: No practitioner found', [
                    'appointment_id' => $appointment->id,
                ]);

                return;
            }

            // Get patient name
            $patientName = $appointment->patient?->full_name
                ?? __('mobile_api::notifications.appointment.unknown_patient');

            // Format date and time
            $date = $appointment->date?->format('M d, Y') ?? '';
            $time = $appointment->start_time?->format('h:i A') ?? '';

            // Send push notification
            app(PushNotificationService::class)->sendAppointmentAssignedNotification(
                user: $practitioner,
                appointment: $appointment,
                patientName: $patientName,
                date: $date,
                time: $time
            );

            Log::debug('AppointmentObserver: Notification sent', [
                'appointment_id' => $appointment->id,
                'practitioner_id' => $practitioner->id,
            ]);
        } catch (\Exception $e) {
            Log::error('AppointmentObserver: Failed to send notification', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
