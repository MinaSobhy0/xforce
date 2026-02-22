@props([
    'items' => [],
])

@php
    $totalDuration = collect($items)->sum('duration');
    $itemCount = count($items);
@endphp

<div class="fixed bottom-0 left-0 right-0 z-[100] border-t border-gray-200 bg-white px-6 py-4 shadow-[0_-8px_20px_-4px_rgba(0,0,0,0.15)] dark:border-gray-700 dark:bg-gray-800">
    <div class="flex items-center justify-between gap-4">
        {{-- Left: Selected items summary --}}
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-100 text-primary-600 dark:bg-primary-900/50 dark:text-primary-400">
                    <span class="text-lg font-bold">{{ $itemCount }}</span>
                </span>
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ trans_choice('booking::booking.labels.appointments_selected', $itemCount, ['count' => $itemCount]) }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $totalDuration }} {{ __('booking::booking.minutes') }} {{ __('booking::booking.labels.total_label') }}
                    </p>
                </div>
            </div>

            {{-- Selected items pills --}}
            <div class="hidden sm:flex items-center gap-2 overflow-x-auto max-w-md">
                @foreach(array_slice($items, 0, 3) as $item)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 whitespace-nowrap dark:bg-gray-700 dark:text-gray-300">
                        <span>{{ \Carbon\Carbon::parse($item['date'])->format('M d') }}</span>
                        <span class="text-gray-400">•</span>
                        <span>{{ $item['start_time'] }}</span>
                        @if($item['practitioner_name'])
                            <span class="text-gray-400">•</span>
                            <span class="truncate max-w-[80px]">{{ $item['practitioner_name'] }}</span>
                        @endif
                    </span>
                @endforeach
                @if($itemCount > 3)
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        +{{ $itemCount - 3 }} {{ __('booking::booking.labels.more') }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Right: Actions --}}
        <div class="flex items-center gap-3">
            <button
                type="button"
                wire:click="clearCart"
                wire:confirm="{{ __('booking::booking.messages.confirm_clear_cart') }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
            >
                <x-heroicon-o-trash class="h-4 w-4" />
                <span class="hidden sm:inline">{{ __('booking::booking.actions.clear_all') }}</span>
            </button>

            <button
                type="button"
                wire:click="createBookings"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-75"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50"
            >
                <x-heroicon-o-check class="h-5 w-5" />
                {{ __('booking::booking.actions.confirm_booking') }}
                <span wire:loading wire:target="createBookings" class="ml-1">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
            </button>
        </div>
    </div>
</div>
