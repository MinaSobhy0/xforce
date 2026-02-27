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
            <div class="grid gap-4 min-w-max lg:min-w-0"
                 style="grid-template-columns: repeat({{ $totalColumns }}, minmax(280px, 1fr));">

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
                            @include('booking::filament.widgets.partials.patient-card', ['appointment' => $appointment, 'waitTimeClasses' => $waitTimeClasses, 'showCheckIn' => true, 'showAssignActions' => true])
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
                            @include('booking::filament.widgets.partials.patient-card', ['appointment' => $appointment, 'waitTimeClasses' => $waitTimeClasses, 'showAssignActions' => true])
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
                                @include('booking::filament.widgets.partials.patient-card', ['appointment' => $appointment, 'waitTimeClasses' => $waitTimeClasses, 'showRoom' => false, 'showAssignActions' => true])
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
                            @include('booking::filament.widgets.partials.patient-card', ['appointment' => $appointment, 'waitTimeClasses' => $waitTimeClasses, 'showPayment' => false])
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

    {{-- Room Assignment Modal --}}
    @if($showRoomModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="closeRoomModal">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md mx-4 overflow-hidden">
                @php
                    $editingAppointment = $this->getEditingAppointment();
                    $availableRooms = $this->getAvailableRooms();
                @endphp

                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-4 py-3 bg-cyan-50 dark:bg-cyan-900/50 border-b border-cyan-200 dark:border-cyan-800">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-building-office class="w-5 h-5 text-cyan-600 dark:text-cyan-400" />
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ __('booking::reception.actions.assign_room') }}
                        </h3>
                    </div>
                    <button type="button" wire:click="closeRoomModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="p-4 space-y-4">
                    @if($editingAppointment)
                        {{-- Patient Info --}}
                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                                <x-heroicon-o-user class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                            </div>
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ $editingAppointment->patient?->full_name ?? 'Patient' }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $editingAppointment->service?->name }} - {{ $editingAppointment->start_time?->format('H:i') }}
                                </div>
                            </div>
                        </div>

                        {{-- Room Selection --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                {{ __('booking::reception.forms.room') }}
                            </label>
                            <select
                                wire:model="selectedRoomId"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            >
                                <option value="">-- {{ __('booking::reception.no_room') }} --</option>
                                @foreach($availableRooms as $roomId => $roomName)
                                    <option value="{{ $roomId }}">{{ $roomName }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                {{-- Modal Footer --}}
                <div class="flex items-center justify-end gap-2 px-4 py-3 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
                    <x-filament::button color="gray" wire:click="closeRoomModal">
                        {{ __('booking::reception.modal.cancel') }}
                    </x-filament::button>
                    <x-filament::button color="primary" wire:click="saveRoom">
                        {{ __('booking::reception.modal.save') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif

    {{-- Doctor Assignment Modal --}}
    @if($showDoctorModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="closeDoctorModal">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md mx-4 overflow-hidden">
                @php
                    $editingAppointment = $this->getEditingAppointment();
                    $availableDoctors = $this->getAvailableDoctors();
                @endphp

                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-4 py-3 bg-indigo-50 dark:bg-indigo-900/50 border-b border-indigo-200 dark:border-indigo-800">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-user-circle class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ __('booking::reception.actions.assign_doctor') }}
                        </h3>
                    </div>
                    <button type="button" wire:click="closeDoctorModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="p-4 space-y-4">
                    @if($editingAppointment)
                        {{-- Patient Info --}}
                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                                <x-heroicon-o-user class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                            </div>
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ $editingAppointment->patient?->full_name ?? 'Patient' }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $editingAppointment->service?->name }} - {{ $editingAppointment->start_time?->format('H:i') }}
                                </div>
                            </div>
                        </div>

                        {{-- Current Doctor --}}
                        @if($editingAppointment->practitioner)
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                {{ __('booking::reception.columns.doctor') }}:
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ $editingAppointment->practitioner->full_name }}
                                </span>
                            </div>
                        @endif

                        {{-- Doctor Selection --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                {{ __('booking::reception.forms.practitioner') }}
                            </label>
                            <select
                                wire:model="selectedDoctorId"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            >
                                <option value="">-- {{ __('booking::reception.unassigned') }} --</option>
                                @foreach($availableDoctors as $doctorId => $doctorName)
                                    <option value="{{ $doctorId }}">{{ $doctorName }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                {{-- Modal Footer --}}
                <div class="flex items-center justify-end gap-2 px-4 py-3 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
                    <x-filament::button color="gray" wire:click="closeDoctorModal">
                        {{ __('booking::reception.modal.cancel') }}
                    </x-filament::button>
                    <x-filament::button color="primary" wire:click="saveDoctor">
                        {{ __('booking::reception.modal.save') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
