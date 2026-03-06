@php
    $waitTime = $this->calculateWaitTime($appointment);
    $waitClass = $waitTime ? ($waitTimeClasses[$waitTime['severity']] ?? $waitTimeClasses['success']) : '';
    $showRoom = $showRoom ?? true;
    $showCheckIn = $showCheckIn ?? false;
    $showPayment = $showPayment ?? true;
    $showCheckout = $showCheckout ?? true;
    $showAssignActions = $showAssignActions ?? false;
    $canCheckIn = $this->canCheckIn($appointment);
    $canChangeRoomOrDoctor = $this->canChangeRoomOrDoctor($appointment);
    $hasBalance = $appointment->remaining_balance > 0;
    $canRecordPayment = in_array($appointment->status, ['checked_in', 'in_progress']) && $hasBalance;

    // Get visit info
    $visit = $appointment->current_visit;
    $hasOpenVisit = $visit && $visit->status === 'open';

    // Check if can checkout via visit (preferred method)
    $canVisitCheckout = $showCheckout && $hasOpenVisit && $appointment->status === 'completed';

    // Legacy: Check if appointment is completed with unpaid invoice (fallback for non-visit appointments)
    $canLegacyCheckout = $showCheckout
        && !$hasOpenVisit
        && $appointment->status === 'completed'
        && $appointment->invoice
        && in_array($appointment->invoice->status, ['draft', 'issued', 'partially_paid']);

    $canCheckout = $canVisitCheckout || $canLegacyCheckout;

    // Status colors and labels
    $statusConfig = [
        'scheduled' => ['color' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300', 'icon' => 'heroicon-o-clock', 'label' => __('booking::appointments.statuses.scheduled')],
        'confirmed' => ['color' => 'bg-blue-100 text-blue-700 dark:bg-blue-800 dark:text-blue-300', 'icon' => 'heroicon-o-check', 'label' => __('booking::appointments.statuses.confirmed')],
        'checked_in' => ['color' => 'bg-amber-100 text-amber-700 dark:bg-amber-800 dark:text-amber-300', 'icon' => 'heroicon-o-user-plus', 'label' => __('booking::appointments.statuses.checked_in')],
        'in_progress' => ['color' => 'bg-purple-100 text-purple-700 dark:bg-purple-800 dark:text-purple-300', 'icon' => 'heroicon-o-play', 'label' => __('booking::appointments.statuses.in_progress')],
        'completed' => ['color' => 'bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-300', 'icon' => 'heroicon-o-check-circle', 'label' => __('booking::appointments.statuses.completed')],
        'cancelled' => ['color' => 'bg-red-100 text-red-700 dark:bg-red-800 dark:text-red-300', 'icon' => 'heroicon-o-x-circle', 'label' => __('booking::appointments.statuses.cancelled')],
        'no_show' => ['color' => 'bg-red-100 text-red-700 dark:bg-red-800 dark:text-red-300', 'icon' => 'heroicon-o-user-minus', 'label' => __('booking::appointments.statuses.no_show')],
        'rescheduled' => ['color' => 'bg-orange-100 text-orange-700 dark:bg-orange-800 dark:text-orange-300', 'icon' => 'heroicon-o-arrow-path', 'label' => __('booking::appointments.statuses.rescheduled')],
    ];

    $status = $appointment->status ?? 'scheduled';
    $statusInfo = $statusConfig[$status] ?? $statusConfig['scheduled'];
@endphp

<div class="bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow transition-all overflow-hidden">
    {{-- Compact Header --}}
    <div class="flex items-center justify-between px-2 py-1.5 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-100 dark:border-gray-700">
        <div class="flex items-center gap-1.5">
            <span class="text-xs font-bold text-primary-600 dark:text-primary-400">
                {{ $appointment->start_time?->format('H:i') }}
            </span>
            <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium rounded {{ $statusInfo['color'] }}">
                {{ $statusInfo['label'] }}
            </span>
            @if($visit)
                <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] rounded
                    @if($visit->status === 'open') bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300
                    @else bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400
                    @endif">
                    {{ $visit->code }}
                </span>
            @endif
        </div>
        @if($waitTime)
            <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold rounded {{ $waitClass }}">
                {{ $waitTime['formatted'] }}
            </span>
        @endif
    </div>

    {{-- Compact Body --}}
    <div class="px-2 py-1.5 space-y-1">
        {{-- Patient & Service Row --}}
        <div class="flex items-center justify-between gap-2">
            <div class="min-w-0 flex-1">
                <div class="text-xs font-semibold text-gray-900 dark:text-white truncate">
                    {{ $appointment->patient?->full_name ?? __('booking::reception.unknown_patient') }}
                </div>
                @if($appointment->service)
                    <div class="text-[10px] text-gray-500 dark:text-gray-400 truncate">
                        {{ $appointment->service->name }}
                    </div>
                @endif
            </div>
            @if($appointment->patient?->phone)
                <a href="tel:{{ $appointment->patient->phone }}" class="text-[10px] text-gray-400 hover:text-primary-600 flex-shrink-0">
                    <x-heroicon-o-phone class="w-3 h-3" />
                </a>
            @endif
        </div>

        {{-- Room & Doctor Row --}}
        <div class="flex flex-wrap gap-1">
            @if($showAssignActions && $canChangeRoomOrDoctor)
                <button
                    type="button"
                    wire:click="openRoomModal('{{ $appointment->id }}')"
                    class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] font-medium rounded border transition-colors
                        {{ $appointment->room
                            ? 'bg-cyan-50 text-cyan-700 dark:bg-cyan-900/50 dark:text-cyan-300 border-cyan-200 dark:border-cyan-800'
                            : 'bg-gray-50 text-gray-500 dark:bg-gray-800 dark:text-gray-400 border-gray-200 dark:border-gray-700 border-dashed' }}"
                >
                    <x-heroicon-o-building-office class="w-2.5 h-2.5" />
                    {{ $appointment->room?->name ?? __('booking::reception.no_room') }}
                </button>
                <button
                    type="button"
                    wire:click="openDoctorModal('{{ $appointment->id }}')"
                    class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] font-medium rounded border transition-colors
                        {{ $appointment->practitioner
                            ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800'
                            : 'bg-gray-50 text-gray-500 dark:bg-gray-800 dark:text-gray-400 border-gray-200 dark:border-gray-700 border-dashed' }}"
                >
                    <x-heroicon-o-user-circle class="w-2.5 h-2.5" />
                    {{ $appointment->practitioner?->full_name ?? __('booking::reception.unassigned') }}
                </button>
            @else
                @if($showRoom && $appointment->room)
                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] font-medium rounded bg-cyan-50 text-cyan-700 dark:bg-cyan-900/50 dark:text-cyan-300">
                        <x-heroicon-o-building-office class="w-2.5 h-2.5" />
                        {{ $appointment->room->name }}
                    </span>
                @endif
                @if($appointment->practitioner)
                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] font-medium rounded bg-indigo-50 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                        <x-heroicon-o-user-circle class="w-2.5 h-2.5" />
                        {{ $appointment->practitioner->full_name }}
                    </span>
                @endif
            @endif
        </div>
    </div>

    {{-- Compact Action Buttons --}}
    @if(($showCheckIn && $canCheckIn) || ($showPayment && $canRecordPayment) || $canCheckout)
        <div class="px-2 pb-2 flex gap-1">
            @if($showCheckIn && $canCheckIn)
                <x-filament::button
                    wire:click="checkInAppointment('{{ $appointment->id }}')"
                    wire:loading.attr="disabled"
                    color="warning"
                    size="xs"
                    class="flex-1"
                >
                    <span wire:loading.remove wire:target="checkInAppointment('{{ $appointment->id }}')">
                        {{ __('booking::reception.actions.check_in') }}
                    </span>
                    <span wire:loading wire:target="checkInAppointment('{{ $appointment->id }}')">...</span>
                </x-filament::button>
            @endif

            @if($showPayment && $canRecordPayment)
                <x-filament::button
                    :href="route('filament.tenant.resources.appointments.view', ['record' => $appointment->id])"
                    tag="a"
                    color="success"
                    size="xs"
                    class="flex-1"
                >
                    {{ __('booking::reception.actions.record_payment') }}
                </x-filament::button>
            @endif

            @if($canVisitCheckout)
                <x-filament::button
                    :href="$visit->checkout_url"
                    tag="a"
                    color="success"
                    size="xs"
                    class="flex-1"
                >
                    {{ __('booking::reception.actions.checkout') }}
                </x-filament::button>
            @elseif($canLegacyCheckout)
                <x-filament::button
                    wire:click="goToCheckout('{{ $appointment->id }}')"
                    wire:loading.attr="disabled"
                    color="success"
                    size="xs"
                    class="flex-1"
                >
                    <span wire:loading.remove wire:target="goToCheckout('{{ $appointment->id }}')">
                        {{ __('booking::reception.actions.checkout') }}
                    </span>
                    <span wire:loading wire:target="goToCheckout('{{ $appointment->id }}')">...</span>
                </x-filament::button>
            @endif
        </div>
    @endif
</div>
