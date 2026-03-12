@props([
    'slots' => [],
    'selectedSlots' => [],
])

@php
    $slotsByDate = collect($slots)->groupBy('date')->sortKeys();
    $dates = $slotsByDate->keys()->toArray();
    $firstDate = $dates[0] ?? null;
@endphp

<div class="space-y-4" x-data="{
    view: 'cards',
    selectedDate: '{{ $firstDate }}',
    dates: {{ json_encode($dates) }}
}">
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

    @if($slotsByDate->isNotEmpty())
        {{-- Day Pills --}}
        <div class="flex flex-wrap gap-2">
            @foreach($slotsByDate as $date => $dateSlots)
                @php
                    $carbon = \Carbon\Carbon::parse($date);
                    $dayName = $carbon->format('D');
                    $dayNum = $carbon->format('d');
                    $monthName = $carbon->format('M');
                    $slotsCount = count($dateSlots);
                    $hasSelectedSlot = collect($dateSlots)->contains(function ($slot) use ($selectedSlots) {
                        $slotKey = $slot['date'] . '_' . $slot['start_time'] . '_' . ($slot['service_id'] ?? '');
                        return isset($selectedSlots[$slotKey]);
                    });
                @endphp
                <button
                    type="button"
                    @click="selectedDate = '{{ $date }}'"
                    class="relative flex flex-col items-center px-4 py-2 rounded-xl border-2 transition-all cursor-pointer min-w-[70px]"
                    :style="selectedDate === '{{ $date }}'
                        ? 'background-color: #3b82f6; border-color: #3b82f6; color: white;'
                        : '{{ $hasSelectedSlot ? "background-color: #f0fdf4; border-color: #22c55e;" : "background-color: #f9fafb; border-color: #e5e7eb;" }}'"
                >
                    @if($hasSelectedSlot)
                        <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full" style="background-color: #22c55e;">
                            <x-heroicon-s-check class="h-2.5 w-2.5 text-white" />
                        </span>
                    @endif
                    <span class="text-[10px] font-medium uppercase" :style="selectedDate === '{{ $date }}' ? 'opacity: 0.8;' : 'color: #6b7280;'">{{ $dayName }}</span>
                    <span class="text-lg font-bold leading-tight">{{ $dayNum }}</span>
                    <span class="text-[10px]" :style="selectedDate === '{{ $date }}' ? 'opacity: 0.8;' : 'color: #6b7280;'">{{ $monthName }}</span>
                    <span
                        class="mt-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold"
                        :style="selectedDate === '{{ $date }}'
                            ? 'background-color: rgba(255,255,255,0.2); color: white;'
                            : 'background-color: #e5e7eb; color: #374151;'"
                    >{{ $slotsCount }}</span>
                </button>
            @endforeach
        </div>

        {{-- Slots for Selected Day --}}
        @foreach($slotsByDate as $date => $dateSlots)
            <div x-show="selectedDate === '{{ $date }}'" x-cloak>
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
                <div x-show="view === 'compact'" class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10">
                    @foreach($dateSlots as $slot)
                        @php
                            $slotKey = $slot['date'] . '_' . $slot['start_time'] . '_' . ($slot['service_id'] ?? '');
                            $isSelected = isset($selectedSlots[$slotKey]);
                            $practitioners = collect($slot['available_practitioners'] ?? [])
                                ->filter(fn($p) => !empty($p['name']) && $p['name'] !== 'Unknown' && !empty($p['id']))
                                ->values()
                                ->all();
                            $defaultPractitioner = collect($practitioners)->firstWhere('is_recommended', true) ?? ($practitioners[0] ?? null);
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
                                'from_package' => $slot['from_package'] ?? null,
                                'new_package_id' => $slot['new_package_id'] ?? null,
                                'treatment_plan_item_id' => $slot['treatment_plan_item_id'] ?? null,
                            ];
                        @endphp

                        <button
                            type="button"
                            wire:key="compact-slot-{{ $slotKey }}"
                            wire:click="selectSlot({{ json_encode($slotData) }})"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-wait"
                            class="group relative flex flex-col items-center rounded-lg border-2 p-2 text-center transition-all active:scale-95"
                            style="{{ $isSelected ? 'background-color: #f0fdf4; border-color: #22c55e; box-shadow: 0 0 0 2px #bbf7d0;' : 'background-color: white; border-color: #e5e7eb;' }}"
                        >
                            @if($isSelected)
                                <span class="absolute -top-1.5 -right-1.5 flex h-4 w-4 items-center justify-center rounded-full" style="background-color: #22c55e; color: white;">
                                    <x-heroicon-s-check class="h-2.5 w-2.5" />
                                </span>
                            @endif

                            <span class="text-base font-bold" style="color: {{ $isSelected ? '#15803d' : '#111827' }};">
                                {{ $slot['start_time'] }}
                            </span>

                            @if(isset($slot['service_name']))
                                <span class="mt-0.5 truncate text-[10px] text-gray-500 dark:text-gray-400 max-w-full px-1" title="{{ $slot['service_name'] }}">
                                    {{ \Illuminate\Support\Str::limit($slot['service_name'], 10) }}
                                </span>
                            @endif

                            @if(count($practitioners) > 0)
                                <span class="mt-0.5 flex items-center gap-0.5 text-[10px] text-gray-400 dark:text-gray-500">
                                    <x-heroicon-o-user class="h-2.5 w-2.5" />
                                    {{ count($practitioners) }}
                                </span>
                            @endif

                            @unless($isSelected)
                                <span class="absolute inset-x-0 bottom-0 h-0.5 rounded-b-lg bg-primary-500 opacity-0 transition-opacity group-hover:opacity-100"></span>
                            @endunless
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- Legend --}}
        <div class="flex flex-wrap items-center gap-4 border-t border-gray-200 pt-3 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full" style="background-color: #3b82f6;"></span>
                {{ __('booking::booking.labels.selected_day') }}
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full" style="background-color: #22c55e;"></span>
                {{ __('booking::booking.labels.has_booking') }}
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
