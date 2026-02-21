<div class="space-y-4">
    {{-- Header --}}
    <div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-700">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('booking::booking.labels.available_slots') }}
            <span class="ml-2 rounded-full bg-primary-100 px-2 py-0.5 text-xs font-semibold text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                {{ count($slots) }}
            </span>
        </h4>
    </div>

    @php
        // Group slots by date
        $slotsByDate = collect($slots)->groupBy('date')->sortKeys();
    @endphp

    @if($slotsByDate->isNotEmpty())
        <div class="space-y-6">
            @foreach($slotsByDate as $date => $dateSlots)
                <div>
                    {{-- Date Header --}}
                    <div class="mb-3 flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900">
                            <svg class="h-4 w-4 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <span class="font-semibold text-gray-900 dark:text-white">
                                {{ \Carbon\Carbon::parse($date)->format('l') }}
                            </span>
                            <span class="ml-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ \Carbon\Carbon::parse($date)->format('M d, Y') }}
                            </span>
                        </div>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                            {{ count($dateSlots) }} {{ __('booking::booking.labels.available_slots') }}
                        </span>
                    </div>

                    {{-- Slots Grid for this date --}}
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                        @foreach($dateSlots as $index => $slot)
                            <button
                                type="button"
                                wire:click="$dispatch('slot-selected', { slot: {{ json_encode($slot) }} })"
                                class="group relative flex flex-col items-center rounded-lg border border-gray-200 bg-white p-3 text-center transition-all hover:border-primary-500 hover:bg-primary-50 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-400 dark:hover:bg-primary-900/20"
                            >
                                {{-- Time --}}
                                <span class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ $slot['start_time'] }}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $slot['end_time'] }}
                                </span>

                                {{-- Service (if multiple) --}}
                                @if(isset($slot['service_name']))
                                    <span class="mt-1 truncate text-xs text-gray-600 dark:text-gray-300 max-w-full px-1" title="{{ $slot['service_name'] }}">
                                        {{ \Illuminate\Support\Str::limit($slot['service_name'], 15) }}
                                    </span>
                                @endif

                                {{-- Practitioners Count --}}
                                @if(!empty($slot['available_practitioners']))
                                    @php
                                        $practitioners = $slot['available_practitioners'];
                                        $practitionerCount = is_array($practitioners) ? count($practitioners) : $practitioners->count();
                                    @endphp
                                    <span class="mt-1 flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        {{ $practitionerCount }}
                                    </span>
                                @endif

                                {{-- Hover indicator --}}
                                <span class="absolute inset-x-0 bottom-0 h-1 rounded-b-lg bg-primary-500 opacity-0 transition-opacity group-hover:opacity-100"></span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Legend --}}
        <div class="flex items-center gap-4 border-t border-gray-200 pt-3 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
            <span class="flex items-center gap-1">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ __('booking::booking.labels.click_to_select') }}
            </span>
            <span class="flex items-center gap-1">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                {{ __('booking::booking.labels.practitioners_available') }}
            </span>
        </div>
    @endif
</div>
