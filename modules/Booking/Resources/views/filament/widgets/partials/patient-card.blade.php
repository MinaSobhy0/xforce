@php
    $waitTime = $this->calculateWaitTime($appointment);
    $waitClass = $waitTime ? ($waitTimeClasses[$waitTime['severity']] ?? $waitTimeClasses['success']) : '';
    $showRoom = $showRoom ?? true;
    $showCheckIn = $showCheckIn ?? false;
    $canCheckIn = $this->canCheckIn($appointment);

    // Status colors and labels
    $statusConfig = [
        'scheduled' => ['color' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300', 'label' => __('booking::reception.statuses.scheduled')],
        'confirmed' => ['color' => 'bg-blue-100 text-blue-700 dark:bg-blue-800 dark:text-blue-300', 'label' => __('booking::reception.statuses.confirmed')],
        'checked_in' => ['color' => 'bg-amber-100 text-amber-700 dark:bg-amber-800 dark:text-amber-300', 'label' => __('booking::reception.statuses.checked_in')],
        'in_progress' => ['color' => 'bg-purple-100 text-purple-700 dark:bg-purple-800 dark:text-purple-300', 'label' => __('booking::reception.statuses.in_progress')],
        'completed' => ['color' => 'bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-300', 'label' => __('booking::reception.statuses.completed')],
        'cancelled' => ['color' => 'bg-red-100 text-red-700 dark:bg-red-800 dark:text-red-300', 'label' => __('booking::reception.statuses.cancelled')],
        'no_show' => ['color' => 'bg-red-100 text-red-700 dark:bg-red-800 dark:text-red-300', 'label' => __('booking::reception.statuses.no_show')],
        'rescheduled' => ['color' => 'bg-orange-100 text-orange-700 dark:bg-orange-800 dark:text-orange-300', 'label' => __('booking::reception.statuses.rescheduled')],
    ];

    $status = $appointment->status ?? 'scheduled';
    $statusInfo = $statusConfig[$status] ?? $statusConfig['scheduled'];
@endphp

<div class="p-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-shadow">
    {{-- Patient Name --}}
    <div class="flex items-start justify-between gap-1 mb-1.5">
        <span class="text-xs font-semibold text-gray-900 dark:text-white truncate flex-1">
            {{ $appointment->patient?->full_name ?? __('booking::reception.unknown_patient') }}
        </span>
        @if($waitTime)
            <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold rounded {{ $waitClass }} flex-shrink-0">
                {{ $waitTime['formatted'] }}
            </span>
        @endif
    </div>

    {{-- Status & Time --}}
    <div class="flex flex-wrap items-center gap-1 mb-1.5">
        <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium rounded {{ $statusInfo['color'] }}">
            {{ $statusInfo['label'] }}
        </span>
        <span class="text-[10px] text-gray-500 dark:text-gray-400">
            {{ $appointment->start_time?->format('H:i') }}
        </span>
    </div>

    {{-- Service --}}
    @if($appointment->service)
        <div class="text-[10px] text-gray-500 dark:text-gray-400 truncate mb-1">
            {{ $appointment->service->name }}
        </div>
    @endif

    {{-- Room & Doctor badges --}}
    <div class="flex flex-wrap gap-1 mb-1.5">
        @if($showRoom && $appointment->room)
            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] font-medium rounded bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                <x-heroicon-o-building-office class="w-2.5 h-2.5" />
                {{ Str::limit($appointment->room->name, 10) }}
            </span>
        @endif
        @if($appointment->practitioner)
            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] font-medium rounded bg-primary-100 text-primary-700 dark:bg-primary-800 dark:text-primary-300">
                <x-heroicon-o-user class="w-2.5 h-2.5" />
                {{ Str::limit($appointment->practitioner->full_name, 10) }}
            </span>
        @endif
    </div>

    {{-- Check-in Button --}}
    @if($showCheckIn && $canCheckIn)
        <button
            type="button"
            wire:click="checkInAppointment('{{ $appointment->id }}')"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-50 cursor-not-allowed"
            class="w-full mt-1 inline-flex items-center justify-center gap-1 px-2 py-1 text-[10px] font-medium rounded bg-emerald-500 hover:bg-emerald-600 text-white transition-colors"
        >
            <x-heroicon-o-check class="w-3 h-3" />
            <span wire:loading.remove wire:target="checkInAppointment('{{ $appointment->id }}')">
                {{ __('booking::reception.actions.check_in') }}
            </span>
            <span wire:loading wire:target="checkInAppointment('{{ $appointment->id }}')">
                ...
            </span>
        </button>
    @endif
</div>
