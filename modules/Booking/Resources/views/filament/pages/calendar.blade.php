<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filters --}}
        <x-filament::section>
            {{ $this->form }}
        </x-filament::section>

        {{-- Calendar Navigation --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <x-filament::button
                    wire:click="today"
                    size="sm"
                    color="gray"
                >
                    {{ __('booking::calendar.today') }}
                </x-filament::button>

                <x-filament::button
                    wire:click="previous"
                    size="sm"
                    color="gray"
                    icon="heroicon-o-chevron-left"
                    icon-position="before"
                >
                </x-filament::button>

                <x-filament::button
                    wire:click="next"
                    size="sm"
                    color="gray"
                    icon="heroicon-o-chevron-right"
                    icon-position="before"
                >
                </x-filament::button>

                <span class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $this->getDateRangeLabel() }}
                </span>
            </div>

            <div class="flex items-center space-x-2">
                <x-filament::button
                    wire:click="setViewMode('day')"
                    size="sm"
                    :color="$viewMode === 'day' ? 'primary' : 'gray'"
                >
                    {{ __('booking::calendar.view.day') }}
                </x-filament::button>

                <x-filament::button
                    wire:click="setViewMode('week')"
                    size="sm"
                    :color="$viewMode === 'week' ? 'primary' : 'gray'"
                >
                    {{ __('booking::calendar.view.week') }}
                </x-filament::button>

                <x-filament::button
                    wire:click="setViewMode('month')"
                    size="sm"
                    :color="$viewMode === 'month' ? 'primary' : 'gray'"
                >
                    {{ __('booking::calendar.view.month') }}
                </x-filament::button>
            </div>
        </div>

        {{-- Calendar Container --}}
        <x-filament::section>
            <div
                id="calendar"
                class="min-h-[600px]"
                wire:ignore
                x-data="{
                    calendar: null,
                    init() {
                        this.initCalendar();

                        Livewire.on('refreshCalendar', () => {
                            this.calendar.refetchEvents();
                        });

                        Livewire.on('calendarViewChanged', (data) => {
                            const mode = data.mode || data;
                            const events = data.events || [];
                            const view = this.modeToView(mode);
                            this.calendar.changeView(view);
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
                        switch(mode) {
                            case 'day': return 'timeGridDay';
                            case 'week': return 'timeGridWeek';
                            case 'month': return 'dayGridMonth';
                            default: return 'timeGridWeek';
                        }
                    },
                    initCalendar() {
                        const calendarEl = document.getElementById('calendar');

                        this.calendar = new FullCalendar.Calendar(calendarEl, {
                            initialView: this.getView(),
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
                            eventClick: function(info) {
                                window.location.href = '{{ route('filament.tenant.resources.appointments.view', ':id') }}'.replace(':id', info.event.id);
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
                                tippy(info.el, {
                                    content: `
                                        <div class='p-2'>
                                            <div class='font-semibold'>${info.event.extendedProps.patient}</div>
                                            <div class='text-sm'>${info.event.extendedProps.treatment}</div>
                                            <div class='text-xs text-gray-400 mt-1'>
                                                ${info.event.extendedProps.practitioner}
                                                ${info.event.extendedProps.room ? ' - ' + info.event.extendedProps.room : ''}
                                            </div>
                                            <div class='text-xs mt-1'>
                                                <span class='px-2 py-1 rounded text-white' style='background-color: ${info.event.backgroundColor}'>
                                                    ${info.event.extendedProps.status.replace('_', ' ').toUpperCase()}
                                                </span>
                                            </div>
                                        </div>
                                    `,
                                    allowHTML: true,
                                    theme: 'light-border',
                                    placement: 'top',
                                });
                            },
                        });

                        this.calendar.render();
                    },
                    getView() {
                        const mode = '{{ $viewMode }}';
                        switch(mode) {
                            case 'day': return 'timeGridDay';
                            case 'week': return 'timeGridWeek';
                            case 'month': return 'dayGridMonth';
                            default: return 'timeGridWeek';
                        }
                    }
                }"
            ></div>
        </x-filament::section>

        {{-- Legend --}}
        <x-filament::section>
            <div class="flex flex-wrap gap-4">
                @foreach(\Modules\Booking\Models\Appointment::STATUSES as $status => $label)
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 rounded" style="background-color: {{ $this->getStatusColor($status) }}"></div>
                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
        <script src="https://unpkg.com/@popperjs/core@2"></script>
        <script src="https://unpkg.com/tippy.js@6"></script>
    @endpush

    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/themes/light-border.css"/>
        <style>
            .fc {
                font-family: inherit;
            }
            .fc-theme-standard td, .fc-theme-standard th {
                border-color: rgb(229 231 235);
            }
            .dark .fc-theme-standard td, .dark .fc-theme-standard th {
                border-color: rgb(55 65 81);
            }
            .fc-event {
                cursor: pointer;
                padding: 2px 4px;
                border-radius: 4px;
                font-size: 0.75rem;
            }
            .fc-timegrid-slot {
                height: 2em;
            }
            .fc-col-header-cell-cushion {
                padding: 8px;
            }
            .fc-daygrid-day-number {
                padding: 8px;
            }
        </style>
    @endpush
</x-filament-panels::page>
