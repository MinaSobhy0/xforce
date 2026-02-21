<x-filament-panels::page>
    {{-- Balance Checker --}}
    <x-filament::section class="mb-6">
        <x-slot name="heading">
            {{ __('patientportal::portal.check_balance') }}
        </x-slot>

        <div class="flex gap-4 items-end">
            <div class="flex-1">
                <x-filament::input.wrapper>
                    <x-filament::input
                        wire:model="checkCode"
                        placeholder="{{ __('patientportal::portal.enter_code') }}"
                    />
                </x-filament::input.wrapper>
            </div>
            <x-filament::button wire:click="checkBalance">
                {{ __('patientportal::portal.check') }}
            </x-filament::button>
        </div>

        @if($checkedCard)
            <div class="mt-4 p-4 bg-success-50 dark:bg-success-900/20 rounded-lg">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">{{ __('patientportal::portal.code') }}</p>
                        <p class="font-medium">{{ $checkedCard['code'] }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">{{ __('patientportal::portal.status') }}</p>
                        <p class="font-medium capitalize">{{ $checkedCard['status'] }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">{{ __('patientportal::portal.current_balance') }}</p>
                        <p class="font-bold text-success-600">{{ number_format($checkedCard['current_balance'] / 100, 2) }} EGP</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">{{ __('patientportal::portal.expires') }}</p>
                        <p class="font-medium">{{ $checkedCard['expires_at'] ?? __('patientportal::portal.no_expiry') }}</p>
                    </div>
                </div>
            </div>
        @endif
    </x-filament::section>

    {{-- My Gift Cards --}}
    <x-filament::section>
        <x-slot name="heading">
            {{ __('patientportal::portal.my_gift_cards') }}
        </x-slot>

        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
