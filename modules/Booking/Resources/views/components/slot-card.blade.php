@props([
    'slot',
    'index',
    'selectedSlots' => [],
])

@php
    // Filter out practitioners without valid names
    // Note: null ID is valid for "Any Available Doctor" option
    $practitioners = collect($slot['available_practitioners'] ?? [])
        ->filter(fn($p) => !empty($p['name']) && $p['name'] !== 'Unknown' && (($p['is_any_available'] ?? false) || !empty($p['id'])))
        ->values()
        ->all();
    $isLimited = count($practitioners) === 1;
    $room = $slot['room'] ?? null;
    $equipment = $slot['equipment'] ?? null;

    // Check if this slot is selected
    $slotKey = $slot['date'] . '_' . $slot['start_time'] . '_' . ($slot['service_id'] ?? '');
    $isSelected = isset($selectedSlots[$slotKey]);
    $selectedPractitionerId = $isSelected ? (string) ($selectedSlots[$slotKey]['practitioner_id'] ?? '') : '';
@endphp

<div
    wire:key="slot-card-{{ $slotKey }}-{{ $selectedPractitionerId }}"
    class="slot-card relative rounded-lg border-2 p-4 transition-all"
    style="{{ $isSelected ? 'background-color: #f0fdf4; border-color: #22c55e; box-shadow: 0 0 0 2px #bbf7d0;' : ($isLimited ? 'background-color: #fefce8; border-color: #fde047;' : 'background-color: white; border-color: #e5e7eb;') }}"
