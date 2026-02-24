@php
    $waitTime = $this->calculateWaitTime($appointment);
    $waitClass = $waitTime ? ($waitTimeClasses[$waitTime['severity']] ?? $waitTimeClasses['success']) : '';
    $showRoom = $showRoom ?? true;

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

<div class="p-3 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-shadow">
    {{-- Patient Name & Status --}}
    <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-semibold text-gray-900 dark:text-white truncate flex-1">
            {{ $appointment->patient?->full_name ?? __('booking::reception.unknown_patient') }}
        </span>
    </div>

    {{-- Status Badge --}}
    <div class="mb-2">
        <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full {{ $statusInfo['color'] }}">
            {{ $statusInfo['label'] }}
        </span>
        @if($waitTime)
            <span class="inline-flex items-center px-2 py-0.5 text-xs font-bold rounded-full {{ $waitClass }} ms-1">
                <x-heroicon-o-clock class="w-3 h-3 me-1" />
                {{ $waitTime['formatted'] }}
            </span>
        @endif
    </div>

    {{-- Time & Service --}}
    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mb-2">
        <x-heroicon-o-clock class="w-3.5 h-3.5" />
        <span>{{ $appointment->start_time?->format('H:i') }}</span>
        @if($appointment->service)
            <span class="text-gray-300 dark:text-gray-600">|</span>
            <span class="truncate">{{ Str::limit($appointment->service->name, 15) }}</span>
        @endif
    </div>

    {{-- Room & Doctor badges --}}
    <div class="flex flex-wrap gap-1.5">
        @if($showRoom && $appointment->room)
            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                <x-heroicon-o-building-office class="w-3 h-3" />
                {{ $appointment->room->name }}
            </span>
        @endif
        @if($appointment->practitioner)
            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full bg-primary-100 text-primary-700 dark:bg-primary-800 dark:text-primary-300">
                <x-heroicon-o-user class="w-3 h-3" />
                {{ Str::limit($appointment->practitioner->full_name, 12) }}
            </span>
        @endif
    </div>
</div>
