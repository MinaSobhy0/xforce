<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected string $provider;

    public function __construct()
    {
        $this->provider = config('marketing.sms.provider', 'twilio');
    }

    /**
     * Check if SMS is enabled and configured.
     */
    public function isEnabled(): bool
    {
        if (!config('marketing.sms.enabled', false)) {
            return false;
        }

        return match ($this->provider) {
            'twilio' => $this->isTwilioConfigured(),
            'vonage' => $this->isVonageConfigured(),
            'victorylink' => $this->isVictoryLinkConfigured(),
            default => false,
        };
    }

    /**
     * Send an SMS message.
     */
    public function send(string $to, string $message): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'SMS is not configured',
            ];
        }

        return match ($this->provider) {
            'twilio' => $this->sendViaTwilio($to, $message),
            'vonage' => $this->sendViaVonage($to, $message),
            'victorylink' => $this->sendViaVictoryLink($to, $message),
            default => ['success' => false, 'error' => 'Unknown SMS provider'],
        };
    }

    /**
     * Send via Twilio.
     */
    protected function sendViaTwilio(string $to, string $message): array
    {
        try {
            $sid = config('marketing.sms.twilio.sid');
            $token = config('marketing.sms.twilio.token');
            $from = config('marketing.sms.twilio.from');

            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To' => $this->formatPhoneNumber($to),
                    'From' => $from,
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

            return [
                'success' => false,
                'error' => $response->json('message', 'Unknown error'),
                'response' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('Twilio SMS failed', [
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
            $apiKey = config('marketing.sms.vonage.api_key');
            $apiSecret = config('marketing.sms.vonage.api_secret');
            $from = config('marketing.sms.vonage.from');

            $response = Http::post('https://rest.nexmo.com/sms/json', [
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
                'to' => $this->formatPhoneNumber($to),
                'from' => $from,
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
                    'error' => $messageData['error-text'] ?? 'Unknown error',
                    'response' => $data,
                ];
            }

            return [
                'success' => false,
                'error' => 'Request failed',
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
            $username = config('marketing.sms.victorylink.username');
            $password = config('marketing.sms.victorylink.password');
            $senderId = config('marketing.sms.victorylink.sender_id');

            $response = Http::get('https://smsvas.vlserv.com/KannelSending/service.asmx/SendSMS', [
                'username' => $username,
                'password' => $password,
                'senderid' => $senderId,
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
                'error' => 'Request failed',
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
     * Check Twilio configuration.
     */
    protected function isTwilioConfigured(): bool
    {
        return config('marketing.sms.twilio.sid')
            && config('marketing.sms.twilio.token')
            && config('marketing.sms.twilio.from');
    }

    /**
     * Check Vonage configuration.
     */
    protected function isVonageConfigured(): bool
    {
        return config('marketing.sms.vonage.api_key')
            && config('marketing.sms.vonage.api_secret')
            && config('marketing.sms.vonage.from');
    }

    /**
     * Check VictoryLink configuration.
     */
    protected function isVictoryLinkConfigured(): bool
    {
        return config('marketing.sms.victorylink.username')
            && config('marketing.sms.victorylink.password')
            && config('marketing.sms.victorylink.sender_id');
    }
}
