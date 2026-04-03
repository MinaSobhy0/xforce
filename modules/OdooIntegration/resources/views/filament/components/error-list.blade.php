<div class="space-y-4">
    @forelse($errors ?? [] as $error)
        <div class="bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-700 rounded-lg p-4">
            <div class="flex items-start">
                <x-heroicon-o-exclamation-circle class="h-5 w-5 text-danger-500 mr-2 flex-shrink-0" />
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-danger-700 dark:text-danger-300">
                        {{ $error['message'] ?? $error }}
                    </p>
                    @if(isset($error['timestamp']))
                        <p class="text-xs text-danger-500 mt-1">
                            {{ \Carbon\Carbon::parse($error['timestamp'])->format('Y-m-d H:i:s') }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
            {{ __('odoo-integration::odoo.empty.no_errors') }}
        </p>
    @endforelse
</div>
