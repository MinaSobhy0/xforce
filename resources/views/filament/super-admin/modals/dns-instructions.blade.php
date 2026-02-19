<div class="space-y-6">
    {{-- Current Domain Status --}}
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <div class="flex items-center justify-between">
            <div>
                <h4 class="font-medium text-gray-900 dark:text-gray-100">{{ $record->domain }}</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Clinic: {{ $record->tenant->name ?? 'Unknown' }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                @if($record->is_verified)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-800 dark:bg-success-800 dark:text-success-100">
                        DNS Verified
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-800 dark:text-warning-100">
                        DNS Pending
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- DNS Instructions --}}
    <div class="space-y-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Required DNS Records
        </h3>

        <p class="text-sm text-gray-600 dark:text-gray-400">
            Add the following DNS records to your domain registrar's DNS settings.
            Changes may take up to 24-48 hours to propagate.
        </p>

        {{-- CNAME Record --}}
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            <div class="bg-gray-100 dark:bg-gray-700 px-4 py-2 border-b border-gray-200 dark:border-gray-600">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Option 1: CNAME Record (Recommended)</span>
            </div>
            <div class="p-4 space-y-3">
                <div class="grid grid-cols-3 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400 block">Type</span>
                        <code class="text-gray-900 dark:text-gray-100 font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">CNAME</code>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400 block">Host/Name</span>
                        <code class="text-gray-900 dark:text-gray-100 font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                            {{ explode('.', $record->domain)[0] ?? '@' }}
                        </code>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400 block">Value/Target</span>
                        <code class="text-gray-900 dark:text-gray-100 font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                            {{ $targetHost }}
                        </code>
                    </div>
                </div>
            </div>
        </div>

        {{-- A Record Alternative --}}
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            <div class="bg-gray-100 dark:bg-gray-700 px-4 py-2 border-b border-gray-200 dark:border-gray-600">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Option 2: A Record (If CNAME not supported)</span>
            </div>
            <div class="p-4 space-y-3">
                <div class="grid grid-cols-3 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400 block">Type</span>
                        <code class="text-gray-900 dark:text-gray-100 font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">A</code>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400 block">Host/Name</span>
                        <code class="text-gray-900 dark:text-gray-100 font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">@</code>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400 block">Value/IP</span>
                        <code class="text-gray-900 dark:text-gray-100 font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                            {{ config('app.server_ip', '1.2.3.4') }}
                        </code>
                    </div>
                </div>
            </div>
        </div>

        {{-- TXT Verification Record --}}
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            <div class="bg-gray-100 dark:bg-gray-700 px-4 py-2 border-b border-gray-200 dark:border-gray-600">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Verification TXT Record (Optional)</span>
            </div>
            <div class="p-4 space-y-3">
                <div class="grid grid-cols-3 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400 block">Type</span>
                        <code class="text-gray-900 dark:text-gray-100 font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">TXT</code>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400 block">Host/Name</span>
                        <code class="text-gray-900 dark:text-gray-100 font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">_xlinic</code>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400 block">Value</span>
                        <code class="text-gray-900 dark:text-gray-100 font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-xs break-all">
                            verify={{ substr(md5($record->tenant_id . $record->domain), 0, 16) }}
                        </code>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SSL Information --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">SSL Certificate</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Once DNS verification is complete, you can provision a free Let's Encrypt SSL certificate
            using the "Provision SSL" button. The certificate will automatically renew before expiration.
        </p>

        @if($record->ssl_status === 'valid')
            <div class="mt-3 flex items-center gap-2 text-success-600 dark:text-success-400">
                <x-filament::icon icon="heroicon-o-shield-check" class="w-5 h-5" />
                <span class="text-sm">SSL certificate is active and valid until {{ $record->ssl_expires_at?->format('M d, Y') }}</span>
            </div>
        @endif
    </div>

    {{-- Troubleshooting --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Troubleshooting</h4>
        <ul class="text-sm text-gray-600 dark:text-gray-400 list-disc list-inside space-y-1">
            <li>DNS changes can take up to 48 hours to propagate globally</li>
            <li>Make sure you're editing the correct DNS zone</li>
            <li>Remove any existing conflicting records for the same hostname</li>
            <li>If using Cloudflare, disable the proxy (orange cloud) initially</li>
        </ul>
    </div>
</div>
