<?php

namespace Modules\MobileApi\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Booking\Models\Appointment;
use Modules\MobileApi\Services\PushNotificationService;

class SendAppointmentReminderPush implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * The queue name for this job.
     */
    public string $queue = 'notifications';

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(
        public Appointment $appointment
    ) {}

    /**
     * Send appointment reminder notification.
     */
    public function handle(): void
    {
        // Ensure we have the required relationships
        $this->appointment->loadMissing(['practitioner', 'patient']);

        $practitioner = $this->appointment->practitioner;
        $patient = $this->appointment->patient;

        if (! $practitioner) {
            Log::warning('Cannot send push notification: practitioner not found', [
                'appointment_id' => $this->appointment->id,
            ]);

            return;
        }

        $pushService = app(PushNotificationService::class);

        $patientName = $patient?->full_name ?? __('mobile_api::notifications.appointment.unknown_patient');
        $time = $this->appointment->start_time?->format('h:i A') ?? $this->appointment->time ?? '';

        $pushService->sendAppointmentReminderNotification(
            user: $practitioner,
            appointment: $this->appointment,
            patientName: $patientName,
            time: $time
        );
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send appointment reminder push notification', [
            'appointment_id' => $this->appointment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
