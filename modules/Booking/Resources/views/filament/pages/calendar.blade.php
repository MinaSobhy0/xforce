<x-filament-panels::page>
    <div>
        <div class="space-y-6">
            {{-- Filters --}}
            <x-filament::section>
                {{ $this->form }}
            </x-filament::section>

            {{-- Calendar Navigation --}}
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <x-filament::button wire:click="today" size="sm" color="gray">
                        {{ __('booking::calendar.today') }}
                    </x-filament::button>

                    <x-filament::button wire:click="previous" size="sm" color="gray" icon="heroicon-o-chevron-left" icon-position="before"></x-filament::button>

                    <x-filament::button wire:click="next" size="sm" color="gray" icon="heroicon-o-chevron-right" icon-position="before"></x-filament::button>

                    <span class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $this->getDateRangeLabel() }}
                    </span>
                </div>

                <div class="flex items-center space-x-2">
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
                <div id="calendar" class="min-h-[600px]" wire:ignore x-data="{
                    calendar: null,
                    init() {
                        this.initCalendar();
                        Livewire.on('refreshCalendar', () => { this.calendar.refetchEvents(); });
                        Livewire.on('calendarViewChanged', (data) => {
                            const mode = data.mode || data;
                            const events = data.events || [];
                            this.calendar.changeView(this.modeToView(mode));
                            this.updateEvents(events);
                        });
                        Livewire.on('calendarDateChanged', (data) => {
                            const date = data.date || data;
                            const events = data.events || [];
                            this.calendar.gotoDate(date);
                            this.updateEvents(events);
                        });
                    },
                    updateEvents(events) {
                        this.calendar.removeAllEvents();
                        events.forEach(event => this.calendar.addEvent(event));
                    },
                    modeToView(mode) {
                        return mode === 'day' ? 'timeGridDay' : mode === 'week' ? 'timeGridWeek' : 'dayGridMonth';
                    },
                    initCalendar() {
                        this.calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
                            initialView: this.modeToView('{{ $viewMode }}'),
                            initialDate: '{{ $selectedDate }}',
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
                            direction: '{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}',
                            events: @json($this->getAppointments()),
                            eventClick: (info) => { window.location.href = '{{ route('filament.tenant.resources.appointments.view', ':id') }}'.replace(':id', info.event.id); },
                            select: (info) => { window.location.href = '/admin/create-booking?date=' + info.startStr.split('T')[0] + '&start_time=' + (info.startStr.split('T')[1] || '09:00:00'); },
                            dateClick: (info) => { window.location.href = '/admin/create-booking?date=' + info.dateStr.split('T')[0] + '&start_time=' + (info.dateStr.split('T')[1] || '09:00:00'); },
                            eventDidMount: (info) => {
                                if (typeof tippy !== 'undefined') {
                                    tippy(info.el, {
                                        content: '<div class=\"p-2\"><div class=\"font-semibold\">' + (info.event.extendedProps.patient || '') + '</div><div class=\"text-sm\">' + (info.event.extendedProps.treatment || '') + '</div></div>',
                                        allowHTML: true,
                                        theme: 'light-border',
                                        placement: 'top',
                                    });
                                }
                            },
                        });
                        this.calendar.render();
                    }
                }"></div>
            </x-filament::section>

            {{-- Legend --}}
            <div class="flex items-center gap-3 text-xs">
                <div class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded bg-blue-200 border border-blue-400"></span>
                    <span class="text-gray-600 dark:text-gray-400">{{ __('booking::room_calendar.legend.scheduled') }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded bg-amber-200 border border-amber-400"></span>
                    <span class="text-gray-600 dark:text-gray-400">{{ __('booking::room_calendar.legend.checked_in') }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded bg-purple-200 border border-purple-400"></span>
                    <span class="text-gray-600 dark:text-gray-400">{{ __('booking::room_calendar.legend.in_progress') }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded bg-green-200 border border-green-400"></span>
                    <span class="text-gray-600 dark:text-gray-400">{{ __('booking::room_calendar.legend.completed') }}</span>
                </div>
            </div>

            <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/themes/light-border.css"/>
            <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
            <script src="https://unpkg.com/@popperjs/core@2"></script>
            <script src="https://unpkg.com/tippy.js@6"></script>
            <style>.fc{font-family:inherit}.fc-theme-standard td,.fc-theme-standard th{border-color:#e5e7eb}.dark .fc-theme-standard td,.dark .fc-theme-standard th{border-color:#374151}.fc-event{cursor:pointer;padding:2px 4px;border-radius:4px;font-size:.75rem}.fc-timegrid-slot{height:2em}.fc-col-header-cell-cushion,.fc-daygrid-day-number{padding:8px}</style>
        </div>
    </div>
</x-filament-panels::page>
