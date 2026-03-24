@props([
    'slots' => [],
    'selectedSlots' => [],
])

@php
    $slotsByDate = collect($slots)->groupBy('date')->sortKeys();
    $dates = $slotsByDate->keys()->toArray();
    $firstDate = $dates[0] ?? null;

    // Pre-process slots data for Alpine (minimize what we send)
    $slotsDataForAlpine = [];
    foreach ($slotsByDate as $date => $dateSlots) {
        $slotsDataForAlpine[$date] = $dateSlots->map(function ($slot) {
            return [
                'key' => $slot['date'] . '_' . $slot['start_time'] . '_' . ($slot['service_id'] ?? ''),
                'service_id' => $slot['service_id'] ?? null,
                'service_name' => $slot['service_name'] ?? null,
                'date' => $slot['date'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'duration' => $slot['duration'] ?? 30,
                'room_id' => $slot['room_id'] ?? null,
                'room_name' => $slot['room_name'] ?? null,
                'equipment_id' => $slot['equipment_id'] ?? null,
                'equipment_name' => $slot['equipment_name'] ?? null,
                'from_package' => $slot['from_package'] ?? null,
                'new_package_id' => $slot['new_package_id'] ?? null,
                'treatment_plan_item_id' => $slot['treatment_plan_item_id'] ?? null,
                'practitioners' => collect($slot['available_practitioners'] ?? [])
                    ->filter(fn($p) => !empty($p['name']) && $p['name'] !== 'Unknown' && (($p['is_any_available'] ?? false) || !empty($p['id'])))
                    ->map(fn($p) => [
                        'id' => $p['id'] ?? null,
                        'name' => $p['name'] ?? '',
                        'is_recommended' => $p['is_recommended'] ?? false,
                        'is_any_available' => $p['is_any_available'] ?? false,
                        'status' => $p['status'] ?? 'available',
                    ])
                    ->values()
                    ->all(),
            ];
        })->values()->all();
    }

    // Date metadata for pills
    $datesMeta = [];
    foreach ($slotsByDate as $date => $dateSlots) {
        $carbon = \Carbon\Carbon::parse($date);
        $datesMeta[$date] = [
            'dayName' => $carbon->format('D'),
            'dayNum' => $carbon->format('d'),
            'monthName' => $carbon->format('M'),
            'count' => count($dateSlots),
        ];
    }
@endphp

<div
    class="space-y-4"
    x-data="{
        view: 'cards',
        selectedDate: '{{ $firstDate }}',
        slotsData: {{ Js::from($slotsDataForAlpine) }},
        selections: {{ Js::from($selectedSlots) }},

        get currentDaySlots() {
            return this.slotsData[this.selectedDate] || [];
        },

        isSelected(slotKey) {
            return !!this.selections[slotKey];
        },

        isSelectedPractitioner(slotKey, practitionerId) {
            const sel = this.selections[slotKey];
            return sel && String(sel.practitioner_id) === String(practitionerId);
        },

        hasSelectionOnDate(date) {
            const slots = this.slotsData[date] || [];
            return slots.some(slot => this.selections[slot.key]);
        },

        toggleSelection(slot, practitioner) {
            const key = slot.key;
            const serviceId = slot.service_id;
            const existingSel = this.selections[key];

            // Check if clicking same practitioner on same slot (toggle off)
            if (existingSel && String(existingSel.practitioner_id) === String(practitioner.id)) {
                delete this.selections[key];
                this.syncToLivewire(slot, null);
                return;
            }

            // Remove any previous selection for the same service (different slot)
            const serviceKeySuffix = '_' + (serviceId || '');
            Object.keys(this.selections).forEach(existingKey => {
                if (existingKey.endsWith(serviceKeySuffix) && existingKey !== key) {
                    delete this.selections[existingKey];
                }
            });

            // Add new selection
            this.selections[key] = {
                practitioner_id: practitioner.id,
                practitioner_name: practitioner.name
            };

            this.syncToLivewire(slot, practitioner);
        },

        toggleSelectionCompact(slot) {
            const key = slot.key;
            const serviceId = slot.service_id;

            // If same slot is already selected, toggle off
            if (this.selections[key]) {
                delete this.selections[key];
                this.syncToLivewire(slot, null);
                return;
            }

            // Remove any previous selection for the same service (different slot)
            const serviceKeySuffix = '_' + (serviceId || '');
            Object.keys(this.selections).forEach(existingKey => {
                if (existingKey.endsWith(serviceKeySuffix) && existingKey !== key) {
                    delete this.selections[existingKey];
                }
            });

            // Add new selection with first/recommended practitioner
            const practitioner = slot.practitioners.find(p => p.is_recommended) || slot.practitioners[0];
            if (practitioner) {
                this.selections[key] = {
                    practitioner_id: practitioner.id,
                    practitioner_name: practitioner.name
                };
                this.syncToLivewire(slot, practitioner);
            }
        },

        syncToLivewire(slot, practitioner) {
            const slotData = {
                service_id: slot.service_id,
                service_name: slot.service_name,
                date: slot.date,
                start_time: slot.start_time,
                end_time: slot.end_time,
                duration: slot.duration,
                practitioner_id: practitioner ? practitioner.id : null,
                practitioner_name: practitioner ? practitioner.name : null,
                room_id: slot.room_id,
                room_name: slot.room_name,
                equipment_id: slot.equipment_id,
                equipment_name: slot.equipment_name,
                from_package: slot.from_package,
                new_package_id: slot.new_package_id,
                treatment_plan_item_id: slot.treatment_plan_item_id
            };
            this.$wire.selectSlot(slotData);
        },

        getPractitionerStyle(slotKey, practitioner) {
            const isThisSelected = this.isSelectedPractitioner(slotKey, practitioner.id);
            const isSlotSelected = this.isSelected(slotKey);

            if (practitioner.is_any_available) {
                return isThisSelected
                    ? 'background-color: #8b5cf6; color: white; border-color: #7c3aed;'
                    : (isSlotSelected ? 'background-color: #f3f4f6; color: #6b7280; border-color: #e5e7eb;' : 'background-color: #f5f3ff; color: #6d28d9; border-color: #c4b5fd; border-style: dashed;');
            }
            return isThisSelected
                ? 'background-color: #22c55e; color: white; border-color: #16a34a;'
                : (isSlotSelected ? 'background-color: #f3f4f6; color: #6b7280; border-color: #e5e7eb;' : 'background-color: white; color: #374151; border-color: #e5e7eb;');
        },

        formatTime(timeStr) {
            if (!timeStr) return '';
            const [hours, minutes] = timeStr.split(':');
            const h = parseInt(hours);
            const ampm = h >= 12 ? 'PM' : 'AM';
            const h12 = h % 12 || 12;
            return h12 + ':' + minutes + ' ' + ampm;
        }
    }"
