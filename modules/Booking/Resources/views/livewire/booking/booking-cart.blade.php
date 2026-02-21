<div class="space-y-4">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h4 class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
            <x-heroicon-o-shopping-cart class="h-5 w-5" />
            {{ __('booking::booking.labels.selected_slots') }}
            @if($itemCount > 0)
                <span class="inline-flex items-center justify-center rounded-full bg-primary-600 px-2 py-0.5 text-xs font-bold text-white">
                    {{ $itemCount }}
                </span>
            @endif
        </h4>

        @if($itemCount > 0)
            <button
                type="button"
                wire:click="clear"
                wire:confirm="{{ __('booking::booking.messages.confirm_clear_cart') }}"
                class="text-sm text-danger-600 hover:text-danger-700 dark:text-danger-400 dark:hover:text-danger-300"
            >
                {{ __('booking::booking.actions.clear_all') }}
            </button>
        @endif
    </div>

    {{-- Items List --}}
    @if($itemCount > 0)
        <div class="space-y-3">
            @foreach($items as $index => $item)
                <div class="flex items-start gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
                    {{-- Time Badge --}}
                    <div class="flex flex-col items-center rounded-md bg-primary-100 px-3 py-2 dark:bg-primary-900/30">
                        <span class="text-lg font-bold text-primary-700 dark:text-primary-300">
                            {{ \Carbon\Carbon::parse($item['start_time'])->format('H:i') }}
                        </span>
                        <span class="text-xs text-primary-600 dark:text-primary-400">
                            {{ \Carbon\Carbon::parse($item['date'])->format('M d') }}
                        </span>
                    </div>

                    {{-- Details --}}
                    <div class="flex-1 min-w-0">
                        <h5 class="truncate font-medium text-gray-900 dark:text-white">
                            {{ $item['service_name'] }}
                        </h5>
                        <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                            <span class="flex items-center gap-1">
                                <x-heroicon-o-clock class="h-3 w-3" />
                                {{ $item['duration'] }} {{ __('booking::booking.minutes') }}
                            </span>
                            @if($item['practitioner_name'])
                                <span class="flex items-center gap-1">
                                    <x-heroicon-o-user class="h-3 w-3" />
                                    {{ $item['practitioner_name'] }}
                                </span>
                            @endif
                            @if($item['room_name'])
                                <span class="flex items-center gap-1">
                                    <x-heroicon-o-building-office class="h-3 w-3" />
                                    {{ $item['room_name'] }}
                                </span>
                            @endif
                        </div>

                        @if(isset($item['from_package']) && $item['from_package'])
                            <span class="mt-1 inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">
                                <x-heroicon-o-gift class="mr-1 h-3 w-3" />
                                {{ __('booking::booking.labels.from_package') }}
                            </span>
                        @endif
                    </div>

                    {{-- Remove Button --}}
                    <button
                        type="button"
                        wire:click="removeItem({{ $index }})"
                        class="flex-shrink-0 rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-danger-600 dark:hover:bg-gray-700 dark:hover:text-danger-400"
                        title="{{ __('booking::booking.actions.remove') }}"
                    >
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>
            @endforeach
        </div>

        {{-- Summary --}}
        <div class="rounded-lg border border-primary-200 bg-primary-50 p-3 dark:border-primary-700 dark:bg-primary-900/20">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600 dark:text-gray-300">
                    {{ __('booking::booking.labels.total_duration') }}
                </span>
                <span class="text-lg font-bold text-primary-600 dark:text-primary-400">
                    {{ $totalDuration }} {{ __('booking::booking.minutes') }}
                </span>
            </div>
        </div>
    @else
        {{-- Empty State --}}
        <div class="rounded-lg border-2 border-dashed border-gray-300 p-6 text-center dark:border-gray-600">
            <x-heroicon-o-shopping-cart class="mx-auto h-8 w-8 text-gray-400" />
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('booking::booking.messages.cart_empty') }}
            </p>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                {{ __('booking::booking.messages.select_slots_hint') }}
            </p>
        </div>
    @endif
</div>
