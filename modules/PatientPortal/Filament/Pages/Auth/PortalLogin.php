<?php

namespace Modules\PatientPortal\Filament\Pages\Auth;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Component;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Patients\Models\Patient;
use Modules\PatientPortal\Services\OtpService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Notifications\Notification;

class PortalLogin extends BaseLogin
{
    protected static string $view = 'patientportal::filament.pages.auth.login';

    public ?string $phone = null;
    public ?string $countryCode = null;
    public ?string $otp = null;
    public bool $otpSent = false;
    public ?string $pendingPhone = null;
    public bool $isNewPatient = false;
    public ?string $fullName = null;

    public function mount(): void
    {
        parent::mount();
        $this->otpSent = false;
        $this->isNewPatient = false;

        // Detect country code by IP location
        $this->countryCode = $this->getDetectedCountryCode();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)
                    ->schema([
                        $this->getCountryCodeFormComponent(),
                        $this->getPhoneFormComponent(),
                    ])
                    ->visible(fn () => !$this->otpSent),
                $this->getNameFormComponent(),
                $this->getOtpFormComponent(),
            ])
            ->statePath('data');
    }

    protected function getCountryCodeFormComponent(): Component
    {
        return \Filament\Forms\Components\Select::make('country_code')
            ->label(__('patientportal::portal.country_code'))
            ->options([
                '+20' => '🇪🇬 +20',
                '+966' => '🇸🇦 +966',
                '+971' => '🇦🇪 +971',
                '+965' => '🇰🇼 +965',
                '+974' => '🇶🇦 +974',
                '+968' => '🇴🇲 +968',
                '+973' => '🇧🇭 +973',
                '+962' => '🇯🇴 +962',
                '+961' => '🇱🇧 +961',
                '+964' => '🇮🇶 +964',
                '+1' => '🇺🇸 +1',
                '+44' => '🇬🇧 +44',
            ])
            ->default($this->countryCode ?? '+20')
            ->required()
            ->searchable()
            ->disabled(fn () => $this->otpSent)
            ->columnSpan(1);
    }

    protected function getNameFormComponent(): Component
    {
        return TextInput::make('full_name')
            ->label(__('patientportal::portal.full_name'))
            ->required()
            ->visible(fn () => $this->isNewPatient && !$this->otpSent)
            ->prefixIcon('heroicon-o-user')
            ->placeholder(__('patientportal::portal.enter_full_name'));
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label(__('patientportal::portal.phone'))
            ->tel()
            ->required()
            ->placeholder(__('patientportal::portal.phone_placeholder'))
            ->helperText(__('patientportal::portal.phone_helper'))
            ->prefixIcon('heroicon-o-phone')
            ->disabled(fn () => $this->otpSent)
            ->autofocus()
            ->columnSpan(2);
    }

    protected function getOtpFormComponent(): Component
    {
        return TextInput::make('otp')
            ->label(__('patientportal::portal.otp_code'))
            ->numeric()
            ->length(6)
            ->required()
            ->visible(fn () => $this->otpSent)
            ->prefixIcon('heroicon-o-key')
            ->placeholder('000000')
            ->autofocus(fn () => $this->otpSent);
    }

    public function sendOtp(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title(__('patientportal::portal.too_many_requests'))
                ->body(__('patientportal::portal.wait_seconds', ['seconds' => $exception->secondsUntilAvailable]))
                ->danger()
                ->send();
            return;
        }

        $data = $this->form->getState();
        $phone = $this->normalizePhone($data['phone'] ?? '', $data['country_code'] ?? null);

        if (empty($phone)) {
            Notification::make()
                ->title(__('patientportal::portal.invalid_phone'))
                ->danger()
                ->send();
            return;
        }

        // Find patient by phone
        $patient = Patient::where('phone', $phone)
            ->orWhere('phone', 'LIKE', '%' . substr($phone, -10))
            ->first();

        if (!$patient) {
            // New patient - show registration form
            $this->isNewPatient = true;
            Notification::make()
                ->title(__('patientportal::portal.new_patient'))
                ->body(__('patientportal::portal.enter_name_to_register'))
                ->info()
                ->send();
            return;
        }

        // Existing patient - check if they filled name when they shouldn't
        if ($this->isNewPatient) {
            Notification::make()
                ->title(__('patientportal::portal.patient_already_exists'))
                ->body(__('patientportal::portal.sending_otp'))
                ->info()
                ->send();
            $this->isNewPatient = false;
        }

        // Check if patient status is active and portal access is enabled
        if ($patient->status !== 'active' || !$patient->portal_access_enabled) {
            Notification::make()
                ->title(__('patientportal::portal.account_inactive'))
                ->danger()
                ->send();
            return;
        }

        $this->sendOtpToPatient($patient, $phone);
    }

    public function registerAndSendOtp(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title(__('patientportal::portal.too_many_requests'))
                ->danger()
                ->send();
            return;
        }

        $data = $this->form->getState();
        $phone = $this->normalizePhone($data['phone'] ?? '', $data['country_code'] ?? null);
        $fullName = trim($data['full_name'] ?? '');

        if (empty($phone) || empty($fullName)) {
            Notification::make()
                ->title(__('patientportal::portal.invalid_input'))
                ->danger()
                ->send();
            return;
        }

        // Check if patient already exists (phone might have been registered while form was open)
        $existing = Patient::where('phone', $phone)
            ->orWhere('phone', 'LIKE', '%' . substr($phone, -10))
            ->first();

        if ($existing) {
            Notification::make()
                ->title(__('patientportal::portal.patient_already_exists'))
                ->body(__('patientportal::portal.sending_otp'))
                ->info()
                ->send();
            $this->isNewPatient = false;
            $this->sendOtpToPatient($existing, $phone);
            return;
        }

        // Split full name into first and last name
        $nameParts = explode(' ', $fullName, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        // Create new patient
        $patient = Patient::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'status' => 'active',
            'portal_access_enabled' => true,
            'referral_source' => 'portal_registration',
        ]);

        Notification::make()
            ->title(__('patientportal::portal.registration_successful'))
            ->body(__('patientportal::portal.sending_otp'))
            ->success()
            ->send();

        $this->isNewPatient = false;
        $this->sendOtpToPatient($patient, $phone);
    }

    protected function sendOtpToPatient(Patient $patient, string $phone): void
    {
        // Generate and send OTP
        $otpService = app(OtpService::class);
        $result = $otpService->generateAndSend($patient);

        if ($result['success']) {
            $this->otpSent = true;
            $this->pendingPhone = $phone;

            // Check if messaging services are configured
            $whatsappEnabled = class_exists(\Modules\Marketing\Services\WhatsAppService::class)
                && app(\Modules\Marketing\Services\WhatsAppService::class)->isEnabled();
            $smsEnabled = class_exists(\Modules\Marketing\Services\SmsService::class)
                && app(\Modules\Marketing\Services\SmsService::class)->isEnabled();

            // Show OTP in notification if no messaging service is configured (development mode)
            if (!$whatsappEnabled && !$smsEnabled) {
                Notification::make()
                    ->title(__('patientportal::portal.otp_sent'))
                    ->body('Your verification code is: **' . $result['otp'] . '** (Valid for 5 minutes)')
                    ->success()
                    ->duration(30000) // Show for 30 seconds
                    ->send();
            } else {
                Notification::make()
                    ->title(__('patientportal::portal.otp_sent'))
                    ->body(__('patientportal::portal.check_phone'))
                    ->success()
                    ->send();
            }
        } else {
            Notification::make()
                ->title(__('patientportal::portal.otp_send_failed'))
                ->danger()
                ->send();
        }
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title(__('patientportal::portal.too_many_requests'))
                ->danger()
                ->send();
            return null;
        }

        $data = $this->form->getState();
        $phone = $this->normalizePhone($this->pendingPhone ?? $data['phone'] ?? '', $data['country_code'] ?? null);
        $otp = $data['otp'] ?? '';

        if (empty($phone) || empty($otp)) {
            Notification::make()
                ->title(__('patientportal::portal.invalid_input'))
                ->danger()
                ->send();
            return null;
        }

        // Verify OTP
        $otpService = app(OtpService::class);
        $result = $otpService->verify($phone, $otp);

        if (!$result['success']) {
            Notification::make()
                ->title($result['message'])
                ->danger()
                ->send();

            if (isset($result['attempts_remaining']) && $result['attempts_remaining'] <= 0) {
                $this->otpSent = false;
                $this->pendingPhone = null;
            }

            return null;
        }

        // Login the patient
        $patient = Patient::find($result['patient_id']);
        if (!$patient) {
            Notification::make()
                ->title(__('patientportal::portal.patient_not_found'))
                ->danger()
                ->send();
            return null;
        }

        Auth::guard('patient')->login($patient, true);

        session()->regenerate();

        return app(LoginResponse::class);
    }

    public function resendOtp(): void
    {
        if (!$this->pendingPhone) {
            $this->otpSent = false;
            return;
        }

        $otpService = app(OtpService::class);
        $result = $otpService->resend($this->pendingPhone);

        if ($result['success']) {
            // Check if messaging services are configured
            $whatsappEnabled = class_exists(\Modules\Marketing\Services\WhatsAppService::class)
                && app(\Modules\Marketing\Services\WhatsAppService::class)->isEnabled();
            $smsEnabled = class_exists(\Modules\Marketing\Services\SmsService::class)
                && app(\Modules\Marketing\Services\SmsService::class)->isEnabled();

            // Show OTP in notification if no messaging service is configured
            if (!$whatsappEnabled && !$smsEnabled && isset($result['otp'])) {
                Notification::make()
                    ->title($result['message'])
                    ->body('Your verification code is: **' . $result['otp'] . '** (Valid for 5 minutes)')
                    ->success()
                    ->duration(30000)
                    ->send();
            } else {
                Notification::make()
                    ->title($result['message'])
                    ->success()
                    ->send();
            }
        } else {
            Notification::make()
                ->title($result['message'])
                ->danger()
                ->send();
        }
    }

    public function changePhone(): void
    {
        $this->otpSent = false;
        $this->pendingPhone = null;
        $this->otp = null;
    }

    protected function normalizePhone(string $phone, ?string $countryCode = null): string
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // If phone already has country code, return as is
        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        // Remove leading zero if present
        if (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        // Use provided country code or default to +20 (Egypt)
        $countryCode = $countryCode ?? '+20';

        // Remove + from country code and add it back
        $countryCode = str_replace('+', '', $countryCode);

        return '+' . $countryCode . $phone;
    }

    protected function getDetectedCountryCode(): string
    {
        try {
            // Get client IP
            $ip = request()->ip();

            // Don't detect for local IPs
            if (in_array($ip, ['127.0.0.1', 'localhost', '::1'])) {
                return '+20'; // Default to Egypt
            }

            // Use ip-api.com for geolocation (free, no API key needed)
            $response = \Illuminate\Support\Facades\Http::timeout(3)
                ->get("http://ip-api.com/json/{$ip}?fields=countryCode");

            if ($response->successful()) {
                $data = $response->json();
                $countryCode = $data['countryCode'] ?? 'EG';

                // Map country codes to phone codes
                $phoneCodeMap = [
                    'EG' => '+20',
                    'SA' => '+966',
                    'AE' => '+971',
                    'KW' => '+965',
                    'QA' => '+974',
                    'OM' => '+968',
                    'BH' => '+973',
                    'JO' => '+962',
                    'LB' => '+961',
                    'IQ' => '+964',
                    'US' => '+1',
                    'CA' => '+1',
                    'GB' => '+44',
                ];

                return $phoneCodeMap[$countryCode] ?? '+20';
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Country detection failed: ' . $e->getMessage());
        }

        return '+20'; // Default to Egypt
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [];
    }
}
