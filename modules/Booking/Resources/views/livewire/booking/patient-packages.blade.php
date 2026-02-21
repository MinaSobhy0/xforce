<div class="space-y-4">
    {{-- Active Packages --}}
    @if($activePackages->isNotEmpty())
        <div class="space-y-3">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('booking::booking.labels.active_packages') }}
            </h4>

            @foreach($activePackages as $subscription)
                <div
                    wire:click="selectPackage('{{ $subscription->id }}')"
                    @class([
                        'cursor-pointer rounded-lg border p-4 transition-all',
                        'border-primary-500 bg-primary-50 ring-2 ring-primary-500 dark:border-primary-400 dark:bg-primary-900/20' => $selectedSubscriptionId === $subscription->id,
                        'border-gray-200 bg-white hover:border-primary-300 hover:shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-500' => $selectedSubscriptionId !== $subscription->id,
                    ])
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900">
                                <x-heroicon-o-gift class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                            </div>
                            <div>
                                <h5 class="font-semibold text-gray-900 dark:text-white">
                                    {{ $subscription->package->translated_name }}
                                </h5>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('booking::booking.labels.sessions_remaining', ['count' => $subscription->sessions_remaining]) }}
                                </p>

                                {{-- Services in package --}}
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @foreach($subscription->package->items->take(3) as $item)
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                            {{ $item->service?->translated_name }}
                                            ({{ $subscription->getSessionsRemainingByService($item->service_id) }})
                                        </span>
                                    @endforeach
                                    @if($subscription->package->items->count() > 3)
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                            +{{ $subscription->package->items->count() - 3 }} more
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col items-end gap-2">
                            @if($subscription->expires_at)
                                @if($subscription->isExpiringSoon())
                                    <span class="flex items-center gap-1 text-xs text-warning-600 dark:text-warning-400">
                                        <x-heroicon-o-exclamation-triangle class="h-4 w-4" />
                                        {{ __('booking::booking.labels.expires_in', ['days' => $subscription->days_until_expiry]) }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ __('booking::booking.labels.expires', ['date' => $subscription->expires_at->format('M d')]) }}
                                    </span>
                                @endif
                            @endif

                            @if($selectedSubscriptionId === $subscription->id)
                                <span class="inline-flex items-center rounded-full bg-primary-600 px-2 py-0.5 text-xs font-medium text-white">
                                    <x-heroicon-o-check class="mr-1 h-3 w-3" />
                                    {{ __('booking::booking.labels.selected') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Usage Progress --}}
                    <div class="mt-3">
                        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ __('booking::booking.labels.usage_progress') }}</span>
                            <span>{{ $subscription->usage_progress }}%</span>
                        </div>
                        <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                            <div
                                class="h-full rounded-full bg-primary-600 transition-all"
                                style="width: {{ $subscription->usage_progress }}%"
                            ></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-lg border-2 border-dashed border-gray-300 p-6 text-center dark:border-gray-600">
            <x-heroicon-o-gift class="mx-auto h-8 w-8 text-gray-400" />
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('booking::booking.messages.no_active_packages') }}
            </p>
        </div>
    @endif

    {{-- Purchase New Package --}}
    <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
        <button
            type="button"
            wire:click="togglePurchaseForm"
            class="flex w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 p-3 text-sm font-medium text-gray-600 hover:border-primary-400 hover:text-primary-600 dark:border-gray-600 dark:text-gray-400 dark:hover:border-primary-500 dark:hover:text-primary-400"
        >
            <x-heroicon-o-plus-circle class="h-5 w-5" />
            {{ __('booking::booking.actions.purchase_package') }}
        </button>

        @if($showPurchaseForm)
            <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                <h5 class="mb-3 font-medium text-gray-900 dark:text-white">
                    {{ __('booking::booking.labels.select_package_to_purchase') }}
                </h5>

                <select
                    wire:model="newPackageId"
                    class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                >
                    <option value="">{{ __('booking::booking.placeholders.select_package') }}</option>
                    @foreach($availablePackages as $package)
                        <option value="{{ $package->id }}">
                            {{ $package->translated_name }} - {{ $package->formatted_price }}
                            ({{ $package->total_sessions }} {{ __('booking::booking.labels.sessions') }})
                        </option>
                    @endforeach
                </select>

                <div class="mt-3 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="togglePurchaseForm"
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        {{ __('booking::booking.actions.cancel') }}
                    </button>
                    <button
                        type="button"
                        wire:click="purchasePackage"
                        class="rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700"
                        {{ !$newPackageId ? 'disabled' : '' }}
                    >
                        {{ __('booking::booking.actions.purchase') }}
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
