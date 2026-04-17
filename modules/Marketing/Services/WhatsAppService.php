<?php

namespace Modules\Marketing\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\NotificationLog;

class WhatsAppService
{
    protected string $apiVersion;
    protected ?string $phoneNumber;
    protected ?string $accessToken;
    protected ?string $accountSid;
    protected ?string $provider;
    protected bool $enabled;

    public function __construct()
    {
        $this->loadSettings();
    }

    /**
     * Load settings from PlatformSetting (SuperAdmin configuration).
     */
    protected function loadSettings(): void
    {
        $this->enabled = (bool) PlatformSetting::get('whatsapp_enabled', false);
        $this->provider = PlatformSetting::get('whatsapp_provider', 'meta');
        $this->apiVersion = config('marketing.whatsapp.api_version', 'v18.0');

        // For Meta (Official WhatsApp Business API)
        if ($this->provider === 'meta') {
            $this->phoneNumber = PlatformSetting::get('whatsapp_meta_phone_number_id', '');
            $this->accessToken = PlatformSetting::get('whatsapp_meta_access_token', '');
            $this->accountSid = PlatformSetting::get('whatsapp_meta_business_id', '');
        }
        // For Twilio
        elseif ($this->provider === 'twilio') {
            $this->phoneNumber = PlatformSetting::get('whatsapp_twilio_from_number', '');
            $this->accountSid = PlatformSetting::get('whatsapp_twilio_account_sid', '');
            $this->accessToken = PlatformSetting::get('whatsapp_twilio_auth_token', '');
        }
        // Fallback to env config if not set in platform settings
        else {
            $this->phoneNumber = config('marketing.whatsapp.phone_number_id');
            $this->accessToken = config('marketing.whatsapp.access_token');
            $this->accountSid = null;
        }
    }

    /**
     * Check if WhatsApp is enabled and configured.
     */
    public function isEnabled(): bool
    {
        if (!$this->enabled || !$this->phoneNumber || !$this->accessToken) {
            return false;
        }

        // Twilio also requires account SID
        if ($this->provider === 'twilio' && !$this->accountSid) {
            return false;
        }

        return true;
    }

    /**
     * Get the current provider.
     */
    public function getProvider(): string
    {
        return $this->provider ?? 'meta';
    }

