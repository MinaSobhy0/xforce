<x-filament-panels::page.simple>
    @if(!$this->otpSent && !$this->isNewPatient)
        {{-- Phone entry form --}}
        <x-filament-panels::form wire:submit="sendOtp">
            {{ $this->form }}

            <div class="flex flex-col gap-4">
                <x-filament::button type="submit" class="w-full">
                    {{ __('patientportal::portal.send_otp') }}
                </x-filament::button>
            </div>
        </x-filament-panels::form>
    @elseif(!$this->otpSent && $this->isNewPatient)
        {{-- Registration form --}}
        <x-filament-panels::form wire:submit="registerAndSendOtp">
            {{ $this->form }}

            <div class="flex flex-col gap-4">
                <x-filament::button type="submit" class="w-full">
                    {{ __('patientportal::portal.register_and_send_otp') }}
                </x-filament::button>

                <x-filament::link wire:click="changePhone" tag="button" type="button" class="text-center">
                    {{ __('patientportal::portal.change_phone') }}
                </x-filament::link>
            </div>
        </x-filament-panels::form>
    @else
        {{-- OTP verification form --}}
        <x-filament-panels::form wire:submit="authenticate">
            {{ $this->form }}

            <div class="flex flex-col gap-4">
                <x-filament::button type="submit" class="w-full">
                    {{ __('patientportal::portal.verify_login') }}
                </x-filament::button>

                <div class="flex justify-between text-sm">
                    <x-filament::link wire:click="resendOtp" tag="button" type="button">
                        {{ __('patientportal::portal.resend_otp') }}
                    </x-filament::link>

                    <x-filament::link wire:click="changePhone" tag="button" type="button">
                        {{ __('patientportal::portal.change_phone') }}
                    </x-filament::link>
                </div>
            </div>
        </x-filament-panels::form>
    @endif

    <div class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
        <p>{{ __('patientportal::portal.secure_access') }}</p>
    </div>
</x-filament-panels::page.simple>
