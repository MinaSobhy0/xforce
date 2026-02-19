<x-filament-panels::page>
    {{-- Personal 2FA Status Section --}}
    <x-filament::section>
        <x-slot name="heading">
            Your Two-Factor Authentication
        </x-slot>

        <x-slot name="description">
            Secure your account with two-factor authentication
        </x-slot>

        @php
            $user = $this->getUser();
        @endphp

        @if($user->hasEnabledTwoFactorAuthentication())
            <div class="flex items-center gap-3 p-4 bg-success-50 dark:bg-success-950 rounded-lg border border-success-200 dark:border-success-800">
                <x-heroicon-o-shield-check class="w-8 h-8 text-success-600 dark:text-success-400" />
                <div>
                    <h3 class="font-semibold text-success-900 dark:text-success-100">2FA is Enabled</h3>
                    <p class="text-sm text-success-700 dark:text-success-300">Your account is protected with two-factor authentication.</p>
                </div>
            </div>
        @elseif($showQrCode)
            <div class="space-y-6">
                <div class="flex items-center gap-3 p-4 bg-warning-50 dark:bg-warning-950 rounded-lg border border-warning-200 dark:border-warning-800">
                    <x-heroicon-o-exclamation-triangle class="w-8 h-8 text-warning-600 dark:text-warning-400" />
                    <div>
                        <h3 class="font-semibold text-warning-900 dark:text-warning-100">Setup in Progress</h3>
                        <p class="text-sm text-warning-700 dark:text-warning-300">Scan the QR code and enter the verification code to complete setup.</p>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-6">
                    {{-- QR Code --}}
                    <div class="flex flex-col items-center p-6 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-4">Scan QR Code</h4>
                        <div class="p-4 bg-white rounded-lg">
                            {!! $qrCodeSvg !!}
                        </div>
                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400 text-center">
                            Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)
                        </p>
                    </div>

                    {{-- Manual Entry & Confirmation --}}
                    <div class="p-6 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-4">Manual Setup</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                            If you can't scan the QR code, enter this key manually:
                        </p>
                        <div class="p-3 bg-gray-100 dark:bg-gray-700 rounded-lg font-mono text-sm break-all mb-6">
                            {{ $twoFactorSecret }}
                        </div>

                        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-4">Confirm Setup</h4>
                        <div class="space-y-4">
                            <x-filament::input.wrapper>
                                <x-filament::input
                                    type="text"
                                    wire:model="confirmationCode"
                                    placeholder="Enter 6-digit code"
                                    maxlength="6"
                                    class="text-center tracking-widest text-lg"
                                />
                            </x-filament::input.wrapper>

                            <x-filament::button
                                wire:click="confirmTwoFactor"
                                color="success"
                                class="w-full"
                            >
                                Verify & Enable 2FA
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="flex items-center gap-3 p-4 bg-danger-50 dark:bg-danger-950 rounded-lg border border-danger-200 dark:border-danger-800">
                <x-heroicon-o-shield-exclamation class="w-8 h-8 text-danger-600 dark:text-danger-400" />
                <div>
                    <h3 class="font-semibold text-danger-900 dark:text-danger-100">2FA is Not Enabled</h3>
                    <p class="text-sm text-danger-700 dark:text-danger-300">Your account is not protected with two-factor authentication. Click "Enable 2FA" to set it up.</p>
                </div>
            </div>
        @endif
    </x-filament::section>

    {{-- Recovery Codes Section --}}
    @if($showRecoveryCodes && count($recoveryCodes) > 0)
        <x-filament::section>
            <x-slot name="heading">
                Recovery Codes
            </x-slot>

            <x-slot name="description">
                Store these codes in a safe place. Each code can only be used once.
            </x-slot>

            <div class="space-y-4">
                <div class="flex items-center gap-3 p-4 bg-warning-50 dark:bg-warning-950 rounded-lg border border-warning-200 dark:border-warning-800">
                    <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-warning-600 dark:text-warning-400" />
                    <p class="text-sm text-warning-700 dark:text-warning-300">
                        Save these recovery codes in a secure location. They can be used to access your account if you lose your authenticator device.
                    </p>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @foreach($recoveryCodes as $code)
                        <div class="p-3 bg-gray-100 dark:bg-gray-700 rounded-lg font-mono text-sm text-center">
                            {{ $code }}
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end gap-3">
                    <x-filament::button
                        wire:click="hideRecoveryCodes"
                        color="gray"
                    >
                        Hide Codes
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @endif

    {{-- Platform Policy Section --}}
    <x-filament::section>
        <x-slot name="heading">
            Platform 2FA Policy
        </x-slot>

        <x-slot name="description">
            Configure two-factor authentication requirements for the entire platform
        </x-slot>

        <form wire:submit="save">
            {{ $this->form }}
        </form>
    </x-filament::section>
</x-filament-panels::page>
