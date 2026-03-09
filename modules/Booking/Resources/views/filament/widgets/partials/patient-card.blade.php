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
    $canRecordPackagePayment = in_array($appointment->status, ['checked_in', 'in_progress']);

    // Package balance check - show badge if appointment has package_subscription_id OR isPackageSession
    $packageSubscription = ($appointment->package_subscription_id || $appointment->isPackageSession())
        ? $appointment->packageSubscription
        : null;
    $hasPackageBalance = $packageSubscription && $packageSubscription->hasBalance();
    $packageBalanceAmount = $packageSubscription?->balance_remaining_minor ?? 0;
    $packageName = $packageSubscription?->package?->getTranslation('name', app()->getLocale()) ?? '';

    // Check if appointment is completed and has a visit that can be checked out
    // Visit-based checkout: go to visit checkout page
    $currentVisit = $appointment->current_visit;
    $canCheckout = $showCheckout
        && $appointment->status === 'completed'
        && $currentVisit
        && $currentVisit->status === 'open';

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

<div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-all overflow-hidden">
    {{-- Clickable Card Area - Opens Appointment --}}
    <a href="{{ route('filament.tenant.resources.appointments.view', ['record' => $appointment->id]) }}"
       class="block cursor-pointer">
        {{-- Header with Time and Wait Time --}}
        <div class="flex items-center justify-between px-3 py-2 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-100 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
            <div class="flex items-center gap-2">
                <span class="text-sm font-bold text-primary-600 dark:text-primary-400">
                    {{ $appointment->start_time?->format('H:i') }}
                </span>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full {{ $statusInfo['color'] }}">
                    <x-dynamic-component :component="$statusInfo['icon']" class="w-3 h-3" />
                    {{ $statusInfo['label'] }}
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
        <div class="p-3 space-y-2 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
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
                    <a href="tel:{{ $appointment->patient->phone }}"
                       onclick="event.stopPropagation();"
                       class="text-xs text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">
                        {{ $appointment->patient->phone }}
                    </a>
                @endif
            </div>
            @php
                $patientBalance = $appointment->patient?->balance_minor ?? 0;
            @endphp
            @if($patientBalance != 0)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold rounded-full flex-shrink-0
                    {{ $patientBalance > 0 ? 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300' : 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300' }}"
                    title="{{ $patientBalance > 0 ? __('patients::patients.balance.owes') : __('patients::patients.balance.credit') }}"
                >
                    <x-heroicon-o-banknotes class="w-3 h-3" />
                    {{ number_format(abs($patientBalance) / 100, 2) }}
                </span>
            @endif
        </div>

        {{-- Package Info/Balance - Stop propagation to prevent card click --}}
        @if($packageSubscription)
            @if($hasPackageBalance)
                {{-- Has balance due - amber warning, clickable --}}
                <button
                   type="button"
                   wire:click="goToPackageInvoice('{{ $packageSubscription->id }}')"
                   onclick="event.preventDefault(); event.stopPropagation();"
                   class="w-full flex items-center gap-2 p-2 rounded-lg bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 hover:bg-amber-100 dark:hover:bg-amber-900/50 transition-colors text-left">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-amber-500" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-amber-800 dark:text-amber-200">
                            {{ __('booking::reception.package_balance_due') }}
                        </p>
                        <p class="text-xs text-amber-600 dark:text-amber-300 truncate">
                            {{ $packageName }}: <span class="font-bold">{{ number_format($packageBalanceAmount / 100, 2) }} {{ current_currency() }}</span>
                        </p>
                    </div>
                    <x-heroicon-o-arrow-right class="w-4 h-4 text-amber-500 flex-shrink-0" />
                </button>
            @else
                {{-- Fully paid - green success, clickable to view invoice --}}
                <button
                   type="button"
                   wire:click="goToPackageInvoice('{{ $packageSubscription->id }}')"
                   onclick="event.preventDefault(); event.stopPropagation();"
                   class="w-full flex items-center gap-2 p-2 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 hover:bg-green-100 dark:hover:bg-green-900/50 transition-colors text-left">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-green-800 dark:text-green-200">
                            {{ __('booking::reception.package_paid') }}
                        </p>
                        <p class="text-xs text-green-600 dark:text-green-300 truncate">
                            {{ $packageName }}: <span class="font-bold">0.00 {{ current_currency() }}</span>
                        </p>
                    </div>
                    <x-heroicon-o-arrow-right class="w-4 h-4 text-green-500 flex-shrink-0" />
                </button>
            @endif
        @endif

        {{-- Service --}}
        @if($appointment->service)
            <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                <x-heroicon-o-clipboard-document-list class="w-4 h-4 text-gray-400 flex-shrink-0" />
                <span class="truncate">{{ $appointment->service->name }}</span>
            </div>
        @endif

        {{-- Room & Doctor --}}
        <div class="flex flex-wrap gap-1.5">
            @if($showAssignActions && $canChangeRoomOrDoctor)
                {{-- Clickable Room Badge --}}
                <button
                    type="button"
                    wire:click="openRoomModal('{{ $appointment->id }}')"
                    onclick="event.preventDefault(); event.stopPropagation();"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-md border transition-colors cursor-pointer
                        {{ $appointment->room
                            ? 'bg-cyan-50 text-cyan-700 dark:bg-cyan-900/50 dark:text-cyan-300 border-cyan-200 dark:border-cyan-800 hover:bg-cyan-100 dark:hover:bg-cyan-900'
                            : 'bg-gray-50 text-gray-500 dark:bg-gray-800 dark:text-gray-400 border-gray-200 dark:border-gray-700 border-dashed hover:bg-gray-100 dark:hover:bg-gray-700' }}"
                    title="{{ __('booking::reception.actions.assign_room') }}"
                >
                    <x-heroicon-o-building-office class="w-3 h-3" />
                    {{ $appointment->room?->name ?? __('booking::reception.no_room') }}
                </button>

                {{-- Clickable Doctor Badge --}}
                <button
                    type="button"
                    wire:click="openDoctorModal('{{ $appointment->id }}')"
                    onclick="event.preventDefault(); event.stopPropagation();"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-md border transition-colors cursor-pointer
                        {{ $appointment->practitioner
                            ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 dark:hover:bg-indigo-900'
                            : 'bg-gray-50 text-gray-500 dark:bg-gray-800 dark:text-gray-400 border-gray-200 dark:border-gray-700 border-dashed hover:bg-gray-100 dark:hover:bg-gray-700' }}"
                    title="{{ __('booking::reception.actions.assign_doctor') }}"
                >
                    <x-heroicon-o-user-circle class="w-3 h-3" />
                    {{ $appointment->practitioner?->full_name ?? __('booking::reception.unassigned') }}
                </button>
            @else
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
            @endif
        </div>
    </div>
    </a>

    {{-- Action Buttons --}}
    @if(($showCheckIn && $canCheckIn) || ($showPayment && $canRecordPayment) || ($showPayment && $canRecordPackagePayment && $hasPackageBalance) || $canCheckout)
        <div class="px-3 pb-4 space-y-2">
            {{-- Check-in Button --}}
            @if($showCheckIn && $canCheckIn)
                <x-filament::button
                    wire:click="checkInAppointment('{{ $appointment->id }}')"
                    wire:loading.attr="disabled"
                    color="warning"
                    size="sm"
                    class="w-full"
                    icon="heroicon-o-check-circle"
                >
                    <span wire:loading.remove wire:target="checkInAppointment('{{ $appointment->id }}')">
                        {{ __('booking::reception.actions.check_in') }}
                    </span>
                    <span wire:loading wire:target="checkInAppointment('{{ $appointment->id }}')">
                        {{ __('booking::reception.actions.checking_in') }}...
                    </span>
                </x-filament::button>
            @endif

            {{-- Record Payment Button (shows for checked_in and in_progress with balance) --}}
            @if($showPayment && $canRecordPayment)
                <x-filament::button
                    :href="route('filament.tenant.resources.appointments.view', ['record' => $appointment->id])"
                    tag="a"
                    color="success"
                    size="sm"
                    class="w-full"
                    icon="heroicon-o-banknotes"
                >
                    {{ __('booking::reception.actions.record_payment') }}
                </x-filament::button>
            {{-- Record Package Payment Button (shows for package appointments with balance due) --}}
            @elseif($showPayment && $canRecordPackagePayment && $hasPackageBalance)
                <x-filament::button
                    wire:click="goToPackageInvoice('{{ $packageSubscription->id }}')"
                    color="success"
                    size="sm"
                    class="w-full"
                    icon="heroicon-o-banknotes"
                >
                    {{ __('booking::reception.actions.record_payment') }}
                </x-filament::button>
            @endif

            {{-- Checkout Button (shows for completed appointments with unpaid invoice) --}}
            @if($canCheckout)
                <x-filament::button
                    wire:click="goToCheckout('{{ $appointment->id }}')"
                    wire:loading.attr="disabled"
                    color="success"
                    size="sm"
                    class="w-full"
                    icon="heroicon-o-banknotes"
                >
                    <span wire:loading.remove wire:target="goToCheckout('{{ $appointment->id }}')">
                        {{ __('booking::reception.actions.checkout') }}
                    </span>
                    <span wire:loading wire:target="goToCheckout('{{ $appointment->id }}')">
                        {{ __('booking::reception.actions.loading') }}...
                    </span>
                </x-filament::button>
            @endif
        </div>
    @endif
</div>
