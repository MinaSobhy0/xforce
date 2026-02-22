<div class="space-y-4">
    {{-- Header with Controls --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('booking::booking.labels.available_slots') }}
            @if(count($slots) > 0)
                <span class="ml-2 rounded-full bg-primary-100 px-2 py-0.5 text-xs font-semibold text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                    {{ count($slots) }}
                </span>
            @endif
        </h4>

        <div class="flex items-center gap-4">
            {{-- Group By Toggle --}}
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('booking::booking.labels.group_by') }}:</span>
                <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700">
                    <button
                        type="button"
                        wire:click="setGroupBy('date')"
                        @class([
                            'px-3 py-1.5 text-xs font-medium rounded-l-lg transition-colors',
                            'bg-primary-600 text-white' => $groupBy === 'date',
                            'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' => $groupBy !== 'date',
                        ])
                    >
                        {{ __('booking::booking.labels.date') }}
                    </button>
                    <button
                        type="button"
                        wire:click="setGroupBy('time')"
                        @class([
                            'px-3 py-1.5 text-xs font-medium transition-colors',
                            'bg-primary-600 text-white' => $groupBy === 'time',
                            'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' => $groupBy !== 'time',
                        ])
                    >
                        {{ __('booking::booking.labels.time') }}
                    </button>
                    <button
                        type="button"
                        wire:click="setGroupBy('service')"
                        @class([
                            'px-3 py-1.5 text-xs font-medium rounded-r-lg transition-colors',
                            'bg-primary-600 text-white' => $groupBy === 'service',
                            'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' => $groupBy !== 'service',
                        ])
                    >
                        {{ __('booking::booking.labels.service') }}
                    </button>
                </div>
            </div>

            {{-- View Mode Toggle --}}
            <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700">
                <button
                    type="button"
                    wire:click="setViewMode('cards')"
                    @class([
                        'px-2.5 py-1.5 text-xs font-medium rounded-l-lg transition-colors',
                        'bg-primary-600 text-white' => $viewMode === 'cards',
                        'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' => $viewMode !== 'cards',
                    ])
                    title="{{ __('booking::booking.labels.card_view') }}"
                >
                    <x-heroicon-o-squares-2x2 class="w-4 h-4" />
                </button>
                <button
                    type="button"
                    wire:click="setViewMode('compact')"
                    @class([
                        'px-2.5 py-1.5 text-xs font-medium rounded-r-lg transition-colors',
                        'bg-primary-600 text-white' => $viewMode === 'compact',
                        'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' => $viewMode !== 'compact',
                    ])
                    title="{{ __('booking::booking.labels.compact_view') }}"
                >
                    <x-heroicon-o-list-bullet class="w-4 h-4" />
                </button>
            </div>
        </div>
    </div>

    {{-- Slots Display --}}
    @if(!empty($slots))
        @if($groupBy === 'date')
            <div class="space-y-6">
                @foreach($groupedSlots as $date => $dateSlots)
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

                        @if($viewMode === 'cards')
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($dateSlots as $slot)
                                    @php $slotIndex = array_search($slot, $slots); @endphp
                                    @include('booking::components.slot-card', [
                                        'slot' => $slot,
                                        'index' => $slotIndex,
                                        'isSelected' => false,
                                    ])
                                @endforeach
                            </div>
                        @else
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-8">
                                @foreach($dateSlots as $slot)
                                    @php
                                        $practitioners = $slot['available_practitioners'] ?? [];
                                        $defaultPractitioner = collect($practitioners)->firstWhere('is_recommended', true) ?? ($practitioners[0] ?? null);
                                        $slotWithPractitioner = $defaultPractitioner ? array_merge($slot, [
                                            'practitioner_id' => $defaultPractitioner['id'],
                                            'practitioner_name' => $defaultPractitioner['name'],
                                        ]) : $slot;
                                    @endphp
                                    <button
                                        type="button"
                                        wire:click="$dispatch('slot-selected', { slot: {{ json_encode($slotWithPractitioner) }} })"
                                        class="group relative flex flex-col items-center rounded-lg border border-gray-200 bg-white p-3 text-center transition-all hover:border-primary-500 hover:bg-primary-50 hover:shadow-md active:scale-95 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-400 dark:hover:bg-primary-900/20"
                                    >
                                        <span class="text-lg font-bold text-gray-900 dark:text-white">{{ $slot['start_time'] }}</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $slot['end_time'] }}</span>
                                        @if(count($practitioners) > 0)
                                            <span class="mt-1 flex items-center gap-1 text-xs text-gray-400">
                                                <x-heroicon-o-user class="h-3 w-3" />
                                                {{ count($practitioners) }}
                                            </span>
                                        @endif
                                        <span class="absolute inset-x-0 bottom-0 h-1 rounded-b-lg bg-primary-500 opacity-0 transition-opacity group-hover:opacity-100"></span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @elseif($groupBy === 'time')
            <div class="space-y-6">
                @foreach($groupedSlots as $time => $timeSlots)
                    <div>
                        <div class="mb-3 flex items-center gap-2">
                            <x-heroicon-o-clock class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $time }}</span>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                {{ count($timeSlots) }} {{ __('booking::booking.labels.options') }}
                            </span>
                        </div>

                        @if($viewMode === 'cards')
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($timeSlots as $slot)
                                    @php $slotIndex = array_search($slot, $slots); @endphp
                                    @include('booking::components.slot-card', [
                                        'slot' => $slot,
                                        'index' => $slotIndex,
                                        'isSelected' => false,
                                    ])
                                @endforeach
                            </div>
                        @else
                            <div class="flex flex-wrap gap-2">
                                @foreach($timeSlots as $slot)
                                    @php
                                        $practitioners = $slot['available_practitioners'] ?? [];
                                        $defaultPractitioner = collect($practitioners)->firstWhere('is_recommended', true) ?? ($practitioners[0] ?? null);
                                        $slotWithPractitioner = $defaultPractitioner ? array_merge($slot, [
                                            'practitioner_id' => $defaultPractitioner['id'],
                                            'practitioner_name' => $defaultPractitioner['name'],
                                        ]) : $slot;
                                    @endphp
                                    <button
                                        type="button"
                                        wire:click="$dispatch('slot-selected', { slot: {{ json_encode($slotWithPractitioner) }} })"
                                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-all hover:border-primary-400 hover:bg-primary-50 hover:text-primary-700 active:scale-95 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:border-primary-500 dark:hover:bg-primary-900/20 dark:hover:text-primary-400"
                                    >
                                        <span>{{ \Carbon\Carbon::parse($slot['date'])->format('M d') }}</span>
                                        @if(isset($slot['service_name']))
                                            <span class="text-gray-400">|</span>
                                            <span class="text-xs">{{ $slot['service_name'] }}</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            {{-- Service-based grouping --}}
            <div class="space-y-6">
                @foreach($groupedSlots as $serviceId => $serviceSlots)
                    @php
                        $firstSlot = $serviceSlots[0] ?? null;
                        $serviceName = $firstSlot['service_name'] ?? 'Unknown Service';
                    @endphp
                    <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                            <h5 class="font-semibold text-gray-900 dark:text-white">{{ $serviceName }}</h5>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ count($serviceSlots) }} {{ __('booking::booking.labels.slots') }}
                            </span>
                        </div>
                        <div class="p-4">
                            @if($viewMode === 'cards')
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach($serviceSlots as $slot)
                                        @php $slotIndex = array_search($slot, $slots); @endphp
                                        @include('booking::components.slot-card', [
                                            'slot' => $slot,
                                            'index' => $slotIndex,
                                            'isSelected' => false,
                                        ])
                                    @endforeach
                                </div>
                            @else
                                <div class="flex flex-wrap gap-2">
                                    @foreach($serviceSlots as $slot)
                                        @php
                                            $practitioners = $slot['available_practitioners'] ?? [];
                                            $defaultPractitioner = collect($practitioners)->firstWhere('is_recommended', true) ?? ($practitioners[0] ?? null);
                                            $slotWithPractitioner = $defaultPractitioner ? array_merge($slot, [
                                                'practitioner_id' => $defaultPractitioner['id'],
                                                'practitioner_name' => $defaultPractitioner['name'],
                                            ]) : $slot;
                                        @endphp
                                        <button
                                            type="button"
                                            wire:click="$dispatch('slot-selected', { slot: {{ json_encode($slotWithPractitioner) }} })"
                                            class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-all hover:border-primary-400 hover:bg-primary-50 hover:text-primary-700 active:scale-95 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:border-primary-500 dark:hover:bg-primary-900/20 dark:hover:text-primary-400"
                                        >
                                            <span class="font-bold">{{ $slot['start_time'] }}</span>
                                            <span class="text-gray-400">-</span>
                                            <span>{{ $slot['end_time'] }}</span>
                                            <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($slot['date'])->format('M d') }}</span>
                                            @if(count($practitioners) > 0)
                                                <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs dark:bg-gray-600">
                                                    {{ count($practitioners) }} <x-heroicon-o-user class="inline h-3 w-3" />
                                                </span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Legend --}}
        <div class="flex flex-wrap items-center gap-4 border-t border-gray-200 pt-3 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                {{ __('booking::booking.labels.available') }}
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
                {{ __('booking::booking.labels.busy_soon_label') }}
            </span>
            <span class="flex items-center gap-1.5">
                <x-heroicon-s-star class="w-3 h-3 text-yellow-500" />
                {{ __('booking::booking.labels.recommended') }}
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
