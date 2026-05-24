<?php

namespace Modules\Marketing\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\Tenant;
use Modules\Marketing\Models\NotificationLog;

/**
 * Sends WhatsApp messages on behalf of a tenant.
 *
 * Credential resolution lives in WhatsAppCredentialsResolver:
 *   1. Tenant's own Meta WABA (per-tenant Embedded Signup), or
 *   2. Platform-global Meta config (fallback for un-onboarded tenants).
 *
 * Platform-global Twilio WhatsApp is still supported as a final fallback
 * when neither Meta path is configured — preserves today's behavior for
 * deployments that picked Twilio as their WhatsApp provider before this
 * refactor.
 *
 * No instance state for credentials: every public send method resolves
 * per-call from current_tenant(), so a single long-lived service can
 * serve many tenants in the same process (cron, queue worker, etc.).
 */
class WhatsAppService
{
    public function __construct(
        protected WhatsAppCredentialsResolver $resolver,
    ) {}

    /**
     * Whether WhatsApp can send for the given tenant (or the platform
     * fallback when null). The kill-switch `whatsapp_enabled` platform
     * flag still gates everything globally.
     */
    public function isEnabledFor(?Tenant $tenant = null): bool
    {
        if (! (bool) PlatformSetting::get('whatsapp_enabled', false)) {
            return false;
        }

        return $this->resolver->resolveFor($tenant ?? current_tenant()) !== null
            || $this->hasPlatformTwilio();
    }

    /**
     * Back-compat alias — callers still typing the old name keep working.
     */
    public function isEnabled(): bool
    {
        return $this->isEnabledFor(null);
    }

    public function getProvider(): string
    {
        $meta = $this->resolver->resolveFor(current_tenant());
        if ($meta) {
            return 'meta';
        }

        return PlatformSetting::get('whatsapp_provider', 'meta');
    }

    /**
     * Send a text message. Routes through Meta (tenant or platform) when
     * Meta creds resolve, else platform Twilio if configured, else error.
     */
    public function sendTextMessage(string $to, string $message): array
    {
        if (! (bool) PlatformSetting::get('whatsapp_enabled', false)) {
            return ['success' => false, 'error' => 'WhatsApp is not configured'];
        }

        $meta = $this->resolver->resolveFor(current_tenant());
        if ($meta) {
            return $this->sendViaMeta($meta, $to, $message);
        }

        if ($this->hasPlatformTwilio()) {
            return $this->sendViaPlatformTwilio($to, $message);
        }

        return ['success' => false, 'error' => 'WhatsApp is not configured'];
    }

