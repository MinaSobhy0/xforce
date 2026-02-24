<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('booking::reception.flow.title') }}
        </x-slot>

        @php
            $flowData = $this->getPatientFlowData();
            $patientsByRoom = $this->getPatientsByRoom();
            $lanes = $this->getFlowLanes();
            $roomCount = count($patientsByRoom);
            $totalColumns = 3 + $roomCount; // arriving + waiting + rooms + done

            // Color mapping for Tailwind classes
            $colorClasses = [
                'info' => [
                    'bg' => 'bg-blue-50 dark:bg-blue-900/20',
                    'header' => 'bg-blue-100 dark:bg-blue-800/50',
                    'border' => 'border-blue-200 dark:border-blue-700',
                    'icon' => 'text-blue-600 dark:text-blue-400',
                    'badge' => 'bg-blue-500 text-white',
                ],
                'warning' => [
                    'bg' => 'bg-amber-50 dark:bg-amber-900/20',
                    'header' => 'bg-amber-100 dark:bg-amber-800/50',
                    'border' => 'border-amber-200 dark:border-amber-700',
                    'icon' => 'text-amber-600 dark:text-amber-400',
                    'badge' => 'bg-amber-500 text-white',
                ],
                'success' => [
                    'bg' => 'bg-green-50 dark:bg-green-900/20',
                    'header' => 'bg-green-100 dark:bg-green-800/50',
                    'border' => 'border-green-200 dark:border-green-700',
                    'icon' => 'text-green-600 dark:text-green-400',
                    'badge' => 'bg-green-500 text-white',
                ],
                'room' => [
                    'bg' => 'bg-cyan-50 dark:bg-cyan-900/20',
                    'header' => 'bg-cyan-100 dark:bg-cyan-800/50',
                    'border' => 'border-cyan-200 dark:border-cyan-700',
                    'icon' => 'text-cyan-600 dark:text-cyan-400',
                    'badge' => 'bg-cyan-500 text-white',
                ],
            ];

            $waitTimeClasses = [
                'success' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-800 dark:text-amber-100',
                'danger' => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
            ];
        @endphp

        {{-- Responsive grid: stack on mobile, horizontal scroll on tablet, full width on desktop --}}
        <div class="overflow-x-auto pb-4" wire:poll.15s>
            <div class="grid gap-3 min-w-max lg:min-w-0"
                 style="grid-template-columns: repeat({{ $totalColumns }}, minmax(200px, 1fr));">

                {{-- Arriving Lane --}}
                @php
                    $arrivingLane = $lanes['arriving'];
                    $arrivingAppointments = $flowData['arriving'] ?? collect();
                    $arrivingColors = $colorClasses[$arrivingLane['color']];
                @endphp
                <div class="flex flex-col rounded-xl border-2 {{ $arrivingColors['border'] }} overflow-hidden min-h-[350px]">
                    <div class="flex items-center justify-between px-3 py-2 {{ $arrivingColors['header'] }}">
                        <div class="flex items-center gap-2 min-w-0">
                            <x-dynamic-component :component="$arrivingLane['icon']" class="w-4 h-4 flex-shrink-0 {{ $arrivingColors['icon'] }}" />
                            <span class="text-xs font-semibold text-gray-900 dark:text-white truncate">{{ $arrivingLane['label'] }}</span>
                        </div>
                        <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold rounded-full flex-shrink-0 {{ $arrivingColors['badge'] }}">
                            {{ $arrivingAppointments->count() }}
                        </span>
                    </div>
                    <div class="flex-1 p-2 space-y-2 overflow-y-auto {{ $arrivingColors['bg'] }}">
                        @forelse($arrivingAppointments as $appointment)
                            @include('booking::filament.widgets.partials.patient-card', ['appointment' => $appointment, 'waitTimeClasses' => $waitTimeClasses, 'showCheckIn' => true])
                        @empty
                            <div class="flex flex-col items-center justify-center py-6 text-gray-400 dark:text-gray-500">
                                <x-dynamic-component :component="$arrivingLane['icon']" class="w-6 h-6 mb-1 opacity-50" />
                                <span class="text-xs">{{ __('booking::reception.flow.empty') }}</span>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Waiting Lane --}}
                @php
                    $waitingLane = $lanes['waiting'];
                    $waitingAppointments = $flowData['waiting'] ?? collect();
                    $waitingColors = $colorClasses[$waitingLane['color']];
                @endphp
                <div class="flex flex-col rounded-xl border-2 {{ $waitingColors['border'] }} overflow-hidden min-h-[350px]">
                    <div class="flex items-center justify-between px-3 py-2 {{ $waitingColors['header'] }}">
                        <div class="flex items-center gap-2 min-w-0">
                            <x-dynamic-component :component="$waitingLane['icon']" class="w-4 h-4 flex-shrink-0 {{ $waitingColors['icon'] }}" />
                            <span class="text-xs font-semibold text-gray-900 dark:text-white truncate">{{ $waitingLane['label'] }}</span>
                        </div>
                        <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold rounded-full flex-shrink-0 {{ $waitingColors['badge'] }}">
                            {{ $waitingAppointments->count() }}
                        </span>
                    </div>
                    <div class="flex-1 p-2 space-y-2 overflow-y-auto {{ $waitingColors['bg'] }}">
                        @forelse($waitingAppointments as $appointment)
                            @include('booking::filament.widgets.partials.patient-card', ['appointment' => $appointment, 'waitTimeClasses' => $waitTimeClasses])
                        @empty
                            <div class="flex flex-col items-center justify-center py-6 text-gray-400 dark:text-gray-500">
                                <x-dynamic-component :component="$waitingLane['icon']" class="w-6 h-6 mb-1 opacity-50" />
                                <span class="text-xs">{{ __('booking::reception.flow.empty') }}</span>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Individual Room Columns --}}
                @foreach($patientsByRoom as $roomId => $roomData)
                    @php
                        $room = $roomData['room'];
                        $roomAppointments = $roomData['appointments'];
                        $roomColors = $colorClasses['room'];
                        $isOccupied = $roomAppointments->count() > 0;
                    @endphp
                    <div class="flex flex-col rounded-xl border-2 {{ $roomColors['border'] }} overflow-hidden min-h-[350px] {{ $isOccupied ? '' : 'opacity-60' }}">
                        <div class="flex items-center justify-between px-3 py-2 {{ $roomColors['header'] }}">
                            <div class="flex items-center gap-2 min-w-0">
                                <x-heroicon-o-building-office class="w-4 h-4 flex-shrink-0 {{ $roomColors['icon'] }}" />
                                <span class="text-xs font-semibold text-gray-900 dark:text-white truncate" title="{{ $room->name }}">
                                    {{ $room->name }}
                                </span>
                            </div>
                            @if($isOccupied)
                                <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold rounded-full flex-shrink-0 {{ $roomColors['badge'] }}">
                                    {{ $roomAppointments->count() }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium rounded-full bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-300 flex-shrink-0">
                                    {{ __('booking::reception.flow.room_available') }}
                                </span>
                            @endif
                        </div>
                        <div class="flex-1 p-2 space-y-2 overflow-y-auto {{ $roomColors['bg'] }}">
                            @forelse($roomAppointments as $appointment)
                                @include('booking::filament.widgets.partials.patient-card', ['appointment' => $appointment, 'waitTimeClasses' => $waitTimeClasses, 'showRoom' => false])
                            @empty
                                <div class="flex flex-col items-center justify-center py-6 text-gray-400 dark:text-gray-500">
                                    <x-heroicon-o-check-circle class="w-6 h-6 mb-1 opacity-50 text-green-400" />
                                    <span class="text-xs">{{ __('booking::reception.flow.room_empty') }}</span>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endforeach

                {{-- Done Lane --}}
                @php
                    $doneLane = $lanes['done'];
                    $doneAppointments = $flowData['done'] ?? collect();
                    $doneColors = $colorClasses[$doneLane['color']];
                @endphp
                <div class="flex flex-col rounded-xl border-2 {{ $doneColors['border'] }} overflow-hidden min-h-[350px]">
                    <div class="flex items-center justify-between px-3 py-2 {{ $doneColors['header'] }}">
                        <div class="flex items-center gap-2 min-w-0">
                            <x-dynamic-component :component="$doneLane['icon']" class="w-4 h-4 flex-shrink-0 {{ $doneColors['icon'] }}" />
                            <span class="text-xs font-semibold text-gray-900 dark:text-white truncate">{{ $doneLane['label'] }}</span>
                        </div>
                        <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold rounded-full flex-shrink-0 {{ $doneColors['badge'] }}">
                            {{ $doneAppointments->count() }}
                        </span>
                    </div>
                    <div class="flex-1 p-2 space-y-2 overflow-y-auto {{ $doneColors['bg'] }}">
                        @forelse($doneAppointments as $appointment)
                            @include('booking::filament.widgets.partials.patient-card', ['appointment' => $appointment, 'waitTimeClasses' => $waitTimeClasses])
                        @empty
                            <div class="flex flex-col items-center justify-center py-6 text-gray-400 dark:text-gray-500">
                                <x-dynamic-component :component="$doneLane['icon']" class="w-6 h-6 mb-1 opacity-50" />
                                <span class="text-xs">{{ __('booking::reception.flow.empty') }}</span>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
