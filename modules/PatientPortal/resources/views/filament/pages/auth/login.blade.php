<x-filament-panels::page.simple>
    <x-filament-panels::form wire:submit="{{ $this->otpSent ? 'authenticate' : 'sendOtp' }}">
        {{ $this->form }}

        <div class="flex flex-col gap-4">
            @if(!$this->otpSent)
                <x-filament::button type="submit" class="w-full">
                    {{ __('patientportal::portal.send_otp') }}
                </x-filament::button>
            @else
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
            @endif
        </div>
    </x-filament-panels::form>

    <div class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
        <p>{{ __('patientportal::portal.not_registered') }}</p>
        <p class="mt-1">{{ __('patientportal::portal.visit_clinic') }}</p>
    </div>
</x-filament-panels::page.simple>
