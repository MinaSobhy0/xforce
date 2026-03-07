<?php

namespace Modules\Marketing\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Booking\Events\AppointmentConfirmed;
use Modules\Marketing\Models\AutomationRule;
use Modules\Marketing\Models\MessageTemplate;
use Modules\Marketing\Services\NotificationService;

class SendAppointmentReminder implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create the event listener.
     */
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(AppointmentConfirmed $event): void
    {
        $appointment = $event->appointment;
        $patient = $appointment->patient;

        if (!$patient) {
            Log::warning('AppointmentConfirmed event: No patient found', [
                'appointment_id' => $appointment->id,
            ]);
            return;
        }

        // Check if patient has consented to messaging
        if (!$this->hasMessagingConsent($patient)) {
            Log::info('AppointmentConfirmed: Patient has not consented to messaging', [
                'appointment_id' => $appointment->id,
                'patient_id' => $patient->id,
            ]);
            return;
        }

        // Find active automation rules for appointment confirmation
        $rules = AutomationRule::query()
            ->where('tenant_id', $appointment->tenant_id)
            ->where('trigger_type', AutomationRule::TRIGGER_APPOINTMENT_CONFIRMED)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->with('template')
            ->get();

        if ($rules->isEmpty()) {
            Log::info('AppointmentConfirmed: No active automation rules found', [
                'appointment_id' => $appointment->id,
                'tenant_id' => $appointment->tenant_id,
            ]);
            return;
        }

        // Build context for condition matching
        $context = [
            'service_id' => $appointment->service_id,
            'practitioner_id' => $appointment->practitioner_id,
            'branch_id' => $appointment->branch_id,
            'patient_gender' => $patient->gender,
            'patient_status' => $patient->status,
        ];

        // Process each matching rule
        foreach ($rules as $rule) {
            if (!$rule->template || !$rule->template->is_active) {
                continue;
            }

            // Check conditions
            if (!$rule->matchesConditions($context)) {
                continue;
            }

            // Build variables for the template
            $variables = $this->buildTemplateVariables($appointment);

            // Send using the notification service
            try {
                $log = $this->notificationService->sendTemplate(
                    $rule->template,
                    $patient,
                    $variables,
                    'appointment',
                    (string) $appointment->id
                );

                Log::info('AppointmentConfirmed: Notification sent', [
                    'appointment_id' => $appointment->id,
                    'rule_id' => $rule->id,
                    'template_id' => $rule->template_id,
                    'notification_log_id' => $log->id,
                    'status' => $log->status,
                ]);

                // Only send one notification per trigger (first matching rule)
                break;
            } catch (\Exception $e) {
                Log::error('AppointmentConfirmed: Failed to send notification', [
                    'appointment_id' => $appointment->id,
                    'rule_id' => $rule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Check if patient has consented to receive messages.
     */
    protected function hasMessagingConsent($patient): bool
    {
        // Check general marketing consent or specific channel consent
        return $patient->marketing_consent
            || $patient->sms_consent
            || $patient->whatsapp_consent
            || $patient->email_consent;
    }

    /**
     * Build template variables from appointment data.
     */
    protected function buildTemplateVariables($appointment): array
    {
        return [
            'appointment_date' => $appointment->date?->format('d/m/Y'),
            'appointment_time' => $appointment->start_time?->format('h:i A'),
            'appointment_datetime' => $appointment->start_date_time?->format('d/m/Y h:i A'),
            'service_name' => $appointment->service?->getTranslation('name', app()->getLocale()) ?? '',
            'practitioner_name' => $appointment->practitioner?->name ?? '',
            'branch_name' => $appointment->branch?->name ?? '',
            'branch_address' => $appointment->branch?->address ?? '',
            'branch_phone' => $appointment->branch?->phone ?? '',
            'appointment_id' => $appointment->id,
            'appointment_code' => $appointment->code,
            'confirmation_code' => $appointment->code,
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(AppointmentConfirmed $event, \Throwable $exception): void
    {
        Log::error('SendAppointmentReminder job failed', [
            'appointment_id' => $event->appointment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
