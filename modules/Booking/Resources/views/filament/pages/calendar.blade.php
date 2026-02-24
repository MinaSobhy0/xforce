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

                <x-filament::button wire:click="today" size="sm" color="gray">
                    {{ __('booking::calendar.today') }}
                </x-filament::button>

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

                <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-2"></div>

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
    <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/themes/light-border.css"/>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script src="https://unpkg.com/tippy.js@6"></script>
    <style>
        .fc { font-family: inherit; }
        .fc-theme-standard td, .fc-theme-standard th { border-color: rgb(229 231 235); }
        .dark .fc-theme-standard td, .dark .fc-theme-standard th { border-color: rgb(55 65 81); }
        .fc-event {
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            border: none !important;
            border-left: 3px solid !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
        }
        .fc-event:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }
        .fc-event .fc-event-main {
            padding: 2px 0;
        }
        .fc-event-title {
            font-weight: 500;
            white-space: normal;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .fc-timegrid-slot { height: 2.5em; }
        .fc-col-header-cell-cushion, .fc-daygrid-day-number { padding: 8px; font-weight: 500; }
        .fc-daygrid-event { margin: 2px 4px; }
        .fc-timegrid-event { margin: 0 2px; }
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
                allDaySlot: false,
                nowIndicator: true,
                editable: false,
                selectable: true,
                selectMirror: true,
                dayMaxEvents: true,
                weekends: true,
                locale: '{{ app()->getLocale() }}',
                direction: '{{ app()->getLocale() === "ar" ? "rtl" : "ltr" }}',
                events: @json($this->getAppointments()),
                eventClick: function(info) {
                    window.location.href = '/admin/appointments/' + info.event.id;
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
                    if (typeof tippy !== 'undefined') {
                        let content = '<div class="p-2 text-sm">';
                        if (info.event.extendedProps.time) {
                            content += '<div class="text-gray-500">' + info.event.extendedProps.time + '</div>';
                        }
                        if (info.event.extendedProps.practitioner) {
                            content += '<div>{{ __("booking::calendar.practitioner") }}: ' + info.event.extendedProps.practitioner + '</div>';
                        }
                        if (info.event.extendedProps.room) {
                            content += '<div>{{ __("booking::calendar.room") }}: ' + info.event.extendedProps.room + '</div>';
                        }
                        if (info.event.extendedProps.status) {
                            content += '<div class="mt-1 text-xs text-gray-400">' + info.event.extendedProps.status.replace('_', ' ') + '</div>';
                        }
                        content += '</div>';
                        tippy(info.el, {
                            content: content,
                            allowHTML: true,
                            theme: 'light-border',
                            placement: 'top',
                        });
                    }
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
</x-filament-panels::page>