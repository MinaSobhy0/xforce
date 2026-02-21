<div class="space-y-3">
    @foreach($packages as $subscription)
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900">
                    <x-heroicon-o-gift class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <h5 class="font-medium text-gray-900 dark:text-white">
                        {{ $subscription->package->translated_name }}
                    </h5>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('booking::booking.labels.sessions_remaining', ['count' => $subscription->sessions_remaining]) }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-4">
                @if($subscription->expires_at)
                    <div class="text-sm">
                        @if($subscription->isExpiringSoon())
                            <span class="text-warning-600 dark:text-warning-400">
                                <x-heroicon-o-exclamation-triangle class="inline h-4 w-4" />
                                {{ __('booking::booking.labels.expires_in', ['days' => $subscription->days_until_expiry]) }}
                            </span>
                        @else
                            <span class="text-gray-500 dark:text-gray-400">
                                {{ __('booking::booking.labels.expires', ['date' => $subscription->expires_at->format('M d, Y')]) }}
                            </span>
                        @endif
                    </div>
                @endif

                <div>
                    <span @class([
                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                        'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-300' => $subscription->isActive(),
                        'bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-300' => $subscription->isFrozen(),
                    ])>
                        {{ $subscription->status_label }}
                    </span>
                </div>
            </div>
        </div>
    @endforeach
</div>
