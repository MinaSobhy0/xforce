<?php

namespace Modules\Marketing\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Services\WhatsAppService;
use Modules\Marketing\Models\NotificationLog;
use Modules\Marketing\Models\MessageTemplate;
use Modules\Booking\Models\Appointment;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        protected WhatsAppService $whatsAppService
    ) {}

    /**
     * Verify webhook from Meta.
     */
    public function verify(Request $request): mixed
    {
        $verifyToken = config('marketing.whatsapp.webhook_verify_token');

        if ($request->get('hub_mode') === 'subscribe' &&
            $request->get('hub_verify_token') === $verifyToken) {
            return response($request->get('hub_challenge'));
        }

        return response('Forbidden', 403);
    }

    /**
     * Handle incoming webhook from WhatsApp.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::debug('WhatsApp webhook received', ['payload' => $payload]);

        // Handle status updates
        $status = $this->whatsAppService->parseWebhookStatus($payload);
        if ($status) {
            $this->handleStatusUpdate($status);
        }

        // Handle button callbacks
        $buttonCallback = $this->whatsAppService->parseButtonCallback($payload);
        if ($buttonCallback) {
            $this->handleButtonCallback($buttonCallback);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle message status updates (sent, delivered, read, failed).
     */
    protected function handleStatusUpdate(array $status): void
    {
        if (!$status['message_id']) {
            return;
        }

        $log = NotificationLog::where('provider_message_id', $status['message_id'])->first();

        if (!$log) {
            return;
        }

        $statusMap = [
            'sent' => 'sent',
            'delivered' => 'delivered',
            'read' => 'read',
            'failed' => 'failed',
        ];

        $newStatus = $statusMap[$status['status']] ?? null;

        if ($newStatus) {
            $log->update([
                'status' => $newStatus,
                "{$newStatus}_at" => now(),
                'error' => $status['error'] ?? null,
            ]);
        }
    }

    /**
     * Handle button click callbacks.
     */
    protected function handleButtonCallback(array $callback): void
    {
        Log::info('WhatsApp button callback received', $callback);

        $action = $callback['action'] ?? null;
        $referenceId = $callback['reference_id'] ?? null;
        $fromPhone = $callback['from'] ?? null;

        if (!$action || !$referenceId) {
            Log::warning('Invalid button callback - missing action or reference', $callback);
            return;
        }

        // Determine tenant from phone number or reference
        // This requires tenant context - you may need to store tenant_id in button payload

        switch ($action) {
            case MessageTemplate::BUTTON_ACTION_CONFIRM:
                $this->handleAppointmentConfirmation($referenceId, $fromPhone);
                break;

            case MessageTemplate::BUTTON_ACTION_RESCHEDULE:
                $this->handleAppointmentReschedule($referenceId, $fromPhone);
                break;

            case MessageTemplate::BUTTON_ACTION_CANCEL:
                $this->handleAppointmentCancellation($referenceId, $fromPhone);
                break;

            default:
                Log::info('Custom button action received', [
                    'action' => $action,
                    'reference_id' => $referenceId,
                ]);
        }
    }

    /**
     * Handle appointment confirmation from button click.
     */
    protected function handleAppointmentConfirmation(string $appointmentId, ?string $phone): void
    {
        // Find appointment across all tenants (webhook doesn't have tenant context)
        // In production, you'd want to include tenant_id in the button payload
        $appointment = Appointment::find($appointmentId);

        if (!$appointment) {
            Log::warning('Appointment not found for confirmation', ['id' => $appointmentId]);
            return;
        }

        // Only confirm if pending
        if ($appointment->status === 'pending') {
            $appointment->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'confirmed_via' => 'whatsapp_button',
            ]);

            Log::info('Appointment confirmed via WhatsApp button', [
                'appointment_id' => $appointmentId,
                'phone' => $phone,
            ]);

            // Send confirmation acknowledgment
            if ($phone) {
                $this->whatsAppService->sendTextMessage(
                    $phone,
                    __('marketing::marketing.button_responses.appointment_confirmed')
                );
            }
        }
    }

    /**
     * Handle appointment reschedule request from button click.
     */
    protected function handleAppointmentReschedule(string $appointmentId, ?string $phone): void
    {
        $appointment = Appointment::find($appointmentId);

        if (!$appointment) {
            Log::warning('Appointment not found for reschedule', ['id' => $appointmentId]);
            return;
        }

        // Mark as reschedule requested
        $appointment->update([
            'reschedule_requested' => true,
            'reschedule_requested_at' => now(),
            'reschedule_requested_via' => 'whatsapp_button',
        ]);

        Log::info('Appointment reschedule requested via WhatsApp button', [
            'appointment_id' => $appointmentId,
            'phone' => $phone,
        ]);

        // Send acknowledgment with instructions
        if ($phone) {
            $this->whatsAppService->sendTextMessage(
                $phone,
                __('marketing::marketing.button_responses.reschedule_requested')
            );
        }
    }

    /**
     * Handle appointment cancellation from button click.
     */
    protected function handleAppointmentCancellation(string $appointmentId, ?string $phone): void
    {
        $appointment = Appointment::find($appointmentId);

        if (!$appointment) {
            Log::warning('Appointment not found for cancellation', ['id' => $appointmentId]);
            return;
        }

        // Only cancel if not already completed
        if (!in_array($appointment->status, ['completed', 'cancelled'])) {
            $appointment->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => 'Cancelled by patient via WhatsApp',
                'cancelled_via' => 'whatsapp_button',
            ]);

            Log::info('Appointment cancelled via WhatsApp button', [
                'appointment_id' => $appointmentId,
                'phone' => $phone,
            ]);

            // Send cancellation confirmation
            if ($phone) {
                $this->whatsAppService->sendTextMessage(
                    $phone,
                    __('marketing::marketing.button_responses.appointment_cancelled')
                );
            }
        }
    }
}