    /**
     * Send a template message (required for business-initiated conversations).
     * Twilio path: degrades to text using the body component (Twilio Content
     * templates require pre-registration which we don't track here).
     */
    public function sendTemplateMessage(
        string $to,
        string $templateName,
        string $languageCode = 'en',
        array $components = []
    ): array {
        if (! (bool) PlatformSetting::get('whatsapp_enabled', false)) {
            return ['success' => false, 'error' => 'WhatsApp is not configured'];
        }

        $meta = $this->resolver->resolveFor(current_tenant());

        if (! $meta) {
            if (! $this->hasPlatformTwilio()) {
                return ['success' => false, 'error' => 'WhatsApp is not configured'];
            }

            $bodyText = $templateName;
            foreach ($components as $component) {
                if (($component['type'] ?? '') === 'body' && ! empty($component['parameters'])) {
                    $texts = array_map(fn ($p) => $p['text'] ?? '', $component['parameters']);
                    $bodyText = implode(' ', array_filter($texts));
                    break;
                }
            }

            return $this->sendViaPlatformTwilio($to, $bodyText);
        }

        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $this->formatPhoneNumber($to),
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $languageCode],
                ],
            ];

            if (! empty($components)) {
                $payload['template']['components'] = $components;
            }

            return $this->postToMeta($meta, $payload, [
                'context' => 'template',
                'template' => $templateName,
                'to' => $to,
            ]);
        } catch (\Exception $e) {
            Log::error('WhatsApp template send failed', [
                'to' => $to,
                'template' => $templateName,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build template components for appointment reminder.
     */
    public function buildAppointmentReminderComponents(array $variables): array
    {
        return [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $variables['patient_name'] ?? ''],
                    ['type' => 'text', 'text' => $variables['appointment_date'] ?? ''],
                    ['type' => 'text', 'text' => $variables['appointment_time'] ?? ''],
                    ['type' => 'text', 'text' => $variables['treatment_name'] ?? ''],
                    ['type' => 'text', 'text' => $variables['clinic_name'] ?? ''],
                ],
            ],
        ];
    }

    /**
     * Send an interactive message with buttons (Meta only — Twilio falls back to text).
     */
    public function sendInteractiveMessage(string $to, array $interactive): array
    {
        if (! (bool) PlatformSetting::get('whatsapp_enabled', false)) {
            return ['success' => false, 'error' => 'WhatsApp is not configured'];
        }

        $meta = $this->resolver->resolveFor(current_tenant());

        if (! $meta) {
            if (! $this->hasPlatformTwilio()) {
                return ['success' => false, 'error' => 'WhatsApp is not configured'];
            }

            return $this->sendViaPlatformTwilio($to, $interactive['body']['text'] ?? '');
        }

        try {
            return $this->postToMeta($meta, [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $this->formatPhoneNumber($to),
                'type' => 'interactive',
                'interactive' => $interactive,
            ], [
                'context' => 'interactive',
                'to' => $to,
            ]);
        } catch (\Exception $e) {
            Log::error('WhatsApp interactive message send failed', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a message using a template with buttons (interactive message).
     */
    public function sendMessageWithButtons(
        string $to,
        \Modules\Marketing\Models\MessageTemplate $template,
        array $variables,
        ?string $locale = null
    ): array {
        if (! $template->hasButtons()) {
            $rendered = $template->render($variables, $locale);

            return $this->sendTextMessage($to, $rendered['content']);
        }

        $meta = $this->resolver->resolveFor(current_tenant());
        if (! $meta) {
            $rendered = $template->render($variables, $locale);
            $messageWithLinks = $this->appendButtonLinks($rendered['content'], $template, $variables, $locale);

            return $this->sendTextMessage($to, $messageWithLinks);
        }

        $interactive = $template->buildInteractivePayload($variables, $locale);

        return $this->sendInteractiveMessage($to, $interactive);
    }

    /**
     * Append button action links to message text (for Twilio fallback).
     */
    protected function appendButtonLinks(
        string $content,
        \Modules\Marketing\Models\MessageTemplate $template,
        array $variables,
        ?string $locale = null
    ): string {
        $buttons = $template->getButtons();
        if (empty($buttons)) {
            return $content;
        }

        $appointmentId = $variables['appointment_id'] ?? null;
        if (! $appointmentId) {
            return $content;
        }

        $links = [];
        foreach ($buttons as $button) {
            $action = $button['action'] ?? '';
            $label = $button['label'] ?? '';
            $url = $this->generateActionUrl($action, $appointmentId);
            if ($url) {
                $links[] = "🔗 {$label}: {$url}";
            }
        }

        if (! empty($links)) {
            $content .= "\n\n".implode("\n", $links);
        }

        return $content;
    }

    /**
     * Generate a short URL for appointment action.
     */
    protected function generateActionUrl(string $action, string $appointmentId): ?string
    {
        $validActions = ['confirm_appointment', 'reschedule_appointment', 'cancel_appointment'];
        if (! in_array($action, $validActions)) {
            return null;
        }

        $routeAction = match ($action) {
            'confirm_appointment' => 'confirm',
            'reschedule_appointment' => 'reschedule',
            'cancel_appointment' => 'cancel',
            default => null,
        };

        if (! $routeAction) {
            return null;
        }

        $appointment = \Modules\Booking\Models\Appointment::find($appointmentId);
        $tenantId = $appointment?->tenant_id;

        $baseUrl = request()->getSchemeAndHttpHost();
        $signedUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'appointment.action',
            now()->addHours(2),
            ['appointment' => $appointmentId, 'action' => $routeAction]
        );
        $signedUrl = preg_replace('/^https?:\/\/[^\/]+/', $baseUrl, $signedUrl);

        $shortLink = \Modules\Marketing\Models\ShortLink::createFor(
            $signedUrl,
            $tenantId,
            $routeAction,
            (int) $appointmentId,
            1
        );

        return $shortLink->short_url;
    }

    /**
     * Parse incoming button click from webhook.
     */
    public function parseButtonCallback(array $payload): ?array
    {
        $messages = $payload['entry'][0]['changes'][0]['value']['messages'] ?? [];
        if (empty($messages)) {
            return null;
        }

        $message = $messages[0];
        if (($message['type'] ?? '') !== 'interactive') {
            return null;
        }

        $interactive = $message['interactive'] ?? [];
        if (($interactive['type'] ?? '') !== 'button_reply') {
            return null;
        }

        $buttonReply = $interactive['button_reply'] ?? [];
        $buttonId = $buttonReply['id'] ?? '';
        $parsed = \Modules\Marketing\Models\MessageTemplate::parseButtonCallback($buttonId);

        return [
            'from' => $message['from'] ?? null,
            'message_id' => $message['id'] ?? null,
            'button_id' => $buttonId,
            'button_title' => $buttonReply['title'] ?? null,
            'action' => $parsed['action'],
            'reference_id' => $parsed['reference_id'],
            'template_code' => $parsed['template_code'],
            'timestamp' => $message['timestamp'] ?? null,
        ];
    }

    /**
     * Get message status from webhook payload.
     */
    public function parseWebhookStatus(array $payload): ?array
    {
        $statuses = $payload['entry'][0]['changes'][0]['value']['statuses'] ?? [];
        if (empty($statuses)) {
            return null;
        }

        $status = $statuses[0];

        return [
            'message_id' => $status['id'] ?? null,
            'status' => $status['status'] ?? null,
            'timestamp' => $status['timestamp'] ?? null,
            'recipient' => $status['recipient_id'] ?? null,
            'error' => $status['errors'][0]['message'] ?? null,
        ];
    }

    /**
     * Send a Meta-formatted payload using the resolved credentials.
     */
    protected function postToMeta(array $creds, array $payload, array $logContext = []): array
    {
        $response = Http::withToken($creds['access_token'])
            ->post($this->metaUrl($creds, '/messages'), $payload);

        if ($response->successful()) {
            $data = $response->json();
            $messageId = $data['messages'][0]['id'] ?? null;

            $this->mirrorOutboundToInbox(
                creds: $creds,
                payload: $payload,
                wamid: $messageId,
                logContext: $logContext,
            );

            return [
                'success' => true,
                'message_id' => $messageId,
                'response' => $data,
            ];
        }

        $error = $response->json();
        $errorCode = $error['error']['code'] ?? null;
        $errorMessage = $error['error']['message'] ?? 'Unknown error';

        // Meta error 190 = access token revoked/expired. Mark the tenant's
        // connection so the UI prompts a reconnect on next page load.
        if ($errorCode === 190 && ($creds['source'] ?? null) === 'tenant') {
            $this->markTokenInvalid(current_tenant(), $errorMessage);
        }

        Log::warning('whatsapp.meta_send_failed', array_merge($logContext, [
            'source' => $creds['source'] ?? null,
            'error_code' => $errorCode,
            'error' => $errorMessage,
        ]));

        return [
            'success' => false,
            'error' => $errorMessage,
            'error_code' => $errorCode,
            'response' => $error,
        ];
    }

    /**
     * Mark the tenant's Meta connection as needing reconnect — the next
     * resolveFor() will return null and fall back to platform creds.
     */
    protected function markTokenInvalid(?Tenant $tenant, string $reason): void
    {
        if (! $tenant) {
            return;
        }

        $meta = $tenant->getSetting('messaging.whatsapp.meta', []) ?: [];
        $meta['status'] = 'disconnected';
        $meta['last_error'] = "Token rejected by Meta: {$reason}";
        $tenant->setSetting('messaging.whatsapp.meta', $meta);

        $this->resolver->flush($tenant);
    }

    /**
     * Send via the platform-global Twilio WhatsApp number (legacy fallback).
     */
    protected function sendViaPlatformTwilio(string $to, string $message): array
    {
        $accountSid = (string) PlatformSetting::get('whatsapp_twilio_account_sid', '');
        $authToken = (string) PlatformSetting::get('whatsapp_twilio_auth_token', '');
        $fromNumber = (string) PlatformSetting::get('whatsapp_twilio_from_number', '');

        try {
            $formattedTo = $this->formatPhoneNumber($to);
            $formattedFrom = $this->formatPhoneNumber($fromNumber);

            $response = Http::withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'From' => "whatsapp:{$formattedFrom}",
                    'To' => "whatsapp:{$formattedTo}",
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'message_id' => $data['sid'] ?? null,
                    'response' => $data,
                ];
            }

            $error = $response->json();

            return [
                'success' => false,
                'error' => $error['message'] ?? 'Unknown error',
                'response' => $error,
            ];
        } catch (\Exception $e) {
            Log::error('Twilio WhatsApp send failed', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Meta-only convenience for callers that don't need template/interactive.
     * Kept protected — public surface is sendTextMessage() which fronts both
     * Meta and platform Twilio routing.
     */
    protected function sendViaMeta(array $creds, string $to, string $message): array
    {
        try {
            return $this->postToMeta($creds, [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $this->formatPhoneNumber($to),
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message,
                ],
            ], ['context' => 'text', 'to' => $to]);
        } catch (\Exception $e) {
            Log::error('WhatsApp send failed', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function hasPlatformTwilio(): bool
    {
        return PlatformSetting::get('whatsapp_provider', 'meta') === 'twilio'
            && (string) PlatformSetting::get('whatsapp_twilio_account_sid', '') !== ''
            && (string) PlatformSetting::get('whatsapp_twilio_auth_token', '') !== ''
            && (string) PlatformSetting::get('whatsapp_twilio_from_number', '') !== '';
    }

    /**
     * Format phone number for WhatsApp API. Expects international format
     * like +201234567890; tolerates local Egyptian numbers starting with 0.
     */
    protected function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }

        if (str_starts_with($phone, '0')) {
            $phone = '20'.substr($phone, 1);
        }

        return '+'.$phone;
    }

    /**
     * Meta enforces a 24-hour customer-service window: free-form text
     * replies are only accepted within 24 hours of the recipient's last
     * inbound message. Outside that window, only approved templates work.
     */
    public function canSendFreeForm(\Modules\Marketing\Models\WhatsAppConversation $conversation): bool
    {
        return $conversation->last_inbound_at !== null
            && $conversation->last_inbound_at->gt(now()->subHours(24));
    }

    /**
     * Build a Meta Graph API URL for the resolved phone_number_id.
     */
    protected function metaUrl(array $creds, string $endpoint = ''): string
    {
        $version = $creds['api_version'] ?? 'v18.0';
        $phoneId = $creds['phone_number_id'];

        return "https://graph.facebook.com/{$version}/{$phoneId}{$endpoint}";
    }

    /**
     * After a successful Meta send, write a WhatsAppMessage row so the
     * inbox thread shows both sides of the conversation. Find-or-create
     * the conversation by (remote phone, our phone_number_id).
     *
     * Best-effort — if the inbox write fails the send already succeeded,
     * we just log and move on. Don't propagate the exception.
     */
    protected function mirrorOutboundToInbox(array $creds, array $payload, ?string $wamid, array $logContext): void
    {
        $tenant = current_tenant();
        if (! $tenant) {
            return; // platform-level send, no per-tenant inbox
        }

        $remotePhone = $payload['to'] ?? ($logContext['to'] ?? null);
        if (! $remotePhone) {
            return;
        }
        $remotePhone = $this->formatPhoneNumber((string) $remotePhone);

        $phoneNumberId = (string) ($creds['phone_number_id'] ?? '');
        if ($phoneNumberId === '') {
            return;
        }

        try {
            $conversation = \Modules\Marketing\Models\WhatsAppConversation::firstOrCreate(
                [
                    'remote_phone_e164' => $remotePhone,
                    'phone_number_id' => $phoneNumberId,
                ],
                [
                    'tenant_id' => $tenant->id,
                ]
            );

            $type = $payload['type'] ?? 'text';
            $body = match ($type) {
                'text' => $payload['text']['body'] ?? null,
                'template' => '[template] '.($payload['template']['name'] ?? ''),
                'image', 'document', 'video', 'audio' => $payload[$type]['caption'] ?? null,
                'interactive' => $payload['interactive']['body']['text'] ?? null,
                default => null,
            };

            $mediaId = isset($payload[$type]['id']) ? (string) $payload[$type]['id'] : null;
            $mediaFilename = $payload[$type]['filename'] ?? null;

            \Modules\Marketing\Models\WhatsAppMessage::create([
                'tenant_id' => $tenant->id,
                'conversation_id' => $conversation->id,
                'wamid' => $wamid,
                'direction' => \Modules\Marketing\Models\WhatsAppMessage::DIRECTION_OUTBOUND,
                'type' => $type,
                'body' => $body,
                'media_id' => $mediaId,
                'media_filename' => $mediaFilename,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $preview = $body ? mb_substr((string) $body, 0, 280) : match ($type) {
                'template' => '[template] '.($payload['template']['name'] ?? ''),
                'image' => '📷 Image',
                'document' => '📄 Document',
                'video' => '🎬 Video',
                'audio' => '🎤 Audio',
                default => ucfirst((string) $type),
            };

            $conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => $preview,
                'last_message_direction' => 'outbound',
            ]);
        } catch (\Throwable $e) {
            Log::warning('whatsapp.outbound.mirror_failed', [
                'wamid' => $wamid,
                'to' => $remotePhone,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
