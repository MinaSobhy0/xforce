<x-filament-panels::page>
    <div class="max-w-lg mx-auto">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-primary-500 to-primary-600 px-6 py-8 text-center">
                <h2 class="text-2xl font-bold text-white">{{ $this->tenant?->name ?? 'Clinic' }}</h2>
                <p class="mt-2 text-primary-100">{{ __('mobile_api::mobile.filament.qr_page_description') }}</p>
            </div>

            {{-- QR Code --}}
            <div class="p-8">
                @if($this->qrCodeSvg)
                    <div class="flex justify-center mb-6">
                        <div class="p-4 bg-white rounded-lg shadow-inner">
                            <img
                                src="data:image/svg+xml;base64,{{ $this->qrCodeSvg }}"
                                alt="QR Code"
                                class="w-72 h-72"
                                id="qr-code-image"
                            >
                        </div>
                    </div>

                    {{-- Code Display --}}
                    <div class="text-center mb-6">
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">{{ __('mobile_api::mobile.filament.clinic_code') }}</p>
                        <p class="text-3xl font-mono font-bold text-gray-900 dark:text-white tracking-wider">{{ $this->code }}</p>
                    </div>

                    {{-- Actions --}}
                    <div class="space-y-3">
                        <button
                            type="button"
                            x-data
                            x-on:click="
                                const link = document.createElement('a');
                                link.href = 'data:image/svg+xml;base64,{{ $this->qrCodeSvg }}';
                                link.download = 'qr-code-{{ $this->code }}.svg';
                                link.click();
                            "
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 text-sm font-semibold text-white bg-primary-600 rounded-lg hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            {{ __('mobile_api::mobile.filament.download_qr') }}
                        </button>

                        <button
                            type="button"
                            x-data="{ copied: false }"
                            x-on:click="
                                navigator.clipboard.writeText('{{ $this->deepLink }}');
                                copied = true;
                                setTimeout(() => copied = false, 2000);
                                $wire.copyDeepLink();
                            "
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                            </svg>
                            <span x-text="copied ? '{{ __("mobile_api::mobile.filament.link_copied") }}' : '{{ __("mobile_api::mobile.filament.copy_link") }}'"></span>
                        </button>
                    </div>

                    {{-- Deep Link Display --}}
                    <div class="mt-6 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                        <p class="text-xs text-gray-500 dark:text-gray-400 text-center break-all font-mono">{{ $this->deepLink }}</p>
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto text-gray-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                        <p class="mt-4 text-gray-500 dark:text-gray-400">Unable to generate QR code. Please contact support.</p>
                    </div>
                @endif
            </div>

            {{-- Footer Instructions --}}
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-100 dark:border-gray-700">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">How to use:</h4>
                <ol class="text-sm text-gray-600 dark:text-gray-400 space-y-1 list-decimal list-inside">
                    <li>Download the XLinic mobile app</li>
                    <li>Open the app and tap "Connect to Clinic"</li>
                    <li>Scan this QR code or enter the code manually</li>
                    <li>Log in with your staff credentials</li>
                </ol>
            </div>
        </div>

        {{-- Download Links --}}
        <div class="mt-6 flex justify-center gap-4">
            <a href="#" class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09l.01-.01zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/>
                </svg>
                App Store
            </a>
            <a href="#" class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M3.609 1.814L13.792 12 3.61 22.186a.996.996 0 01-.61-.92V2.734a1 1 0 01.609-.92zm10.89 10.893l2.302 2.302-10.937 6.333 8.635-8.635zm3.199-3.198l2.807 1.626a1 1 0 010 1.73l-2.808 1.626L15.206 12l2.492-2.491zM5.864 2.658L16.8 8.99l-2.302 2.302-8.634-8.634z"/>
                </svg>
                Google Play
            </a>
        </div>
    </div>
</x-filament-panels::page>
