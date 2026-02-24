<x-filament-panels::page>
    @php
        $rooms = $this->getRooms();
        $timeSlots = $this->getTimeSlots();
        $appointmentsByRoom = $this->getAppointmentsByRoom();
        $currentTimePosition = $this->getCurrentTimePosition();
    @endphp

    {{-- Filters & Legend --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <div class="flex items-center gap-2">
            {{-- Previous Day Button --}}
            <x-filament::icon-button
                icon="heroicon-o-chevron-left"
                wire:click="previousDay"
                :label="__('booking::room_calendar.previous_day')"
                color="gray"
            />

            {{-- Date Picker --}}
            <div class="w-48">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="date"
                        wire:model.live="selectedDate"
                        class="text-center"
                    />
                </x-filament::input.wrapper>
            </div>

            {{-- Next Day Button --}}
            <x-filament::icon-button
                icon="heroicon-o-chevron-right"
                wire:click="nextDay"
                :label="__('booking::room_calendar.next_day')"
                color="gray"
            />

            {{-- Today Button --}}
            @unless($this->isToday())
                <x-filament::button
                    wire:click="goToToday"
                    color="primary"
                    size="sm"
                >
                    {{ __('booking::room_calendar.today') }}
                </x-filament::button>
            @endunless

            <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-2"></div>

            {{-- Legend --}}
            <div class="flex items-center gap-3 text-xs">
                <div class="flex items-center gap-1.5">
                    <span class="inline-block w-3 h-3 rounded" style="background-color: #3b82f6;"></span>
                    <span class="text-gray-500 dark:text-gray-400">{{ __('booking::room_calendar.legend.scheduled') }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="inline-block w-3 h-3 rounded" style="background-color: #6366f1;"></span>
                    <span class="text-gray-500 dark:text-gray-400">{{ __('booking::room_calendar.legend.confirmed') }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="inline-block w-3 h-3 rounded" style="background-color: #f59e0b;"></span>
                    <span class="text-gray-500 dark:text-gray-400">{{ __('booking::room_calendar.legend.checked_in') }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="inline-block w-3 h-3 rounded" style="background-color: #a855f7;"></span>
                    <span class="text-gray-500 dark:text-gray-400">{{ __('booking::room_calendar.legend.in_progress') }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="inline-block w-3 h-3 rounded" style="background-color: #22c55e;"></span>
                    <span class="text-gray-500 dark:text-gray-400">{{ __('booking::room_calendar.legend.completed') }}</span>
                </div>
            </div>
        </div>

        {{-- Back to Calendar Button --}}
        <x-filament::button
            tag="a"
            href="{{ route('filament.tenant.pages.calendar') }}"
            size="sm"
            color="gray"
            icon="heroicon-o-calendar-days"
        >
            {{ __('booking::room_calendar.back_to_calendar') }}
        </x-filament::button>
    </div>

    {{-- Calendar Grid --}}
    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="overflow-x-auto overflow-y-visible">
            <div class="min-w-max">
                {{-- Header: Room Names --}}
                <div class="flex border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 sticky top-0 z-10">
                    {{-- Time Column Header --}}
                    <div class="w-20 flex-shrink-0 px-3 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 border-r border-gray-200 dark:border-gray-700">
                        {{ __('booking::room_calendar.time') }}
                    </div>

                    {{-- Room Headers --}}
                    @foreach($rooms as $room)
                        <div class="flex-1 min-w-[180px] px-3 py-3 text-center" style="border-right: 1px solid #d1d5db;">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white truncate" title="{{ $room->name }}">
                                {{ $room->name }}
                            </div>
                            @if($room->capacity)
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('booking::room_calendar.capacity') }}: {{ $room->capacity }}
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @if($rooms->isEmpty())
                        <div class="flex-1 px-3 py-3 text-center text-gray-500 dark:text-gray-400">
                            {{ __('booking::room_calendar.no_rooms') }}
                        </div>
                    @endif
                </div>

                {{-- Time Slots Grid --}}
                <div class="relative">
                    {{-- Current Time Indicator --}}
                    @if($currentTimePosition !== null)
                        <div class="absolute left-0 right-0 z-20 pointer-events-none" style="top: {{ $currentTimePosition }}px;">
                            <div class="flex items-center">
                                <div class="w-20 flex-shrink-0 text-right pr-2">
                                    <span class="inline-block px-1 py-0.5 text-[10px] font-bold bg-red-500 text-white rounded">
                                        {{ now()->format('H:i') }}
                                    </span>
                                </div>
                                <div class="flex-1 h-0.5 bg-red-500"></div>
                            </div>
                        </div>
                    @endif

                    {{-- Time Slots --}}
                    @foreach($timeSlots as $slot)
                        <div class="flex border-b {{ $slot['isHour'] ? 'border-gray-300 dark:border-gray-600' : 'border-gray-100 dark:border-gray-800' }}" style="height: 48px;">
                            {{-- Time Label --}}
                            <div class="w-20 flex-shrink-0 px-3 py-1 text-xs text-gray-500 dark:text-gray-400 border-r border-gray-200 dark:border-gray-700 {{ $slot['isHour'] ? 'font-semibold text-gray-700 dark:text-gray-300' : 'text-gray-400 dark:text-gray-500' }}">
                                @if($slot['isHour'])
                                    {{ $slot['label'] }}
                                @else
                                    <span class="text-[10px]">{{ $slot['label'] }}</span>
                                @endif
                            </div>

                            {{-- Room Columns --}}
                            @foreach($rooms as $room)
                                @php
                                    $appointment = $this->isSlotOccupied($room->id, $slot['time']);
                                    $isStartSlot = $appointment && $appointment->start_time->format('H:i') === $slot['label'];
                                @endphp
                                <div class="flex-1 min-w-[180px] relative overflow-hidden" style="border-right: 1px solid #d1d5db;">
                                    @if($isStartSlot)
                                        @php
                                            $position = $this->getAppointmentPosition($appointment);
                                            $statusColor = $this->getStatusColor($appointment->status);
                                        @endphp
                                        @php
                                            $statusStyles = match ($appointment->status) {
                                                'scheduled' => ['bg' => 'rgba(59, 130, 246, 0.1)', 'border' => '#3b82f6', 'text' => '#1e40af', 'darkBg' => 'rgba(59, 130, 246, 0.2)'],
                                                'confirmed' => ['bg' => 'rgba(99, 102, 241, 0.1)', 'border' => '#6366f1', 'text' => '#4338ca', 'darkBg' => 'rgba(99, 102, 241, 0.2)'],
                                                'checked_in' => ['bg' => 'rgba(245, 158, 11, 0.1)', 'border' => '#f59e0b', 'text' => '#b45309', 'darkBg' => 'rgba(245, 158, 11, 0.2)'],
                                                'in_progress' => ['bg' => 'rgba(168, 85, 247, 0.1)', 'border' => '#a855f7', 'text' => '#7c3aed', 'darkBg' => 'rgba(168, 85, 247, 0.2)'],
                                                'completed' => ['bg' => 'rgba(34, 197, 94, 0.1)', 'border' => '#22c55e', 'text' => '#15803d', 'darkBg' => 'rgba(34, 197, 94, 0.2)'],
                                                default => ['bg' => 'rgba(107, 114, 128, 0.1)', 'border' => '#6b7280', 'text' => '#374151', 'darkBg' => 'rgba(107, 114, 128, 0.2)'],
                                            };
                                        @endphp
                                        <div
                                            class="appointment-card"
                                            style="position: absolute; top: 2px; left: 4px; width: calc(100% - 8px); height: {{ $position['height'] - 6 }}px; z-index: 5; background: {{ $statusStyles['bg'] }}; border: none; border-left: 3px solid {{ $statusStyles['border'] }}; border-radius: 6px; padding: 4px 8px; box-sizing: border-box; overflow: hidden; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: all 0.2s ease;"
                                            onmouseover="this.style.boxShadow='0 4px 6px rgba(0,0,0,0.1)'; this.style.transform='translateY(-1px)';"
                                            onmouseout="this.style.boxShadow='0 1px 2px rgba(0,0,0,0.05)'; this.style.transform='none';"
                                            data-patient="{{ $appointment->patient?->full_name ?? __('booking::room_calendar.unknown') }}"
                                            data-phone="{{ $appointment->patient?->phone ?? '' }}"
                                            data-service="{{ $appointment->service?->name ?? '' }}"
                                            data-time="{{ $position['startTime'] }} - {{ $position['endTime'] }} ({{ $position['duration'] }} min)"
                                            data-practitioner="{{ $appointment->practitioner?->full_name ?? '-' }}"
                                            data-room="{{ $appointment->room?->name ?? '-' }}"
                                            data-status="{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}"
                                            wire:click="$dispatch('open-modal', { id: 'appointment-{{ $appointment->id }}' })"
                                        >
                                            <p style="margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; font-weight: 500; color: {{ $statusStyles['text'] }};">
                                                {{ $appointment->patient?->full_name ?? __('booking::room_calendar.unknown') }}
                                            </p>
                                            @if($appointment->patient?->phone)
                                                <p style="margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 10px; color: {{ $statusStyles['text'] }}; opacity: 0.8;">
                                                    {{ $appointment->patient->phone }}
                                                </p>
                                            @endif
                                            <p style="margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 10px; color: {{ $statusStyles['text'] }}; opacity: 0.7;">
                                                {{ $appointment->service?->name }}
                                            </p>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Summary Stats --}}
    <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
        @php
            $allAppointments = $this->getAppointments();
            $totalAppointments = $allAppointments->count();
            $occupiedRooms = $allAppointments->pluck('room_id')->unique()->count();
            $totalRooms = $rooms->count();
            $avgDuration = $totalAppointments > 0 ? round($allAppointments->avg('duration_minutes')) : 0;
        @endphp

        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalAppointments }}</div>
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::room_calendar.stats.total_bookings') }}</div>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $occupiedRooms }} / {{ $totalRooms }}</div>
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::room_calendar.stats.rooms_used') }}</div>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalRooms - $occupiedRooms }}</div>
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::room_calendar.stats.rooms_available') }}</div>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $avgDuration }} min</div>
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::room_calendar.stats.avg_duration') }}</div>
        </div>
    </div>

    @assets
    <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/themes/light-border.css"/>
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script src="https://unpkg.com/tippy.js@6"></script>
    @endassets

    @script
    <script>
        function initRoomCalendarTippy() {
            if (typeof tippy === 'undefined') return;

            document.querySelectorAll('.appointment-card').forEach(function(el) {
                if (el._tippy) return; // Already initialized

                const patient = el.dataset.patient || '';
                const phone = el.dataset.phone || '';
                const service = el.dataset.service || '';
                const time = el.dataset.time || '';
                const practitioner = el.dataset.practitioner || '';
                const room = el.dataset.room || '';
                const status = el.dataset.status || '';

                let content = '<div style="padding: 8px; font-size: 13px;">';
                content += '<div style="font-weight: 600; color: #1f2937; margin-bottom: 4px;">' + patient + '</div>';
                if (phone) content += '<div style="color: #4b5563;">' + phone + '</div>';
                if (service) content += '<div style="color: #4b5563; margin-top: 4px;">' + service + '</div>';
                content += '<div style="color: #6b7280; margin-top: 8px;"><strong>{{ __("booking::room_calendar.time") }}:</strong> ' + time + '</div>';
                content += '<div style="color: #6b7280;"><strong>{{ __("booking::room_calendar.practitioner") }}:</strong> ' + practitioner + '</div>';
                content += '<div style="color: #6b7280;"><strong>{{ __("booking::room_calendar.room") }}:</strong> ' + room + '</div>';
                content += '<div style="color: #6b7280;"><strong>{{ __("booking::room_calendar.status") }}:</strong> ' + status + '</div>';
                content += '</div>';

                tippy(el, {
                    content: content,
                    allowHTML: true,
                    theme: 'light-border',
                    placement: 'top',
                    interactive: true,
                    maxWidth: 320,
                });
            });
        }

        // Initialize immediately
        initRoomCalendarTippy();

        // Re-initialize on Livewire updates
        Livewire.hook('morph.updated', () => {
            setTimeout(initRoomCalendarTippy, 100);
        });
    </script>
    @endscript
</x-filament-panels::page>
