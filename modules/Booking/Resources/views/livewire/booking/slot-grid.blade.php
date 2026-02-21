<div class="space-y-4">
    {{-- Group By Toggle --}}
    <div class="flex items-center justify-between">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('booking::booking.labels.available_slots') }}
        </h4>
        <div class="flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('booking::booking.labels.group_by') }}:</span>
            <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700">
                <button
                    type="button"
                    wire:click="setGroupBy('time')"
                    @class([
                        'px-3 py-1.5 text-xs font-medium rounded-l-lg transition-colors',
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
    </div>

    {{-- Time-based Grid View --}}
    @if($groupBy === 'time')
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach($slots as $index => $slot)
                <div
                    class="group relative rounded-lg border border-gray-200 bg-white p-4 transition-all hover:border-primary-400 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-500"
                >
                    {{-- Time Header --}}
                    <div class="mb-3 flex items-center justify-between">
                        <span class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ $slot['start_time'] }}
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            - {{ $slot['end_time'] }}
                        </span>
                    </div>

                    {{-- Service Name --}}
                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ $slot['service_name'] ?? 'Service' }}
                    </p>

                    {{-- Duration --}}
                    <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                        <x-heroicon-o-clock class="inline h-3 w-3" />
                        {{ $slot['duration'] ?? 30 }} {{ __('booking::booking.minutes') }}
                    </p>

                    {{-- Available Practitioners --}}
                    @if(!empty($slot['available_practitioners']))
                        <div class="mb-3">
                            <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">
                                {{ __('booking::booking.labels.practitioner') }}
                            </label>
                            <select
                                wire:change="selectPractitioner({{ $index }}, $event.target.value)"
                                class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                            >
                                @foreach($slot['available_practitioners'] as $practitioner)
                                    <option
                                        value="{{ $practitioner['id'] }}"
                                        {{ isset($selectedPractitioners[$index]) && $selectedPractitioners[$index] === $practitioner['id'] ? 'selected' : '' }}
                                    >
                                        {{ $practitioner['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Room & Equipment --}}
                    <div class="mb-3 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                        @if($slot['room_name'])
                            <p class="flex items-center gap-1">
                                <x-heroicon-o-building-office class="h-3 w-3" />
                                {{ $slot['room_name'] }}
                            </p>
                        @endif
                        @if($slot['equipment_name'])
                            <p class="flex items-center gap-1">
                                <x-heroicon-o-wrench class="h-3 w-3" />
                                {{ $slot['equipment_name'] }}
                            </p>
                        @endif
                    </div>

                    {{-- Select Button --}}
                    <button
                        type="button"
                        wire:click="selectSlot({{ $index }})"
                        class="w-full rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                    >
                        {{ __('booking::booking.actions.select_slot') }}
                    </button>
                </div>
            @endforeach
        </div>
    @else
        {{-- Service-based View --}}
        <div class="space-y-6">
            @foreach($groupedSlots as $serviceId => $serviceSlots)
                @php
                    $firstSlot = $serviceSlots[0] ?? null;
                    $serviceName = $firstSlot['service_name'] ?? 'Unknown Service';
                @endphp
                <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <h5 class="font-semibold text-gray-900 dark:text-white">
                            {{ $serviceName }}
                        </h5>
                    </div>
                    <div class="p-4">
                        <div class="flex flex-wrap gap-2">
                            @foreach($serviceSlots as $slot)
                                @php
                                    $slotIndex = array_search($slot, $slots);
                                @endphp
                                <button
                                    type="button"
                                    wire:click="selectSlot({{ $slotIndex }})"
                                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-all hover:border-primary-400 hover:bg-primary-50 hover:text-primary-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:border-primary-500 dark:hover:bg-primary-900/20 dark:hover:text-primary-400"
                                >
                                    <span class="font-bold">{{ $slot['start_time'] }}</span>
                                    <span class="text-gray-400">-</span>
                                    <span>{{ $slot['end_time'] }}</span>

                                    @if(!empty($slot['available_practitioners']))
                                        <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs dark:bg-gray-600">
                                            {{ count($slot['available_practitioners']) }}
                                            <x-heroicon-o-user class="inline h-3 w-3" />
                                        </span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Empty State --}}
    @if(empty($slots))
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

    {{-- Legend --}}
    @if(!empty($slots))
        <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
            <span class="flex items-center gap-1">
                <x-heroicon-o-user class="h-3 w-3" />
                {{ __('booking::booking.labels.practitioners_available') }}
            </span>
            <span class="flex items-center gap-1">
                <x-heroicon-o-building-office class="h-3 w-3" />
                {{ __('booking::booking.labels.room_assigned') }}
            </span>
            <span class="flex items-center gap-1">
                <x-heroicon-o-wrench class="h-3 w-3" />
                {{ __('booking::booking.labels.equipment_assigned') }}
            </span>
        </div>
    @endif
</div>