    /**
     * Send a text message.
     */
    public function sendTextMessage(string $to, string $message): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'WhatsApp is not configured',
            ];
        }

        // Route to appropriate provider
        if ($this->provider === 'twilio') {
            return $this->sendViaTwilio($to, $message);
        }

        return $this->sendViaMeta($to, $message);
    }

    /**
     * Send message via Twilio WhatsApp API.
     */
    protected function sendViaTwilio(string $to, string $message): array
    {
        try {
            $formattedTo = $this->formatPhoneNumber($to);
            $formattedFrom = $this->formatPhoneNumber($this->phoneNumber);

            $response = Http::withBasicAuth($this->accountSid, $this->accessToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", [
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

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send message via Meta WhatsApp Business API.
     */
    protected function sendViaMeta(string $to, string $message): array
    {
        try {
            $response = Http::withToken($this->accessToken)
                ->post($this->getMetaApiUrl('/messages'), [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $this->formatPhoneNumber($to),
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $message,
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message_id' => $data['messages'][0]['id'] ?? null,
                    'response' => $data,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('error.message', 'Unknown error'),
                'response' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp send failed', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send a template message (required for business-initiated conversations).
     * Note: Twilio uses Content Templates which work differently.
     * For Twilio, we fall back to regular text message with the rendered content.
     */
    public function sendTemplateMessage(
        string $to,
        string $templateName,
        string $languageCode = 'en',
        array $components = []
    ): array {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'WhatsApp is not configured',
            ];
        }

        // For Twilio, templates are handled via Content API or we send as text
        // Since our templates are stored in the database with content, we'll use text
        if ($this->provider === 'twilio') {
            // Extract body text from components if available
            $bodyText = $templateName; // Fallback to template name
            foreach ($components as $component) {
                if (($component['type'] ?? '') === 'body' && !empty($component['parameters'])) {
                    $texts = array_map(fn($p) => $p['text'] ?? '', $component['parameters']);
                    $bodyText = implode(' ', array_filter($texts));
                    break;
                }
            }
            return $this->sendViaTwilio($to, $bodyText);
        }

        // Meta API
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $this->formatPhoneNumber($to),
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => $languageCode,
                    ],
                ],
            ];

            if (!empty($components)) {
                $payload['template']['components'] = $components;
            }

            $response = Http::withToken($this->accessToken)
                ->post($this->getMetaApiUrl('/messages'), $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message_id' => $data['messages'][0]['id'] ?? null,
                    'response' => $data,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('error.message', 'Unknown error'),
                'response' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp template send failed', [
                'to' => $to,
                'template' => $templateName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
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
     * Send an interactive message with buttons.
     * Note: Twilio doesn't support interactive messages the same way.
     * For Twilio, we send as plain text.
     */
    public function sendInteractiveMessage(string $to, array $interactive): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'WhatsApp is not configured',
            ];
        }

        // For Twilio, extract body text and send as regular message
        if ($this->provider === 'twilio') {
            $bodyText = $interactive['body']['text'] ?? '';
            return $this->sendViaTwilio($to, $bodyText);
        }

        // Meta API
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $this->formatPhoneNumber($to),
                'type' => 'interactive',
                'interactive' => $interactive,
            ];

            $response = Http::withToken($this->accessToken)
                ->post($this->getMetaApiUrl('/messages'), $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message_id' => $data['messages'][0]['id'] ?? null,
                    'response' => $data,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('error.message', 'Unknown error'),
                'response' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp interactive message send failed', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
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
        if (!$template->hasButtons()) {
            // Fall back to text message if no buttons
            $rendered = $template->render($variables, $locale);
            return $this->sendTextMessage($to, $rendered['content']);
        }

        // For Twilio, append action links to the message
        if ($this->provider === 'twilio') {
            $rendered = $template->render($variables, $locale);
            $messageWithLinks = $this->appendButtonLinks($rendered['content'], $template, $variables, $locale);
            return $this->sendViaTwilio($to, $messageWithLinks);
        }

        $interactive = $template->buildInteractivePayload($variables, $locale);
        return $this->sendInteractiveMessage($to, $interactive);
    }

    /**
     * Append button action links to message text (for Twilio).
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
        if (!$appointmentId) {
            return $content;
        }

        $links = [];
        $locale = $locale ?? app()->getLocale();

        foreach ($buttons as $button) {
            $action = $button['action'] ?? '';
            $label = $button['label'] ?? '';

            // Generate signed URL for the action
            $url = $this->generateActionUrl($action, $appointmentId);
            if ($url) {
                $links[] = "🔗 {$label}: {$url}";
            }
        }

        if (!empty($links)) {
            $content .= "\n\n" . implode("\n", $links);
        }

        return $content;
    }

    /**
     * Generate a short URL for appointment action.
     */
    protected function generateActionUrl(string $action, string $appointmentId): ?string
    {
        $validActions = ['confirm_appointment', 'reschedule_appointment', 'cancel_appointment'];
        if (!in_array($action, $validActions)) {
            return null;
        }

        // Map action to route action name
        $routeAction = match ($action) {
            'confirm_appointment' => 'confirm',
            'reschedule_appointment' => 'reschedule',
            'cancel_appointment' => 'cancel',
            default => null,
        };

        if (!$routeAction) {
            return null;
        }

        // Get tenant_id from appointment
        $appointment = \Modules\Booking\Models\Appointment::find($appointmentId);
        $tenantId = $appointment?->tenant_id;

        // SECURITY: Generate signed URL that expires in 2 hours (not days)
        // Appointment actions shouldn't need more than a few hours validity
        // Longer expiry increases risk if URL is leaked or forwarded
        $baseUrl = request()->getSchemeAndHttpHost();
        $signedUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'appointment.action',
            now()->addHours(2),
            ['appointment' => $appointmentId, 'action' => $routeAction]
        );
        // Replace the APP_URL domain with the current tenant domain
        $signedUrl = preg_replace('/^https?:\/\/[^\/]+/', $baseUrl, $signedUrl);

        // Create short link (also expires in 2 hours / ~0.08 days)
        $shortLink = \Modules\Marketing\Models\ShortLink::createFor(
            $signedUrl,
            $tenantId,
            $routeAction,
            (int) $appointmentId,
            1  // 1 day is minimum, but the signed URL inside will expire in 2 hours
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

        // Check if this is an interactive reply (button click)
        if (($message['type'] ?? '') !== 'interactive') {
            return null;
        }

        $interactive = $message['interactive'] ?? [];

        if (($interactive['type'] ?? '') !== 'button_reply') {
            return null;
        }

        $buttonReply = $interactive['button_reply'] ?? [];
        $buttonId = $buttonReply['id'] ?? '';

        // Parse the button ID to get action and reference
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
            'status' => $status['status'] ?? null, // sent, delivered, read, failed
            'timestamp' => $status['timestamp'] ?? null,
            'recipient' => $status['recipient_id'] ?? null,
            'error' => $status['errors'][0]['message'] ?? null,
        ];
    }

    /**
     * Format phone number for WhatsApp API.
     * Expects phone in international format like +201234567890
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove any non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // If starts with +, remove it (APIs expect just numbers)
        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }

        // If starts with 0, assume Egyptian number and add country code
        if (str_starts_with($phone, '0')) {
            $phone = '20' . substr($phone, 1);
        }

        return '+' . $phone;
    }

    /**
     * Get Meta Graph API URL.
     */
    protected function getMetaApiUrl(string $endpoint = ''): string
    {
        return "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumber}{$endpoint}";
    }
}