>
    {{-- Selected Indicator --}}
    @if($isSelected)
        <div class="absolute -top-2 -right-2 z-10">
            <span class="flex h-6 w-6 items-center justify-center rounded-full shadow-md" style="background-color: #22c55e; color: white;">
                <x-heroicon-s-check class="h-4 w-4" />
            </span>
        </div>
    @endif

    {{-- Header: Time & Duration --}}
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
            <span style="color: {{ $isSelected ? '#22c55e' : '#9ca3af' }};">
                <x-heroicon-o-clock class="w-4 h-4" />
            </span>
            <span class="text-lg font-bold" style="color: {{ $isSelected ? '#15803d' : '#111827' }};">
                {{ \Carbon\Carbon::parse($slot['start_time'])->format('g:i A') }}
            </span>
            <span class="text-gray-400">-</span>
            <span class="text-gray-600">
                {{ \Carbon\Carbon::parse($slot['end_time'])->format('g:i A') }}
            </span>
        </div>
        <span
            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
            style="{{ $isSelected ? 'background-color: #dcfce7; color: #15803d;' : 'background-color: #f3f4f6; color: #4b5563;' }}"
        >
            {{ $slot['duration'] ?? 30 }} {{ __('booking::booking.minutes') }}
        </span>
    </div>

    {{-- Service Name (if provided) --}}
    @if(isset($slot['service_name']))
        <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $slot['service_name'] }}
        </p>
    @endif

    {{-- Available Practitioners - Click to select slot --}}
    @if(!empty($practitioners))
        <div class="mb-3">
            <label class="mb-2 block text-xs font-medium text-gray-500 dark:text-gray-400">
                @if($isSelected)
                    {{ __('booking::booking.labels.click_to_change') }}:
                @else
                    {{ __('booking::booking.labels.click_practitioner') }}:
                @endif
            </label>
            <div class="flex flex-wrap gap-2">
                @foreach($practitioners as $practitioner)
                    @php
                        $practitionerId = (string) ($practitioner['id'] ?? '');
                        $isAnyAvailable = $practitioner['is_any_available'] ?? false;
                        $isThisPractitionerSelected = $isSelected && $selectedPractitionerId === $practitionerId;
                        $practitionerStatus = $practitioner['status'] ?? 'available';

                        // Only pass essential data to reduce payload size
                        $slotData = [
                            'service_id' => $slot['service_id'] ?? null,
                            'service_name' => $slot['service_name'] ?? null,
                            'date' => $slot['date'],
                            'start_time' => $slot['start_time'],
                            'end_time' => $slot['end_time'],
                            'duration' => $slot['duration'] ?? 30,
                            'practitioner_id' => $practitioner['id'],
                            'practitioner_name' => $practitioner['name'] ?? '',
                            'room_id' => $slot['room_id'] ?? null,
                            'room_name' => $slot['room_name'] ?? null,
                            'equipment_id' => $slot['equipment_id'] ?? null,
                            'equipment_name' => $slot['equipment_name'] ?? null,
                            'from_package' => $slot['from_package'] ?? null,
                            'new_package_id' => $slot['new_package_id'] ?? null,
                            'treatment_plan_item_id' => $slot['treatment_plan_item_id'] ?? null,
                        ];

                        // Special styling for "Any Available Doctor" option
                        if ($isAnyAvailable) {
                            $buttonStyle = $isThisPractitionerSelected
                                ? 'background-color: #8b5cf6; color: white; border-color: #7c3aed;'
                                : ($isSelected ? 'background-color: #f3f4f6; color: #6b7280; border-color: #e5e7eb;' : 'background-color: #f5f3ff; color: #6d28d9; border-color: #c4b5fd; border-style: dashed;');
                        } else {
                            $buttonStyle = $isThisPractitionerSelected
                                ? 'background-color: #22c55e; color: white; border-color: #16a34a;'
                                : ($isSelected ? 'background-color: #f3f4f6; color: #6b7280; border-color: #e5e7eb;' : 'background-color: white; color: #374151; border-color: #e5e7eb;');
                        }
                    @endphp
                    <button
                        type="button"
                        wire:click="selectSlot({{ json_encode($slotData) }})"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50 cursor-wait"
                        class="practitioner-chip inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition-all cursor-pointer border-2"
                        style="{{ $buttonStyle }}"
                    >
                        {{-- Status Dot / Users Icon for Any Available --}}
                        @if($isAnyAvailable)
                            <x-heroicon-o-user-group
                                class="w-5 h-5 flex-shrink-0"
                                style="color: {{ $isThisPractitionerSelected ? '#ffffff' : '#8b5cf6' }};"
                            />
                        @else
                            <span
                                class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                                style="background-color: {{ $isThisPractitionerSelected ? '#ffffff' : ($practitionerStatus === 'busy_soon' ? '#eab308' : '#22c55e') }};"
                            ></span>
                        @endif

                        {{-- Avatar (not shown for Any Available) --}}
                        @if(!$isAnyAvailable)
                            @if(!empty($practitioner['avatar']))
                                <img src="{{ $practitioner['avatar'] }}" alt="" class="w-6 h-6 rounded-full object-cover" />
                            @else
                                <span
                                    class="w-6 h-6 rounded-full flex items-center justify-center"
                                    style="background-color: {{ $isThisPractitionerSelected ? '#4ade80' : '#e5e7eb' }};"
                                >
                                    <x-heroicon-o-user
                                        class="w-4 h-4"
                                        style="color: {{ $isThisPractitionerSelected ? '#ffffff' : '#6b7280' }};"
                                    />
                                </span>
                            @endif
                        @endif

                        {{-- Name --}}
                        <span>{{ $practitioner['name'] }}</span>

                        {{-- Recommended Star --}}
                        @if(($practitioner['is_recommended'] ?? false) && !$isAnyAvailable)
                            <x-heroicon-s-star
                                class="w-4 h-4 flex-shrink-0"
                                style="color: {{ $isThisPractitionerSelected ? '#fde047' : '#eab308' }};"
                                title="{{ __('booking::booking.labels.recommended') }}"
                            />
                        @endif

                        {{-- Selected checkmark --}}
                        @if($isThisPractitionerSelected)
                            <x-heroicon-s-check-circle class="w-5 h-5 flex-shrink-0" style="color: white;" />
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Room & Equipment Resources --}}
    <div class="space-y-1.5 text-sm text-gray-500 dark:text-gray-400">
        @if($room)
            <div class="flex items-center gap-2">
                <x-heroicon-o-building-office class="w-4 h-4 flex-shrink-0" />
                <span>{{ $room['name'] }}</span>
                @if($room['is_primary'] ?? false)
                    <span class="inline-flex items-center rounded-full bg-green-100 px-1.5 py-0.5 text-xs text-green-700 dark:bg-green-900/30 dark:text-green-400">
                        {{ __('booking::booking.labels.primary') }}
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-yellow-100 px-1.5 py-0.5 text-xs text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                        {{ __('booking::booking.labels.backup') }}
                    </span>
                @endif
            </div>
        @elseif($slot['room_name'] ?? null)
            <div class="flex items-center gap-2">
                <x-heroicon-o-building-office class="w-4 h-4 flex-shrink-0" />
                <span>{{ $slot['room_name'] }}</span>
            </div>
        @endif

        @if($equipment)
            <div class="flex items-center gap-2">
                <x-heroicon-o-wrench class="w-4 h-4 flex-shrink-0" />
                <span>{{ $equipment['name'] }}</span>
            </div>
        @elseif($slot['equipment_name'] ?? null)
            <div class="flex items-center gap-2">
                <x-heroicon-o-wrench class="w-4 h-4 flex-shrink-0" />
                <span>{{ $slot['equipment_name'] }}</span>
            </div>
        @endif
    </div>

    {{-- From Package Badge --}}
    @if($slot['from_package'] ?? null)
        <div class="mt-3">
            <span class="inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">
                <x-heroicon-o-gift class="w-3 h-3 mr-1" />
                {{ __('booking::booking.labels.from_package') }}
            </span>
        </div>
    @endif
</div>
