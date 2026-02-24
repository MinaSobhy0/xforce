<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filters --}}
        <x-filament::section>
            {{ $this->form }}
        </x-filament::section>

        {{-- Calendar Navigation & Legend --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                {{-- Navigation --}}
                <x-filament::icon-button
                    icon="heroicon-o-chevron-left"
                    wire:click="previous"
                    color="gray"
                />

                <div class="w-40">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="date"
                            wire:model.live="selectedDate"
                            class="text-center"
                        />
                    </x-filament::input.wrapper>
                </div>

                <x-filament::icon-button
                    icon="heroicon-o-chevron-right"
                    wire:click="next"
                    color="gray"
                />

                @unless($this->isToday())
                    <x-filament::button wire:click="today" size="sm" color="primary">
                        {{ __('booking::calendar.today') }}
                    </x-filament::button>
                @endunless

                <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-2"></div>

                {{-- Legend --}}
                <div class="flex items-center gap-3 text-xs">
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded" style="background-color: #3b82f6;"></span>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('booking::calendar.status.scheduled') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded" style="background-color: #8b5cf6;"></span>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('booking::calendar.status.confirmed') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded" style="background-color: #f59e0b;"></span>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('booking::calendar.status.checked_in') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded" style="background-color: #6366f1;"></span>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('booking::calendar.status.in_progress') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded" style="background-color: #10b981;"></span>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('booking::calendar.status.completed') }}</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <x-filament::button wire:click="setViewMode('day')" size="sm" :color="$viewMode === 'day' ? 'primary' : 'gray'">
                    {{ __('booking::calendar.view.day') }}
                </x-filament::button>

                <x-filament::button wire:click="setViewMode('week')" size="sm" :color="$viewMode === 'week' ? 'primary' : 'gray'">
                    {{ __('booking::calendar.view.week') }}
                </x-filament::button>

                <x-filament::button wire:click="setViewMode('month')" size="sm" :color="$viewMode === 'month' ? 'primary' : 'gray'">
                    {{ __('booking::calendar.view.month') }}
                </x-filament::button>

                <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-4"></div>

                <x-filament::button tag="a" href="{{ route('filament.tenant.pages.room-calendar') }}" size="sm" color="gray" icon="heroicon-o-building-office">
                    {{ __('booking::calendar.view.rooms') }}
                </x-filament::button>
            </div>
        </div>

        {{-- Calendar Container --}}
        <x-filament::section>
            <div id="calendar" class="min-h-[600px]" wire:ignore></div>
        </x-filament::section>

    </div>

    @assets
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <style>
        .fc { font-family: inherit; }
        .fc-theme-standard td, .fc-theme-standard th { border-color: rgb(229 231 235); }
        .dark .fc-theme-standard td, .dark .fc-theme-standard th { border-color: rgb(55 65 81); }
        .fc-event {
            cursor: pointer;
            padding: 2px 6px !important;
            border-radius: 6px;
            font-size: 0.65rem;
            border: none !important;
            border-left: 3px solid !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
            overflow: hidden !important;
            max-width: 100% !important;
        }
        .fc-event:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }
        .fc-event .fc-event-main {
            padding: 1px 0;
            overflow: hidden !important;
            max-width: 100% !important;
        }
        .fc-event-title, .fc-event-title-container {
            font-weight: 500;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            display: block !important;
            max-width: 100% !important;
        }
        .fc-timegrid-event .fc-event-main {
            overflow: hidden !important;
        }
        .fc-timegrid-event-harness {
            overflow: hidden !important;
        }
        .fc-daygrid-event-dot { display: none; }
        .fc-daygrid-event {
            margin: 1px 2px;
            overflow: hidden !important;
            white-space: nowrap !important;
            text-overflow: ellipsis !important;
            max-width: calc(100% - 4px) !important;
        }
        .fc-daygrid-event-harness {
            overflow: hidden !important;
        }
        .fc-daygrid-day-events {
            overflow: hidden !important;
        }
        .fc-timegrid-event {
            margin: 0 2px;
            overflow: hidden !important;
        }
        .fc-timegrid-slot { height: 2.5em; }
        .fc-col-header-cell-cushion, .fc-daygrid-day-number { padding: 8px; font-weight: 500; }
        .fc-h-event .fc-event-main-frame {
            overflow: hidden !important;
        }
    </style>
    @endassets

    @script
    <script>
        const calendarEl = document.getElementById('calendar');
        if (calendarEl && !calendarEl.dataset.initialized) {
            calendarEl.dataset.initialized = 'true';

            const viewMode = $wire.viewMode;
            const initialView = viewMode === 'day' ? 'timeGridDay' : viewMode === 'week' ? 'timeGridWeek' : 'dayGridMonth';

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: initialView,
                initialDate: $wire.selectedDate,
                headerToolbar: false,
                slotMinTime: '08:00:00',
                slotMaxTime: '22:00:00',
                slotDuration: '00:15:00',
                allDaySlot: true,
                nowIndicator: true,
                editable: false,
                selectable: true,
                selectMirror: true,
                dayMaxEvents: 4,
                weekends: true,
                locale: '{{ app()->getLocale() }}',
                direction: '{{ app()->getLocale() === "ar" ? "rtl" : "ltr" }}',
                events: @json($this->getAppointments()),
                eventClick: function(info) {
                    // Don't navigate for grouped events
                    if (info.event.extendedProps.isGroup) {
                        return;
                    }
                    // Show appointment details in modal (ID is a UUID string)
                    const appointmentId = info.event.id;
                    if (appointmentId && !appointmentId.startsWith('group_')) {
                        // Show modal immediately, then load data
                        $wire.set('showModal', true);
                        $wire.set('selectedAppointmentId', appointmentId);
                    }
                },
                select: function(info) {
                    const startDate = info.startStr.split('T')[0];
                    const startTime = info.startStr.split('T')[1] || '09:00:00';
                    window.location.href = '/admin/create-booking?date=' + startDate + '&start_time=' + startTime;
                },
                dateClick: function(info) {
                    const startDate = info.dateStr.split('T')[0];
                    const startTime = info.dateStr.split('T')[1] || '09:00:00';
                    window.location.href = '/admin/create-booking?date=' + startDate + '&start_time=' + startTime;
                },
                eventDidMount: function(info) {
                    let title = '';

                    // Check if this is a grouped event (month view)
                    if (info.event.extendedProps.isGroup) {
                        const appointments = info.event.extendedProps.appointments || [];
                        title = info.event.extendedProps.category + ' (' + info.event.extendedProps.count + ')\n\n';
                        appointments.forEach(function(apt, idx) {
                            if (idx > 0) title += '\n---\n';
                            title += apt.patient;
                            if (apt.phone) title += '\n' + apt.phone;
                            title += '\n' + apt.time + ' - ' + apt.service;
                            if (apt.practitioner) title += '\n{{ __("booking::calendar.practitioner") }}: ' + apt.practitioner;
                        });
                    } else {
                        // Individual appointment
                        if (info.event.extendedProps.patient) title += info.event.extendedProps.patient;
                        if (info.event.extendedProps.phone) title += '\n' + info.event.extendedProps.phone;
                        if (info.event.extendedProps.treatment) title += '\n' + info.event.extendedProps.treatment;
                        if (info.event.extendedProps.time) title += '\n\n{{ __("booking::calendar.time") }}: ' + info.event.extendedProps.time;
                        if (info.event.extendedProps.practitioner) title += '\n{{ __("booking::calendar.practitioner") }}: ' + info.event.extendedProps.practitioner;
                        if (info.event.extendedProps.room) title += '\n{{ __("booking::calendar.room") }}: ' + info.event.extendedProps.room;
                        if (info.event.extendedProps.status) title += '\n{{ __("booking::calendar.status_label") }}: ' + info.event.extendedProps.status.replace('_', ' ');
                    }

                    info.el.setAttribute('title', title);
                },
            });

            calendar.render();
            window.bookingCalendar = calendar;

            $wire.on('calendarViewChanged', (data) => {
                const mode = data.mode || data;
                const events = data.events || [];
                const view = mode === 'day' ? 'timeGridDay' : mode === 'week' ? 'timeGridWeek' : 'dayGridMonth';
                window.bookingCalendar.changeView(view);
                window.bookingCalendar.removeAllEvents();
                events.forEach(event => window.bookingCalendar.addEvent(event));
            });

            $wire.on('calendarDateChanged', (data) => {
                const date = data.date || data;
                const events = data.events || [];
                window.bookingCalendar.gotoDate(date);
                window.bookingCalendar.removeAllEvents();
                events.forEach(event => window.bookingCalendar.addEvent(event));
            });
        }
    </script>
    @endscript

    {{-- Appointment Details Modal --}}
    <div
        x-data="{ open: @entangle('showModal') }"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            {{-- Background overlay --}}
            <div
                x-show="open"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                @click="open = false"
            ></div>

            {{-- Spacer for centering --}}
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            {{-- Modal panel --}}
            <div
                x-show="open"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="inline-block align-bottom bg-white dark:bg-gray-900 rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full"
            >
                {{-- Header --}}
                <div class="bg-gray-50 dark:bg-gray-800 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ __('booking::calendar.appointment_details') }}
                        </h3>
                        <button
                            type="button"
                            @click="open = false"
                            class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                        >
                            <x-heroicon-o-x-mark class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                {{-- Content --}}
                <div class="px-4 py-4 max-h-[60vh] overflow-y-auto">
                    @if($showModal && $selectedAppointmentId)
                        @php
                            $appointment = $this->getSelectedAppointment();
                        @endphp

                        @if($appointment)
                            @include('booking::filament.pages.partials.appointment-details', ['appointment' => $appointment])
                        @else
                            @include('booking::filament.pages.partials.appointment-not-found')
                        @endif
                    @endif
                </div>

                {{-- Footer --}}
                @if($showModal && $selectedAppointmentId)
                    @php
                        $appointment = $appointment ?? $this->getSelectedAppointment();
                    @endphp
                    @if($appointment)
                        <div class="bg-gray-50 dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-2">
                            <x-filament::button
                                tag="a"
                                :href="route('filament.tenant.resources.appointments.view', ['record' => $appointment->id])"
                                color="primary"
                                icon="heroicon-o-arrow-top-right-on-square"
                            >
                                {{ __('booking::calendar.open_full_view') }}
                            </x-filament::button>
                            <x-filament::button
                                color="gray"
                                x-on:click="open = false"
                            >
                                {{ __('booking::calendar.close') }}
                            </x-filament::button>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>