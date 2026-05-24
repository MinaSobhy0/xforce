@php
    /** @var bool $isConnected */
    /** @var bool $techProviderReady */
    /** @var string $metaAppId */
    /** @var string $metaConfigId */
    /** @var string $graphVersion */
    /** @var ?string $displayName */
    /** @var ?string $phoneE164 */
    /** @var ?string $wabaId */
    /** @var ?string $lastError */
    /** @var ?string $connectedAt */
@endphp

<x-filament-panels::page>
    @if (! $techProviderReady)
        {{-- Platform admin hasn't pasted the Meta app_id / config_id yet --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('marketing::whatsapp.placeholder.heading') }}</x-slot>
            <x-slot name="description">{{ __('marketing::whatsapp.placeholder.description') }}</x-slot>
        </x-filament::section>
    @elseif ($isConnected)
        <x-filament::section icon="heroicon-o-check-badge" icon-color="success">
            <x-slot name="heading">{{ $displayName ?: __('marketing::whatsapp.connected.heading') }}</x-slot>
            <x-slot name="description">{{ __('marketing::whatsapp.connected.description') }}</x-slot>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('marketing::whatsapp.connected.phone') }}</dt>
                    <dd class="font-medium">{{ $phoneE164 ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('marketing::whatsapp.connected.waba_id') }}</dt>
                    <dd class="font-mono text-xs">{{ $wabaId ?: '—' }}</dd>
                </div>
                @if ($connectedAt)
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('marketing::whatsapp.connected.connected_at') }}</dt>
                        <dd>{{ \Carbon\Carbon::parse($connectedAt)->diffForHumans() }}</dd>
                    </div>
                @endif
            </dl>
        </x-filament::section>
    @else
        {{-- Disconnected hero --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('marketing::whatsapp.disconnected.heading') }}</x-slot>
            <x-slot name="description">{{ __('marketing::whatsapp.disconnected.description') }}</x-slot>

            @if ($lastError)
                <div class="mb-4 rounded-lg bg-danger-50 dark:bg-danger-900/30 border border-danger-200 dark:border-danger-800 p-4 text-sm">
                    <p class="font-medium text-danger-700 dark:text-danger-300">{{ __('marketing::whatsapp.disconnected.last_error') }}</p>
                    <p class="mt-1 text-danger-600 dark:text-danger-400">{{ $lastError }}</p>
                </div>
            @endif

            <x-filament::button
                tag="button"
                color="success"
                size="lg"
                icon="heroicon-o-link"
                onclick="launchWhatsAppEmbeddedSignup()"
            >
                {{ __('marketing::whatsapp.disconnected.connect_button') }}
            </x-filament::button>

            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                {{ __('marketing::whatsapp.disconnected.help_text') }}
            </p>
        </x-filament::section>

        {{-- Meta JS SDK + Embedded Signup launcher --}}
        <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>
        <script>
            window.fbAsyncInit = function () {
                FB.init({
                    appId: @json($metaAppId),
                    xfbml: true,
                    version: @json($graphVersion),
                });
            };

            function launchWhatsAppEmbeddedSignup() {
                if (typeof FB === 'undefined') {
                    alert('Facebook SDK not loaded yet — please try again in a moment.');
                    return;
                }

                FB.login(function (response) {
                    if (response.authResponse && response.authResponse.code) {
                        // The popup also returns waba_id and phone_number_id via
                        // the message-event channel; modern Embedded Signup wraps
                        // them into response.authResponse.data.
                        const code = response.authResponse.code;
                        const data = response.authResponse.data || {};
                        const phoneNumberId = data.phone_number_id || '';
                        const wabaId = data.waba_id || '';

                        if (!phoneNumberId || !wabaId) {
                            alert('Meta did not return a phone_number_id / waba_id. Please try the signup again.');
                            return;
                        }

                        // Hand off to the Livewire page method.
                        @this.call('handleSignupCallback', code, phoneNumberId, wabaId);
                    } else {
                        // User closed the popup without completing.
                    }
                }, {
                    config_id: @json($metaConfigId),
                    response_type: 'code',
                    override_default_response_type: true,
                    extras: {
                        setup: {},
                        featureType: '',
                        sessionInfoVersion: '2',
                    },
                });
            }
        </script>
    @endif
</x-filament-panels::page>
