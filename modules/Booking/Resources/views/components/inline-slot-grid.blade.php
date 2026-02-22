@props([
    'slots' => [],
    'selectedSlots' => [],
])

<div class="space-y-4" x-data="{ view: 'cards' }">
    {{-- Header --}}
    <div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-700">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('booking::booking.labels.available_slots') }}
            <span class="ml-2 rounded-full bg-primary-100 px-2 py-0.5 text-xs font-semibold text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                {{ count($slots) }}
            </span>
            @if(count($selectedSlots) > 0)
                <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-semibold" style="background-color: #dcfce7; color: #15803d;">
                    {{ count($selectedSlots) }} {{ __('booking::booking.labels.selected') }}
                </span>
            @endif
        </h4>

        {{-- View Toggle --}}
        <div class="flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('booking::booking.labels.view') }}:</span>
            <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700">
                <button
                    type="button"
                    @click="view = 'cards'"
                    :class="view === 'cards'
                        ? 'bg-primary-600 text-white'
                        : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'"
                    class="px-3 py-1.5 text-xs font-medium rounded-l-lg transition-colors"
                >
                    <x-heroicon-o-squares-2x2 class="w-4 h-4" />
                </button>
                <button
                    type="button"
                    @click="view = 'compact'"
                    :class="view === 'compact'
                        ? 'bg-primary-600 text-white'
                        : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'"
                    class="px-3 py-1.5 text-xs font-medium rounded-r-lg transition-colors"
                >
                    <x-heroicon-o-list-bullet class="w-4 h-4" />
                </button>
            </div>
        </div>
    </div>

    @php
        $slotsByDate = collect($slots)->groupBy('date')->sortKeys();
    @endphp

    @if($slotsByDate->isNotEmpty())
        <div class="space-y-6">
            @foreach($slotsByDate as $date => $dateSlots)
                <div>
                    {{-- Date Header --}}
                    <div class="mb-4 flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/50">
                            <x-heroicon-o-calendar class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div>
                            <span class="font-semibold text-gray-900 dark:text-white">
                                {{ \Carbon\Carbon::parse($date)->format('l') }}
                            </span>
                            <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">
                                {{ \Carbon\Carbon::parse($date)->format('M d, Y') }}
                            </span>
                        </div>
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                            {{ count($dateSlots) }} {{ __('booking::booking.labels.slots') }}
                        </span>
                    </div>

                    {{-- Cards View --}}
                    <div x-show="view === 'cards'" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($dateSlots as $slot)
                            @php
                                $globalIndex = array_search($slot, $slots);
                            @endphp
                            @include('booking::components.slot-card', [
                                'slot' => $slot,
                                'index' => $globalIndex,
                                'selectedSlots' => $selectedSlots,
                            ])
                        @endforeach
                    </div>

                    {{-- Compact View --}}
                    <div x-show="view === 'compact'" x-cloak class="grid grid-cols-2 gap-2 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-8">
                        @foreach($dateSlots as $slot)
                            @php
                                $slotKey = $slot['date'] . '_' . $slot['start_time'] . '_' . ($slot['service_id'] ?? '');
                                $isSelected = isset($selectedSlots[$slotKey]);
                                $practitioners = collect($slot['available_practitioners'] ?? [])
                                    ->filter(fn($p) => !empty($p['name']) && $p['name'] !== 'Unknown' && !empty($p['id']))
                                    ->values()
                                    ->all();
                                $defaultPractitioner = collect($practitioners)->firstWhere('is_recommended', true) ?? ($practitioners[0] ?? null);
                                // Only pass essential data
                                $slotData = [
                                    'service_id' => $slot['service_id'] ?? null,
                                    'service_name' => $slot['service_name'] ?? null,
                                    'date' => $slot['date'],
                                    'start_time' => $slot['start_time'],
                                    'end_time' => $slot['end_time'],
                                    'duration' => $slot['duration'] ?? 30,
                                    'practitioner_id' => $defaultPractitioner['id'] ?? null,
                                    'practitioner_name' => $defaultPractitioner['name'] ?? '',
                                    'room_id' => $slot['room_id'] ?? null,
                                    'room_name' => $slot['room_name'] ?? null,
                                    'equipment_id' => $slot['equipment_id'] ?? null,
                                    'equipment_name' => $slot['equipment_name'] ?? null,
                                ];
                            @endphp

                            <button
                                type="button"
                                wire:click="selectSlot({{ json_encode($slotData) }})"
                                class="group relative flex flex-col items-center rounded-lg border-2 p-3 text-center transition-all active:scale-95"
                                style="{{ $isSelected ? 'background-color: #f0fdf4; border-color: #22c55e; box-shadow: 0 0 0 2px #bbf7d0;' : 'background-color: white; border-color: #e5e7eb;' }}"
                            >
                                @if($isSelected)
                                    <span class="absolute -top-1.5 -right-1.5 flex h-5 w-5 items-center justify-center rounded-full" style="background-color: #22c55e; color: white;">
                                        <x-heroicon-s-check class="h-3 w-3" />
                                    </span>
                                @endif

                                <span class="text-lg font-bold" style="color: {{ $isSelected ? '#15803d' : '#111827' }};">
                                    {{ $slot['start_time'] }}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $slot['end_time'] }}
                                </span>

                                @if(isset($slot['service_name']))
                                    <span class="mt-1 truncate text-xs text-gray-600 dark:text-gray-300 max-w-full px-1" title="{{ $slot['service_name'] }}">
                                        {{ \Illuminate\Support\Str::limit($slot['service_name'], 12) }}
                                    </span>
                                @endif

                                @if(count($practitioners) > 0)
                                    <span class="mt-1 flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500">
                                        <x-heroicon-o-user class="h-3 w-3" />
                                        {{ count($practitioners) }}
                                    </span>
                                @endif

                                @unless($isSelected)
                                    <span class="absolute inset-x-0 bottom-0 h-1 rounded-b-lg bg-primary-500 opacity-0 transition-opacity group-hover:opacity-100"></span>
                                @endunless
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Legend --}}
        <div class="flex flex-wrap items-center gap-4 border-t border-gray-200 pt-3 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full" style="background-color: #22c55e;"></span>
                {{ __('booking::booking.labels.available') }}
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full" style="background-color: #eab308;"></span>
                {{ __('booking::booking.labels.busy_soon_label') }}
            </span>
            <span class="flex items-center gap-1.5">
                <x-heroicon-s-star class="w-3 h-3" style="color: #eab308;" />
                {{ __('booking::booking.labels.recommended') }}
            </span>
            <span class="flex items-center gap-1.5">
                <x-heroicon-s-check-circle class="w-3 h-3" style="color: #22c55e;" />
                {{ __('booking::booking.labels.selected') }}
            </span>
        </div>
    @else
        {{-- Empty State --}}
        <div class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 py-12 dark:border-gray-600">
            <x-heroicon-o-calendar class="h-12 w-12 text-gray-400" />
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                {{ __('booking::booking.messages.no_slots') }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('booking::booking.messages.generate_slots_hint') }}
            </p>
        </div>
    @endif
</div>
