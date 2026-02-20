<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\NotificationLog;

class WhatsAppService
{
    protected string $apiVersion;
    protected ?string $phoneNumberId;
    protected ?string $accessToken;

    public function __construct()
    {
        $this->apiVersion = config('marketing.whatsapp.api_version', 'v18.0');
        $this->phoneNumberId = config('marketing.whatsapp.phone_number_id');
        $this->accessToken = config('marketing.whatsapp.access_token');
    }

    /**
     * Check if WhatsApp is enabled and configured.
     */
    public function isEnabled(): bool
    {
        return config('marketing.whatsapp.enabled', false)
            && $this->phoneNumberId
            && $this->accessToken;
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

        try {
            $response = Http::withToken($this->accessToken)
                ->post($this->getApiUrl('/messages'), [
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
                ->post($this->getApiUrl('/messages'), $payload);

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
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // If starts with 0, assume Egyptian number and add country code
        if (str_starts_with($phone, '0')) {
            $phone = '2' . $phone;
        }

        // Ensure it starts with country code
        if (!str_starts_with($phone, '2')) {
            $phone = '2' . $phone;
        }

        return $phone;
    }

    /**
     * Get API URL.
     */
    protected function getApiUrl(string $endpoint = ''): string
    {
        return "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}{$endpoint}";
    }
}