>
    {{-- Header --}}
    <div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-700">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('booking::booking.labels.available_slots') }}
            <span class="ml-2 rounded-full bg-primary-100 px-2 py-0.5 text-xs font-semibold text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                {{ count($slots) }}
            </span>
            <span
                x-show="Object.keys(selections).length > 0"
                x-text="Object.keys(selections).length + ' {{ __('booking::booking.labels.selected') }}'"
                class="ml-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                style="background-color: #dcfce7; color: #15803d;"
            ></span>
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
            @foreach($datesMeta as $date => $meta)
                <button
                    type="button"
                    @click="selectedDate = '{{ $date }}'"
                    class="relative flex flex-col items-center px-4 py-2 rounded-xl border-2 transition-all cursor-pointer min-w-[70px]"
                    :style="selectedDate === '{{ $date }}'
                        ? 'background-color: #3b82f6; border-color: #3b82f6; color: white;'
                        : (hasSelectionOnDate('{{ $date }}') ? 'background-color: #f0fdf4; border-color: #22c55e;' : 'background-color: #f9fafb; border-color: #e5e7eb;')"
                >
                    <template x-if="hasSelectionOnDate('{{ $date }}')">
                        <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full" style="background-color: #22c55e;">
                            <svg class="h-2.5 w-2.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        </span>
                    </template>
                    <span class="text-[10px] font-medium uppercase" :style="selectedDate === '{{ $date }}' ? 'opacity: 0.8;' : 'color: #6b7280;'">{{ $meta['dayName'] }}</span>
                    <span class="text-lg font-bold leading-tight">{{ $meta['dayNum'] }}</span>
                    <span class="text-[10px]" :style="selectedDate === '{{ $date }}' ? 'opacity: 0.8;' : 'color: #6b7280;'">{{ $meta['monthName'] }}</span>
                    <span
                        class="mt-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold"
                        :style="selectedDate === '{{ $date }}'
                            ? 'background-color: rgba(255,255,255,0.2); color: white;'
                            : 'background-color: #e5e7eb; color: #374151;'"
                    >{{ $meta['count'] }}</span>
                </button>
            @endforeach
        </div>

        {{-- Slots Container - Only renders selected day --}}
        <div>
            {{-- Cards View --}}
            <template x-if="view === 'cards'">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <template x-for="slot in currentDaySlots" :key="slot.key">
                        <div
                            class="slot-card relative rounded-lg border-2 p-4 transition-all"
                            :style="isSelected(slot.key)
                                ? 'background-color: #f0fdf4; border-color: #22c55e; box-shadow: 0 0 0 2px #bbf7d0;'
                                : (slot.practitioners.length === 1 ? 'background-color: #fefce8; border-color: #fde047;' : 'background-color: white; border-color: #e5e7eb;')"
                        >
                            {{-- Selected Indicator --}}
                            <template x-if="isSelected(slot.key)">
                                <div class="absolute -top-2 -right-2 z-10">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full shadow-md" style="background-color: #22c55e; color: white;">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    </span>
                                </div>
                            </template>

                            {{-- Header: Time & Duration --}}
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <span :style="'color: ' + (isSelected(slot.key) ? '#22c55e' : '#9ca3af')">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </span>
                                    <span class="text-lg font-bold" :style="'color: ' + (isSelected(slot.key) ? '#15803d' : '#111827')" x-text="formatTime(slot.start_time)"></span>
                                    <span class="text-gray-400">-</span>
                                    <span class="text-gray-600" x-text="formatTime(slot.end_time)"></span>
                                </div>
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :style="isSelected(slot.key) ? 'background-color: #dcfce7; color: #15803d;' : 'background-color: #f3f4f6; color: #4b5563;'"
                                    x-text="slot.duration + ' {{ __('booking::booking.minutes') }}'"
                                ></span>
                            </div>

                            {{-- Service Name --}}
                            <template x-if="slot.service_name">
                                <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300" x-text="slot.service_name"></p>
                            </template>

                            {{-- Practitioners --}}
                            <template x-if="slot.practitioners.length > 0">
                                <div class="mb-3">
                                    <label class="mb-2 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                        <span x-text="isSelected(slot.key) ? '{{ __('booking::booking.labels.click_to_change') }}:' : '{{ __('booking::booking.labels.click_practitioner') }}:'"></span>
                                    </label>
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="practitioner in slot.practitioners" :key="(practitioner.id || 'any') + '-' + slot.key">
                                            <button
                                                type="button"
                                                @click="toggleSelection(slot, practitioner)"
                                                class="practitioner-chip inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition-all cursor-pointer border-2"
                                                :style="getPractitionerStyle(slot.key, practitioner)"
                                            >
                                                {{-- Status Dot / Users Icon --}}
                                                <template x-if="practitioner.is_any_available">
                                                    <svg class="w-5 h-5 flex-shrink-0" :style="'color: ' + (isSelectedPractitioner(slot.key, practitioner.id) ? '#ffffff' : '#8b5cf6')" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                                </template>
                                                <template x-if="!practitioner.is_any_available">
                                                    <span
                                                        class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                                                        :style="'background-color: ' + (isSelectedPractitioner(slot.key, practitioner.id) ? '#ffffff' : (practitioner.status === 'busy_soon' ? '#eab308' : '#22c55e'))"
                                                    ></span>
                                                </template>

                                                {{-- Avatar placeholder --}}
                                                <template x-if="!practitioner.is_any_available">
                                                    <span
                                                        class="w-6 h-6 rounded-full flex items-center justify-center"
                                                        :style="'background-color: ' + (isSelectedPractitioner(slot.key, practitioner.id) ? '#4ade80' : '#e5e7eb')"
                                                    >
                                                        <svg class="w-4 h-4" :style="'color: ' + (isSelectedPractitioner(slot.key, practitioner.id) ? '#ffffff' : '#6b7280')" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                                    </span>
                                                </template>

                                                {{-- Name --}}
                                                <span x-text="practitioner.name"></span>

                                                {{-- Recommended Star --}}
                                                <template x-if="practitioner.is_recommended && !practitioner.is_any_available">
                                                    <svg class="w-4 h-4 flex-shrink-0" :style="'color: ' + (isSelectedPractitioner(slot.key, practitioner.id) ? '#fde047' : '#eab308')" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                                                </template>

                                                {{-- Selected checkmark --}}
                                                <template x-if="isSelectedPractitioner(slot.key, practitioner.id)">
                                                    <svg class="w-5 h-5 flex-shrink-0 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                                </template>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- Room & Equipment --}}
                            <div class="space-y-1.5 text-sm text-gray-500 dark:text-gray-400">
                                <template x-if="slot.room_name">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                        <span x-text="slot.room_name"></span>
                                    </div>
                                </template>
                                <template x-if="slot.equipment_name">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        <span x-text="slot.equipment_name"></span>
                                    </div>
                                </template>
                            </div>

                            {{-- From Package Badge --}}
                            <template x-if="slot.from_package">
                                <div class="mt-3">
                                    <span class="inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path></svg>
                                        {{ __('booking::booking.labels.from_package') }}
                                    </span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Compact View --}}
            <template x-if="view === 'compact'">
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10">
                    <template x-for="slot in currentDaySlots" :key="slot.key">
                        <button
                            type="button"
                            @click="toggleSelectionCompact(slot)"
                            class="group relative flex flex-col items-center rounded-lg border-2 p-2 text-center transition-all active:scale-95"
                            :style="isSelected(slot.key)
                                ? 'background-color: #f0fdf4; border-color: #22c55e; box-shadow: 0 0 0 2px #bbf7d0;'
                                : 'background-color: white; border-color: #e5e7eb;'"
                        >
                            <template x-if="isSelected(slot.key)">
                                <span class="absolute -top-1.5 -right-1.5 flex h-4 w-4 items-center justify-center rounded-full" style="background-color: #22c55e; color: white;">
                                    <svg class="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                </span>
                            </template>

                            <span class="text-base font-bold" :style="'color: ' + (isSelected(slot.key) ? '#15803d' : '#111827')" x-text="slot.start_time"></span>

                            <template x-if="slot.service_name">
                                <span class="mt-0.5 truncate text-[10px] text-gray-500 dark:text-gray-400 max-w-full px-1" :title="slot.service_name" x-text="slot.service_name.substring(0, 10) + (slot.service_name.length > 10 ? '...' : '')"></span>
                            </template>

                            <template x-if="slot.practitioners.length > 0">
                                <span class="mt-0.5 flex items-center gap-0.5 text-[10px] text-gray-400 dark:text-gray-500">
                                    <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                    <span x-text="slot.practitioners.length"></span>
                                </span>
                            </template>

                            <template x-if="!isSelected(slot.key)">
                                <span class="absolute inset-x-0 bottom-0 h-0.5 rounded-b-lg bg-primary-500 opacity-0 transition-opacity group-hover:opacity-100"></span>
                            </template>
                        </button>
                    </template>
                </div>
            </template>
        </div>

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
                <svg class="w-3 h-3" style="color: #22c55e;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                {{ __('booking::booking.labels.selected') }}
            </span>
        </div>
    @else
        {{-- Empty State --}}
        <div class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 py-12 dark:border-gray-600">
            <svg class="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                {{ __('booking::booking.messages.no_slots') }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('booking::booking.messages.generate_slots_hint') }}
            </p>
        </div>
    @endif
</div>
