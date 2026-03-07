<?php

namespace Modules\Marketing\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected bool $enabled;
    protected string $provider;
    protected ?string $apiKey;
    protected ?string $apiSecret;
    protected ?string $senderId;

    public function __construct()
    {
        $this->loadSettings();
    }

    /**
     * Load settings from PlatformSetting (SuperAdmin configuration).
     */
    protected function loadSettings(): void
    {
        $this->enabled = (bool) PlatformSetting::get('sms_enabled', false);
        $this->provider = PlatformSetting::get('sms_provider', 'twilio');
        $this->apiKey = PlatformSetting::get('sms_api_key', '');      // Twilio Account SID / API Key
        $this->apiSecret = PlatformSetting::get('sms_api_secret', ''); // Twilio Auth Token / API Secret
        $this->senderId = PlatformSetting::get('sms_sender_id', '');   // From number / Sender ID
    }

    /**
     * Refresh settings (useful after settings update).
     */
    public function refreshSettings(): void
    {
        $this->loadSettings();
    }

    /**
     * Check if SMS is enabled and configured.
     */
    public function isEnabled(): bool
    {
        return $this->enabled
            && $this->apiKey
            && $this->apiSecret;
    }

    /**
     * Get the current provider.
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Send an SMS message.
     */
    public function send(string $to, string $message): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'SMS is not configured. Please configure SMS settings in Platform → Integrations.',
            ];
        }

        return match ($this->provider) {
            'twilio' => $this->sendViaTwilio($to, $message),
            'vonage' => $this->sendViaVonage($to, $message),
            'victorylink' => $this->sendViaVictoryLink($to, $message),
            'cequens' => $this->sendViaCequens($to, $message),
            'messagebird' => $this->sendViaMessageBird($to, $message),
            default => ['success' => false, 'error' => "Unknown SMS provider: {$this->provider}"],
        };
    }

    /**
     * Send via Twilio.
     */
    protected function sendViaTwilio(string $to, string $message): array
    {
        try {
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->apiKey}/Messages.json", [
                    'To' => $this->formatPhoneNumber($to),
                    'From' => $this->senderId,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('SMS sent via Twilio', [
                    'to' => $to,
                    'sid' => $data['sid'] ?? null,
                    'status' => $data['status'] ?? null,
                ]);

                return [
                    'success' => true,
                    'message_id' => $data['sid'] ?? null,
                    'status' => $data['status'] ?? null,
                    'response' => $data,
                ];
            }

            $error = $response->json('message', 'Unknown Twilio error');
            Log::error('Twilio SMS failed', [
                'to' => $to,
                'error' => $error,
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'error' => $error,
                'response' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('Twilio SMS exception', [
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
     * Send via Vonage (Nexmo).
     */
    protected function sendViaVonage(string $to, string $message): array
    {
        try {
            $response = Http::post('https://rest.nexmo.com/sms/json', [
                'api_key' => $this->apiKey,
                'api_secret' => $this->apiSecret,
                'to' => $this->formatPhoneNumber($to),
                'from' => $this->senderId,
                'text' => $message,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $messageData = $data['messages'][0] ?? [];

                if (($messageData['status'] ?? '1') === '0') {
                    return [
                        'success' => true,
                        'message_id' => $messageData['message-id'] ?? null,
                        'response' => $data,
                    ];
                }

                return [
                    'success' => false,
                    'error' => $messageData['error-text'] ?? 'Unknown Vonage error',
                    'response' => $data,
                ];
            }

            return [
                'success' => false,
                'error' => 'Vonage request failed',
                'response' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('Vonage SMS failed', [
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
     * Send via VictoryLink (Egyptian SMS provider).
     */
    protected function sendViaVictoryLink(string $to, string $message): array
    {
        try {
            $response = Http::get('https://smsvas.vlserv.com/KannelSending/service.asmx/SendSMS', [
                'username' => $this->apiKey,
                'password' => $this->apiSecret,
                'senderid' => $this->senderId,
                'mobileno' => $this->formatPhoneNumber($to),
                'message' => $message,
            ]);

            if ($response->successful()) {
                $body = $response->body();
                // VictoryLink returns XML, parse for success
                if (str_contains($body, 'Success') || str_contains($body, 'success')) {
                    return [
                        'success' => true,
                        'message_id' => md5($to . time()), // Generate a pseudo ID
                        'response' => ['raw' => $body],
                    ];
                }

                return [
                    'success' => false,
                    'error' => $body,
                ];
            }

            return [
                'success' => false,
                'error' => 'VictoryLink request failed',
            ];
        } catch (\Exception $e) {
            Log::error('VictoryLink SMS failed', [
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
     * Send via Cequens (MENA provider).
     */
    protected function sendViaCequens(string $to, string $message): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiSecret}",
                'Content-Type' => 'application/json',
            ])->post('https://apis.cequens.com/sms/v1/messages', [
                'senderName' => $this->senderId,
                'messageType' => 'text',
                'messageText' => $message,
                'recipients' => $this->formatPhoneNumber($to),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message_id' => $data['messageId'] ?? null,
                    'response' => $data,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('message', 'Cequens request failed'),
                'response' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('Cequens SMS failed', [
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
     * Send via MessageBird.
     */
    protected function sendViaMessageBird(string $to, string $message): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "AccessKey {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->post('https://rest.messagebird.com/messages', [
                'originator' => $this->senderId,
                'recipients' => [$this->formatPhoneNumber($to)],
                'body' => $message,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message_id' => $data['id'] ?? null,
                    'response' => $data,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('errors.0.description', 'MessageBird request failed'),
                'response' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('MessageBird SMS failed', [
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
     * Format phone number for SMS.
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // If starts with 0, assume Egyptian number and add country code
        if (str_starts_with($phone, '0')) {
            $phone = '+2' . $phone;
        } elseif (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    /**
     * Validate phone number format.
     */
    public function validatePhoneNumber(string $phone): bool
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }

    /**
     * Get configuration status for display.
     */
    public function getStatus(): array
    {
        return [
            'enabled' => $this->enabled,
            'provider' => $this->provider,
            'configured' => $this->isEnabled(),
            'sender_id' => $this->senderId ? substr($this->senderId, 0, 4) . '***' : null,
        ];
    }
}
