<div class="text-center space-y-4">
    @if($tenant)
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $tenant }}</h3>
    @endif

    <div class="flex justify-center p-4 bg-white rounded-lg">
        <img src="data:image/svg+xml;base64,{{ $qrCode }}" alt="QR Code" class="w-64 h-64">
    </div>

    <div class="space-y-2">
        <p class="text-sm text-gray-600 dark:text-gray-400">Code: <span class="font-mono font-bold">{{ $code }}</span></p>

        <div class="flex flex-col gap-2">
            <button
                type="button"
                x-data="{ copied: false }"
                x-on:click="
                    navigator.clipboard.writeText('{{ $deepLink }}');
                    copied = true;
                    setTimeout(() => copied = false, 2000);
                "
                class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                </svg>
                <span x-text="copied ? 'Copied!' : 'Copy Deep Link'"></span>
            </button>

            <a
                href="data:image/svg+xml;base64,{{ $qrCode }}"
                download="qr-code-{{ $code }}.svg"
                class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Download QR
            </a>
        </div>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400 break-all">{{ $deepLink }}</p>
</div>
