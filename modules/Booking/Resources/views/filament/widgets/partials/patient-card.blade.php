@php
    $waitTime = $this->calculateWaitTime($appointment);
    $waitClass = $waitTime ? ($waitTimeClasses[$waitTime['severity']] ?? $waitTimeClasses['success']) : '';
    $showRoom = $showRoom ?? true;
    $showCheckIn = $showCheckIn ?? false;
    $canCheckIn = $this->canCheckIn($appointment);

    // Status colors and labels
    $statusConfig = [
        'scheduled' => ['color' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300', 'icon' => 'heroicon-o-clock'],
        'confirmed' => ['color' => 'bg-blue-100 text-blue-700 dark:bg-blue-800 dark:text-blue-300', 'icon' => 'heroicon-o-check'],
        'checked_in' => ['color' => 'bg-amber-100 text-amber-700 dark:bg-amber-800 dark:text-amber-300', 'icon' => 'heroicon-o-user-plus'],
        'in_progress' => ['color' => 'bg-purple-100 text-purple-700 dark:bg-purple-800 dark:text-purple-300', 'icon' => 'heroicon-o-play'],
        'completed' => ['color' => 'bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-300', 'icon' => 'heroicon-o-check-circle'],
        'cancelled' => ['color' => 'bg-red-100 text-red-700 dark:bg-red-800 dark:text-red-300', 'icon' => 'heroicon-o-x-circle'],
        'no_show' => ['color' => 'bg-red-100 text-red-700 dark:bg-red-800 dark:text-red-300', 'icon' => 'heroicon-o-user-minus'],
        'rescheduled' => ['color' => 'bg-orange-100 text-orange-700 dark:bg-orange-800 dark:text-orange-300', 'icon' => 'heroicon-o-arrow-path'],
    ];

    $status = $appointment->status ?? 'scheduled';
    $statusInfo = $statusConfig[$status] ?? $statusConfig['scheduled'];
@endphp

<div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-all overflow-hidden">
    {{-- Header with Time and Wait Time --}}
    <div class="flex items-center justify-between px-3 py-2 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-100 dark:border-gray-700">
        <div class="flex items-center gap-2">
            <span class="text-sm font-bold text-primary-600 dark:text-primary-400">
                {{ $appointment->start_time?->format('H:i') }}
            </span>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full {{ $statusInfo['color'] }}">
                <x-dynamic-component :component="$statusInfo['icon']" class="w-3 h-3" />
            </span>
        </div>
        @if($waitTime)
            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-bold rounded-full {{ $waitClass }}">
                <x-heroicon-o-clock class="w-3 h-3" />
                {{ $waitTime['formatted'] }}
            </span>
        @endif
    </div>

    {{-- Body --}}
    <div class="p-3 space-y-2">
        {{-- Patient Name --}}
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center flex-shrink-0">
                <x-heroicon-o-user class="w-4 h-4 text-primary-600 dark:text-primary-400" />
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                    {{ $appointment->patient?->full_name ?? __('booking::reception.unknown_patient') }}
                </div>
                @if($appointment->patient?->phone)
                    <a href="tel:{{ $appointment->patient->phone }}" class="text-xs text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">
                        {{ $appointment->patient->phone }}
                    </a>
                @endif
            </div>
        </div>

        {{-- Service --}}
        @if($appointment->service)
            <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                <x-heroicon-o-clipboard-document-list class="w-4 h-4 text-gray-400 flex-shrink-0" />
                <span class="truncate">{{ $appointment->service->name }}</span>
            </div>
        @endif

        {{-- Room & Doctor --}}
        <div class="flex flex-wrap gap-1.5">
            @if($showRoom && $appointment->room)
                <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-md bg-cyan-50 text-cyan-700 dark:bg-cyan-900/50 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800">
                    <x-heroicon-o-building-office class="w-3 h-3" />
                    {{ $appointment->room->name }}
                </span>
            @endif
            @if($appointment->practitioner)
                <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-md bg-indigo-50 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                    <x-heroicon-o-user-circle class="w-3 h-3" />
                    {{ $appointment->practitioner->full_name }}
                </span>
            @endif
        </div>
    </div>

    {{-- Check-in Button --}}
    @if($showCheckIn && $canCheckIn)
        <div class="px-3 pb-3">
            <button
                type="button"
                wire:click="checkInAppointment('{{ $appointment->id }}')"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50 cursor-not-allowed"
                class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white transition-colors shadow-sm"
            >
                <x-heroicon-o-check-circle class="w-4 h-4" />
                <span wire:loading.remove wire:target="checkInAppointment('{{ $appointment->id }}')">
                    {{ __('booking::reception.actions.check_in') }}
                </span>
                <span wire:loading wire:target="checkInAppointment('{{ $appointment->id }}')">
                    {{ __('booking::reception.actions.checking_in') }}...
                </span>
            </button>
        </div>
    @endif
</div>
