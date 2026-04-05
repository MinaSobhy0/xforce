<?php

namespace Modules\PatientPortal\Filament\Pages\Auth;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Component;
use Filament\Forms\Form;
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
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getPhoneFormComponent(),
                $this->getNameFormComponent(),
                $this->getOtpFormComponent(),
            ])
            ->statePath('data');
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
            ->placeholder('+20 1XX XXX XXXX')
            ->prefixIcon('heroicon-o-phone')
            ->disabled(fn () => $this->otpSent)
            ->autofocus();
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
        $phone = $this->normalizePhone($data['phone'] ?? '');

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

        if (!$patient->is_active) {
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
        $phone = $this->normalizePhone($data['phone'] ?? '');
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

        // Create new patient
        $patient = Patient::create([
            'name' => $fullName,
            'phone' => $phone,
            'is_active' => true,
            'source' => 'portal_registration',
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
        if ($otpService->generateAndSend($patient)) {
            $this->otpSent = true;
            $this->pendingPhone = $phone;

            Notification::make()
                ->title(__('patientportal::portal.otp_sent'))
                ->body(__('patientportal::portal.check_phone'))
                ->success()
                ->send();
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
        $phone = $this->normalizePhone($this->pendingPhone ?? $data['phone'] ?? '');
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

        Notification::make()
            ->title($result['message'])
            ->color($result['success'] ? 'success' : 'danger')
            ->send();
    }

    public function changePhone(): void
    {
        $this->otpSent = false;
        $this->pendingPhone = null;
        $this->otp = null;
    }

    protected function normalizePhone(string $phone): string
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Egyptian phone normalization
        if (str_starts_with($phone, '0')) {
            $phone = '+20' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [];
    }
}
