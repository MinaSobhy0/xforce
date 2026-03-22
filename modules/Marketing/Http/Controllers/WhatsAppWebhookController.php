<?php

namespace Modules\Marketing\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Modules\Marketing\Services\WhatsAppService;
use Modules\Marketing\Models\NotificationLog;
use Modules\Marketing\Models\MessageTemplate;
use Modules\Booking\Models\Appointment;
use Modules\Core\Models\Tenant;

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

        // SECURITY: Use constant-time comparison to prevent timing attacks
        if ($request->get('hub_mode') === 'subscribe' &&
            hash_equals($verifyToken ?? '', $request->get('hub_verify_token') ?? '')) {
            return response($request->get('hub_challenge'));
        }

        Log::warning('WhatsApp webhook verification failed', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Handle incoming webhook from WhatsApp.
     */
    public function handle(Request $request): JsonResponse
    {
        // SECURITY: Verify webhook signature from Meta
        if (!$this->verifyWebhookSignature($request)) {
            Log::warning('WhatsApp webhook signature verification failed', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();

        // Don't log full payload in production to avoid exposing sensitive data
        Log::debug('WhatsApp webhook received', [
            'has_statuses' => isset($payload['entry'][0]['changes'][0]['value']['statuses']),
            'has_messages' => isset($payload['entry'][0]['changes'][0]['value']['messages']),
        ]);

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
     * Verify the webhook signature from Meta.
     * Meta signs webhooks with HMAC-SHA256 using your app secret.
     */
    protected function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (!$signature) {
            return false;
        }

        $appSecret = config('marketing.whatsapp.app_secret');

        if (!$appSecret) {
            // If app secret is not configured, log and reject
            Log::error('WhatsApp app secret not configured - cannot verify webhook signatures');
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);

        // Use constant-time comparison to prevent timing attacks
        return hash_equals($expectedSignature, $signature);
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
     * Find appointment with proper tenant context.
     * SECURITY: Never query appointments globally - always with tenant scoping.
     *
     * The appointment ID should be in format: {tenant_id}:{appointment_id}
     * to ensure we can properly scope the query.
     */
    protected function findAppointmentWithTenant(string $referenceId): ?Appointment
    {
        // Parse the reference ID - expected format: {tenant_id}:{appointment_id}
        // This ensures webhook callbacks include tenant context
        if (str_contains($referenceId, ':')) {
            [$tenantId, $appointmentId] = explode(':', $referenceId, 2);
        } else {
            // Legacy format - try to find via notification log which has tenant context
            $notificationLog = NotificationLog::where('reference_id', $referenceId)
                ->orWhere('reference_id', 'appointment:' . $referenceId)
                ->first();

            if (!$notificationLog || !$notificationLog->tenant_id) {
                Log::warning('Cannot determine tenant for appointment callback', [
                    'reference_id' => $referenceId,
                ]);
                return null;
            }

            $tenantId = $notificationLog->tenant_id;
            $appointmentId = $referenceId;
        }

        // Validate appointment ID is numeric to prevent injection
        if (!is_numeric($appointmentId)) {
            Log::warning('Invalid appointment ID format', ['appointment_id' => $appointmentId]);
            return null;
        }

        // Find the tenant
        $tenant = Tenant::find($tenantId);
        if (!$tenant || !$tenant->database_name) {
            Log::warning('Tenant not found for appointment callback', ['tenant_id' => $tenantId]);
            return null;
        }

        // Switch to tenant schema
        $this->switchToTenantSchema($tenant);

        // Now find the appointment within the tenant's schema
        return Appointment::find($appointmentId);
    }

    /**
     * Switch to tenant's database schema.
     */
    protected function switchToTenantSchema(Tenant $tenant): void
    {
        $schemaName = $tenant->database_name;

        Config::set('database.connections.pgsql.search_path', $schemaName);
        DB::purge('pgsql');
        DB::reconnect('pgsql');
        DB::statement("SET search_path TO \"{$schemaName}\"");
    }

    /**
     * Handle appointment confirmation from button click.
     */
    protected function handleAppointmentConfirmation(string $appointmentId, ?string $phone): void
    {
        // SECURITY: Find appointment with proper tenant context
        $appointment = $this->findAppointmentWithTenant($appointmentId);

        if (!$appointment) {
            Log::warning('Appointment not found for confirmation', ['id' => $appointmentId]);
            return;
        }

        // Validate phone matches appointment patient (optional additional security)
        if ($phone && $appointment->patient) {
            $patientPhone = preg_replace('/[^0-9]/', '', $appointment->patient->phone ?? '');
            $callbackPhone = preg_replace('/[^0-9]/', '', $phone);
            // Check if last 9 digits match (to handle country code variations)
            if (strlen($patientPhone) >= 9 && strlen($callbackPhone) >= 9) {
                if (substr($patientPhone, -9) !== substr($callbackPhone, -9)) {
                    Log::warning('Phone mismatch in appointment confirmation', [
                        'appointment_id' => $appointmentId,
                        'callback_phone' => substr($callbackPhone, -4), // Log only last 4 digits
                    ]);
                    // Continue anyway - phone verification is optional
                }
            }
        }

        // Only confirm if pending
        if ($appointment->status === 'pending') {
            $appointment->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'confirmed_via' => 'whatsapp_button',
            ]);

            Log::info('Appointment confirmed via WhatsApp button', [
                'appointment_id' => $appointment->id,
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
        // SECURITY: Find appointment with proper tenant context
        $appointment = $this->findAppointmentWithTenant($appointmentId);

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
            'appointment_id' => $appointment->id,
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
        // SECURITY: Find appointment with proper tenant context
        $appointment = $this->findAppointmentWithTenant($appointmentId);

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
                'appointment_id' => $appointment->id,
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
