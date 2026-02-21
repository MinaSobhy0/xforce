<div class="space-y-4">
    @if(count($items) > 0)
        {{-- Items List --}}
        <div class="space-y-2">
            @foreach($items as $index => $item)
                <div class="relative rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">
                    {{-- Remove Button --}}
                    <button
                        type="button"
                        wire:click="removeBookingItem({{ $index }})"
                        class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-danger-500 text-white shadow-md transition-colors hover:bg-danger-600"
                        title="{{ __('booking::booking.actions.remove') }}"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    {{-- Service Name --}}
                    <h5 class="pr-4 font-medium text-gray-900 dark:text-white">
                        {{ $item['service_name'] }}
                    </h5>

                    {{-- Details --}}
                    <div class="mt-2 space-y-1 text-xs text-gray-600 dark:text-gray-400">
                        <div class="flex items-center gap-2">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>{{ \Carbon\Carbon::parse($item['date'])->format('D, M d') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>{{ $item['start_time'] }} - {{ $item['end_time'] }}</span>
                        </div>
                        @if($item['practitioner_name'])
                            <div class="flex items-center gap-2">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span>{{ $item['practitioner_name'] }}</span>
                            </div>
                        @endif
                        @if($item['room_name'])
                            <div class="flex items-center gap-2">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                <span>{{ $item['room_name'] }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Package Badge --}}
                    @if(!empty($item['from_package']))
                        <div class="mt-2">
                            <span class="inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                                <svg class="mr-1 h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                                </svg>
                                {{ __('booking::booking.labels.from_package') }}
                            </span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Summary --}}
        <div class="rounded-lg border border-primary-200 bg-primary-50 p-3 dark:border-primary-800 dark:bg-primary-900/20">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600 dark:text-gray-300">{{ __('booking::booking.labels.total') }}</span>
                <span class="text-lg font-bold text-primary-600 dark:text-primary-400">
                    {{ count($items) }} {{ __('booking::booking.labels.appointments_count') }}
                </span>
            </div>
            <div class="mt-1 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>{{ __('booking::booking.labels.total_duration') }}</span>
                <span>{{ collect($items)->sum('duration') }} {{ __('booking::booking.minutes') }}</span>
            </div>
        </div>

        {{-- Clear All --}}
        <button
            type="button"
            wire:click="clearCart"
            wire:confirm="{{ __('booking::booking.messages.confirm_clear_cart') }}"
            class="w-full text-center text-sm text-danger-600 hover:text-danger-700 dark:text-danger-400"
        >
            {{ __('booking::booking.actions.clear_all') }}
        </button>
    @else
        {{-- Empty State --}}
        <div class="rounded-lg border-2 border-dashed border-gray-300 p-6 text-center dark:border-gray-600">
            <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                {{ __('booking::booking.messages.cart_empty') }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ __('booking::booking.messages.select_slots_hint') }}
            </p>
        </div>
    @endif
</div>
