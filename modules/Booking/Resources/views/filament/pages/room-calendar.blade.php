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
                        <div class="flex-1 min-w-[180px] px-3 py-3 text-center border-r border-gray-200 dark:border-gray-700 last:border-r-0">
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
                                <div class="flex-1 min-w-[180px] border-r border-gray-200 dark:border-gray-700 last:border-r-0 relative">
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
                                            class="absolute inset-x-1 top-0 rounded-lg border-l-4 px-2 py-1 cursor-pointer hover:shadow-lg transition-all"
                                            style="height: {{ $position['height'] - 4 }}px; z-index: 5; background: {{ $statusStyles['bg'] }}; border-left-color: {{ $statusStyles['border'] }}; overflow: hidden;"
                                            title="{{ $position['startTime'] }} - {{ $position['endTime'] }} ({{ $position['duration'] }} min)&#10;{{ __('booking::room_calendar.practitioner') }}: {{ $appointment->practitioner?->full_name ?? '-' }}&#10;{{ __('booking::room_calendar.status') }}: {{ $appointment->status }}"
                                            wire:click="$dispatch('open-modal', { id: 'appointment-{{ $appointment->id }}' })"
                                        >
                                            <div class="font-medium text-gray-900 dark:text-white text-xs whitespace-nowrap overflow-hidden text-ellipsis">
                                                {{ $appointment->patient?->full_name ?? __('booking::room_calendar.unknown') }}
                                            </div>
                                            @if($appointment->patient?->phone)
                                                <div class="text-[10px] text-gray-600 dark:text-gray-300 whitespace-nowrap overflow-hidden text-ellipsis">
                                                    {{ $appointment->patient->phone }}
                                                </div>
                                            @endif
                                            <div class="text-[10px] text-gray-500 dark:text-gray-400 whitespace-nowrap overflow-hidden text-ellipsis">
                                                {{ $appointment->service?->name }}
                                            </div>
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
</x-filament-panels::page>
