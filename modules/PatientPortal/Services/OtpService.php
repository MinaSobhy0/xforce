<?php

namespace Modules\PatientPortal\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Patients\Models\Patient;

class OtpService
{
    protected int $otpLength;
    protected int $otpExpiry;
    protected int $maxAttempts;

    public function __construct()
    {
        $this->otpLength = config('patientportal.otp.length', 6);
        $this->otpExpiry = config('patientportal.otp.expiry_minutes', 5);
        $this->maxAttempts = config('patientportal.otp.max_attempts', 3);
    }

    /**
     * Generate and send OTP to patient
     */
    public function generateAndSend(Patient $patient): bool
    {
        $otp = $this->generateOtp();
        $phone = $patient->phone;

        // Store OTP in cache
        $cacheKey = $this->getCacheKey($phone);
        Cache::put($cacheKey, [
            'otp' => $otp,
            'attempts' => 0,
            'patient_id' => $patient->id,
        ], now()->addMinutes($this->otpExpiry));

        // Send OTP via WhatsApp or SMS
        return $this->sendOtp($patient, $otp);
    }

    /**
     * Verify OTP
     */
    public function verify(string $phone, string $otp): array
    {
        $cacheKey = $this->getCacheKey($phone);
        $cached = Cache::get($cacheKey);

        if (!$cached) {
            return [
                'success' => false,
                'message' => __('patientportal::portal.otp_expired'),
            ];
        }

        // Check attempts
        if ($cached['attempts'] >= $this->maxAttempts) {
            Cache::forget($cacheKey);
            return [
                'success' => false,
                'message' => __('patientportal::portal.max_attempts_exceeded'),
            ];
        }

        // SECURITY: Use timing-safe comparison to prevent timing attacks
        // hash_equals() prevents attackers from guessing OTP digit-by-digit
        if (!hash_equals((string) $cached['otp'], (string) $otp)) {
            // Increment attempts
            $cached['attempts']++;
            Cache::put($cacheKey, $cached, now()->addMinutes($this->otpExpiry));

            return [
                'success' => false,
                'message' => __('patientportal::portal.invalid_otp'),
                'attempts_remaining' => $this->maxAttempts - $cached['attempts'],
            ];
        }

        // OTP verified, clear cache
        Cache::forget($cacheKey);

        return [
            'success' => true,
            'patient_id' => $cached['patient_id'],
        ];
    }

    /**
     * Resend OTP
     */
    public function resend(string $phone): array
    {
        $patient = Patient::where('phone', $phone)->first();

        if (!$patient) {
            return [
                'success' => false,
                'message' => __('patientportal::portal.patient_not_found'),
            ];
        }

        // Check cooldown
        $cooldownKey = "otp_cooldown:{$phone}";
        if (Cache::has($cooldownKey)) {
            $remaining = Cache::get($cooldownKey);
            return [
                'success' => false,
                'message' => __('patientportal::portal.wait_before_resend', ['seconds' => $remaining]),
            ];
        }

        // Set cooldown
        $cooldownSeconds = config('patientportal.otp.cooldown_seconds', 60);
        Cache::put($cooldownKey, $cooldownSeconds, now()->addSeconds($cooldownSeconds));

        if ($this->generateAndSend($patient)) {
            return [
                'success' => true,
                'message' => __('patientportal::portal.otp_sent'),
            ];
        }

        return [
            'success' => false,
            'message' => __('patientportal::portal.otp_send_failed'),
        ];
    }

    /**
     * Generate numeric OTP
     */
    protected function generateOtp(): string
    {
        $min = pow(10, $this->otpLength - 1);
        $max = pow(10, $this->otpLength) - 1;
        return (string) random_int($min, $max);
    }

    /**
     * Get cache key for phone
     */
    protected function getCacheKey(string $phone): string
    {
        return "portal_otp:{$phone}";
    }

    /**
     * Send OTP to patient via WhatsApp/SMS
     */
    protected function sendOtp(Patient $patient, string $otp): bool
    {
        try {
            // Try WhatsApp first, then SMS
            $message = __('patientportal::portal.otp_message', [
                'otp' => $otp,
                'minutes' => $this->otpExpiry,
            ]);

            Log::info("Attempting to send OTP", [
                'phone' => $patient->phone,
                'otp' => $otp,
                'message' => $message,
            ]);

            // Check if Marketing module is available
            if (class_exists(\Modules\Marketing\Services\WhatsAppService::class)) {
                $whatsapp = app(\Modules\Marketing\Services\WhatsAppService::class);

                Log::info("Trying WhatsApp", [
                    'enabled' => $whatsapp->isEnabled(),
                    'provider' => $whatsapp->getProvider(),
                ]);

                $result = $whatsapp->sendTextMessage($patient->phone, $message);

                Log::info("WhatsApp send result", ['result' => $result]);

                if ($result['success'] ?? false) {
                    Log::info("OTP sent successfully via WhatsApp");
                    return true;
                }
            }

            // Fallback to SMS
            if (class_exists(\Modules\Marketing\Services\SmsService::class)) {
                $sms = app(\Modules\Marketing\Services\SmsService::class);

                Log::info("Trying SMS", [
                    'enabled' => $sms->isEnabled(),
                    'provider' => $sms->getProvider(),
                ]);

                $result = $sms->send($patient->phone, $message);

                Log::info("SMS send result", ['result' => $result]);

                if ($result['success'] ?? false) {
                    Log::info("OTP sent successfully via SMS");
                    return true;
                }
            }

            // Log for development (no WhatsApp/SMS configured)
            Log::warning("No messaging service configured, logging OTP", [
                'phone' => $patient->phone,
                'otp' => $otp,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to send OTP", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }
}
