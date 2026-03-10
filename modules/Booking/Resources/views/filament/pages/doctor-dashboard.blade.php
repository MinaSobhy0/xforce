<x-filament-panels::page>
    {{-- Dashboard Controls: Date & Practitioner Selector --}}
    <div class="mb-6 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        <div class="flex flex-wrap items-center gap-4">
            {{-- Date Selector --}}
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                    <x-heroicon-o-calendar class="w-5 h-5" />
                    <span class="font-medium">{{ __('booking::dashboard.date.label') }}:</span>
                </div>
                <input
                    type="date"
                    wire:model.live="selectedDate"
                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm focus:border-primary-500 focus:ring-primary-500"
                />
                @if(!$this->isViewingToday())
                    <button
                        wire:click="$set('selectedDate', '{{ today()->format('Y-m-d') }}')"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 text-xs font-medium hover:bg-primary-200 dark:hover:bg-primary-900/50 transition-colors"
                    >
                        <x-heroicon-o-arrow-uturn-left class="w-3 h-3" />
                        {{ __('booking::dashboard.date.back_to_today') }}
                    </button>
                @endif
                @if($this->isViewingPastDate())
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-xs font-medium">
                        <x-heroicon-o-clock class="w-3 h-3" />
                        {{ __('booking::dashboard.date.past_date') }}
                    </span>
                @endif
            </div>

            {{-- Admin Practitioner Selector --}}
            @if($this->canSelectPractitioner())
                <div class="flex items-center gap-2 border-l border-gray-200 dark:border-gray-700 pl-4">
                    <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                        <x-heroicon-o-user-circle class="w-5 h-5" />
                        <span class="font-medium">{{ __('booking::dashboard.admin.viewing_as') }}:</span>
                    </div>
                    <select
                        wire:model.live="selectedPractitionerId"
                        class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm focus:border-primary-500 focus:ring-primary-500"
                    >
                        @foreach($this->getPractitioners() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @if($this->selectedPractitionerId != auth()->id())
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 text-xs font-medium">
                            <x-heroicon-o-eye class="w-3 h-3" />
                            {{ __('booking::dashboard.admin.viewing_other') }}
                        </span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Left Column: Appointment Queue --}}
        <div class="space-y-4">
            {{-- Appointment Queue --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-queue-list class="w-5 h-5" />
                        {{ __('booking::dashboard.queue.title') }}
                    </h3>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-800 max-h-[calc(100vh-320px)] overflow-y-auto">
                    @php $appointments = $this->getAppointmentsByStatus(); @endphp

                    {{-- In Progress (Show first - highest priority) --}}
                    @if($appointments['in_progress']->isNotEmpty())
                        <div class="px-4 py-2 bg-green-50 dark:bg-green-900/20">
                            <span class="text-xs font-medium text-green-700 dark:text-green-300 uppercase tracking-wide">
                                {{ __('booking::dashboard.queue.in_progress') }} ({{ $appointments['in_progress']->count() }})
                            </span>
                        </div>
                        @foreach($appointments['in_progress'] as $appointment)
                            <div class="px-4 py-3 bg-green-50/50 dark:bg-green-900/10 border-l-4 border-green-500">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div style="flex: 1;">
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ $appointment->patient->full_name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $appointment->start_time?->format('H:i') }} - {{ $appointment->service?->translated_name }}
                                        </div>
                                        {{-- Check package session FIRST (higher priority) --}}
                                        @if($appointment->is_package_session && $appointment->packageSubscription)
                                            @php
                                                $sub = $appointment->packageSubscription;
                                                $package = $sub->package;
                                                if ($package && !$package->relationLoaded('items')) {
                                                    $package->load('items');
                                                }
                                                $serviceItem = $package?->items?->firstWhere('service_id', $appointment->service_id);
                                                $pulsesPerSession = $serviceItem?->pulses_per_session ?? 0;
                                                // Item is pulse-based if consumption_type is 'pulses' (regardless of pulses_per_session)
                                                $isServicePulseBased = $serviceItem?->consumption_type === 'pulses';
                                                $sessionsUsed = $sub->getSessionsUsedByService($appointment->service_id);
                                                $totalQuantity = $serviceItem?->quantity ?? 0;
                                            @endphp
                                            <div class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 flex items-center gap-1">
                                                <x-heroicon-o-gift class="w-3 h-3" />
                                                @if($isServicePulseBased)
                                                    {{-- For pulse-based: if pulses_per_session is set, multiply; otherwise quantity IS total pulses --}}
                                                    @php
                                                        if ($pulsesPerSession > 0) {
                                                            $pulsesUsed = $sessionsUsed * $pulsesPerSession;
                                                            $totalPulses = $totalQuantity * $pulsesPerSession;
                                                        } else {
                                                            // quantity IS the total pulses, sessionsUsed tracks pulse consumption
                                                            $pulsesUsed = $sessionsUsed;
                                                            $totalPulses = $totalQuantity;
                                                        }
                                                    @endphp
                                                    {{ number_format($pulsesUsed) }}/{{ number_format($totalPulses) }} {{ __('packages::packages.labels.pulses') }}
                                                @else
                                                    {{ $sessionsUsed }}/{{ $totalQuantity }} {{ __('packages::packages.labels.sessions') }}
                                                @endif
                                            </div>
                                        @elseif($appointment->treatmentPlanAppointment)
                                            <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                Session {{ $appointment->treatmentPlanAppointment->session_number }} of {{ $appointment->treatmentPlanAppointment->item->recommended_sessions }}
                                            </div>
                                        @endif
                                    </div>
                                    <div style="flex-shrink: 0; margin-left: 16px;">
                                        <button
                                            wire:click="resumeSession('{{ $appointment->id }}')"
                                            wire:loading.attr="disabled"
                                            style="background-color: #16a34a; color: white; padding: 8px 16px; border-radius: 8px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer;"
                                            onmouseover="this.style.backgroundColor='#15803d'"
                                            onmouseout="this.style.backgroundColor='#16a34a'"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px;">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                            </svg>
                                            {{ __('booking::dashboard.actions.resume') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif

                    {{-- Checked In (Waiting) --}}
                    @if($appointments['checked_in']->isNotEmpty())
                        <div class="px-4 py-2 bg-amber-50 dark:bg-amber-900/20">
                            <span class="text-xs font-medium text-amber-700 dark:text-amber-300 uppercase tracking-wide">
                                {{ __('booking::dashboard.queue.checked_in') }} ({{ $appointments['checked_in']->count() }})
                            </span>
                        </div>
                        @foreach($appointments['checked_in'] as $appointment)
                            <div class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div style="flex: 1;">
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ $appointment->patient->full_name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $appointment->start_time?->format('H:i') }} - {{ $appointment->service?->translated_name }}
                                        </div>
                                        {{-- Past date indicator --}}
                                        @if($this->isViewingPastDate())
                                            <div class="text-xs text-gray-500 dark:text-gray-500 mt-1 italic">
                                                {{ __('booking::dashboard.date.past_appointment') }}
                                            </div>
                                        @endif
                                        {{-- Check package session FIRST (higher priority) --}}
                                        @if($appointment->is_package_session && $appointment->packageSubscription)
                                            @php
                                                $sub = $appointment->packageSubscription;
                                                $package = $sub->package;
                                                if ($package && !$package->relationLoaded('items')) {
                                                    $package->load('items');
                                                }
                                                $serviceItem = $package?->items?->firstWhere('service_id', $appointment->service_id);
                                                $pulsesPerSession = $serviceItem?->pulses_per_session ?? 0;
                                                // Item is pulse-based if consumption_type is 'pulses' (regardless of pulses_per_session)
                                                $isServicePulseBased = $serviceItem?->consumption_type === 'pulses';
                                                $sessionsUsed = $sub->getSessionsUsedByService($appointment->service_id);
                                                $totalQuantity = $serviceItem?->quantity ?? 0;
                                            @endphp
                                            <div class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 flex items-center gap-1">
                                                <x-heroicon-o-gift class="w-3 h-3" />
                                                @if($isServicePulseBased)
                                                    {{-- For pulse-based: if pulses_per_session is set, multiply; otherwise quantity IS total pulses --}}
                                                    @php
                                                        if ($pulsesPerSession > 0) {
                                                            $pulsesUsed = $sessionsUsed * $pulsesPerSession;
                                                            $totalPulses = $totalQuantity * $pulsesPerSession;
                                                        } else {
                                                            // quantity IS the total pulses, sessionsUsed tracks pulse consumption
                                                            $pulsesUsed = $sessionsUsed;
                                                            $totalPulses = $totalQuantity;
                                                        }
                                                    @endphp
                                                    {{ number_format($pulsesUsed) }}/{{ number_format($totalPulses) }} {{ __('packages::packages.labels.pulses') }}
                                                @else
                                                    {{ $sessionsUsed }}/{{ $totalQuantity }} {{ __('packages::packages.labels.sessions') }}
                                                @endif
                                            </div>
                                        @elseif($appointment->treatmentPlanAppointment)
                                            <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                Session {{ $appointment->treatmentPlanAppointment->session_number }} of {{ $appointment->treatmentPlanAppointment->item->recommended_sessions }}
                                            </div>
                                        @endif
                                    </div>
                                    <div style="flex-shrink: 0; margin-left: 16px; display: flex; align-items: center; gap: 8px;">
                                        @if($this->isViewingPastDate())
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-xs font-medium">
                                                <x-heroicon-o-x-circle class="w-3 h-3" />
                                                {{ __('booking::dashboard.status.missed') }}
                                            </span>
                                        @endif
                                        <button
                                            wire:click="rescheduleAppointment('{{ $appointment->id }}')"
                                            wire:loading.attr="disabled"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-xs font-medium hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                                        >
                                            <x-heroicon-o-calendar-days class="w-3 h-3" />
                                            {{ __('booking::dashboard.actions.reschedule') }}
                                        </button>
                                        <button
                                            wire:click="startSession('{{ $appointment->id }}')"
                                            wire:loading.attr="disabled"
                                            style="background-color: #f97316; color: white; padding: 6px 12px; border-radius: 8px; font-weight: 500; border: none; cursor: pointer;"
                                            onmouseover="this.style.backgroundColor='#ea580c'"
                                            onmouseout="this.style.backgroundColor='#f97316'"
                                        >
                                            {{ __('booking::dashboard.actions.start') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif

                    {{-- Confirmed --}}
                    @if($appointments['confirmed']->isNotEmpty())
                        <div class="px-4 py-2 bg-purple-50 dark:bg-purple-900/20">
                            <span class="text-xs font-medium text-purple-700 dark:text-purple-300 uppercase tracking-wide">
                                {{ __('booking::dashboard.queue.confirmed') }} ({{ $appointments['confirmed']->count() }})
                            </span>
                        </div>
                        @foreach($appointments['confirmed'] as $appointment)
                            <div class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div style="flex: 1;">
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ $appointment->patient->full_name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $appointment->start_time?->format('H:i') }} - {{ $appointment->service?->translated_name }}
                                        </div>
                                        {{-- Check package session FIRST (higher priority) --}}
                                        @if($appointment->is_package_session && $appointment->packageSubscription)
                                            @php
                                                $sub = $appointment->packageSubscription;
                                                $package = $sub->package;
                                                if ($package && !$package->relationLoaded('items')) {
                                                    $package->load('items');
                                                }
                                                $serviceItem = $package?->items?->firstWhere('service_id', $appointment->service_id);
                                                $pulsesPerSession = $serviceItem?->pulses_per_session ?? 0;
                                                // Item is pulse-based if consumption_type is 'pulses' (regardless of pulses_per_session)
                                                $isServicePulseBased = $serviceItem?->consumption_type === 'pulses';
                                                $sessionsUsed = $sub->getSessionsUsedByService($appointment->service_id);
                                                $totalQuantity = $serviceItem?->quantity ?? 0;
                                            @endphp
                                            <div class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 flex items-center gap-1">
                                                <x-heroicon-o-gift class="w-3 h-3" />
                                                @if($isServicePulseBased)
                                                    {{-- For pulse-based: if pulses_per_session is set, multiply; otherwise quantity IS total pulses --}}
                                                    @php
                                                        if ($pulsesPerSession > 0) {
                                                            $pulsesUsed = $sessionsUsed * $pulsesPerSession;
                                                            $totalPulses = $totalQuantity * $pulsesPerSession;
                                                        } else {
                                                            // quantity IS the total pulses, sessionsUsed tracks pulse consumption
                                                            $pulsesUsed = $sessionsUsed;
                                                            $totalPulses = $totalQuantity;
                                                        }
                                                    @endphp
                                                    {{ number_format($pulsesUsed) }}/{{ number_format($totalPulses) }} {{ __('packages::packages.labels.pulses') }}
                                                @else
                                                    {{ $sessionsUsed }}/{{ $totalQuantity }} {{ __('packages::packages.labels.sessions') }}
                                                @endif
                                            </div>
                                        @elseif($appointment->treatmentPlanAppointment)
                                            <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                Session {{ $appointment->treatmentPlanAppointment->session_number }} of {{ $appointment->treatmentPlanAppointment->item->recommended_sessions }}
                                            </div>
                                        @endif
                                    </div>
                                    <div style="flex-shrink: 0; margin-left: 16px; display: flex; align-items: center; gap: 8px;">
                                        @if($this->isViewingPastDate())
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-xs font-medium">
                                                <x-heroicon-o-x-circle class="w-3 h-3" />
                                                {{ __('booking::dashboard.status.missed') }}
                                            </span>
                                        @endif
                                        <button
                                            wire:click="rescheduleAppointment('{{ $appointment->id }}')"
                                            wire:loading.attr="disabled"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-xs font-medium hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                                        >
                                            <x-heroicon-o-calendar-days class="w-3 h-3" />
                                            {{ __('booking::dashboard.actions.reschedule') }}
                                        </button>
                                        <button
                                            wire:click="startSession('{{ $appointment->id }}')"
                                            wire:loading.attr="disabled"
                                            style="background-color: #7c3aed; color: white; padding: 6px 12px; border-radius: 8px; font-weight: 500; border: none; cursor: pointer;"
                                            onmouseover="this.style.backgroundColor='#6d28d9'"
                                            onmouseout="this.style.backgroundColor='#7c3aed'"
                                        >
                                            {{ __('booking::dashboard.actions.start') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif

                    {{-- Empty State --}}
                    @if($appointments['checked_in']->isEmpty() && $appointments['in_progress']->isEmpty() && $appointments['confirmed']->isEmpty())
                        <div class="px-4 py-6 text-center">
                            <x-heroicon-o-calendar class="w-8 h-8 mx-auto text-gray-300 dark:text-gray-600" />
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('booking::dashboard.queue.empty') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right Column: Completed Sessions --}}
        <div class="space-y-4">
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500" />
                        {{ __('booking::dashboard.queue.completed') }}
                    </h3>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-800 max-h-[calc(100vh-320px)] overflow-y-auto">
                    @php $appointments = $this->getAppointmentsByStatus(); @endphp

                    @if($appointments['completed']->isNotEmpty())
                        @foreach($appointments['completed'] as $appointment)
                            <div class="px-4 py-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ $appointment->patient->full_name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $appointment->start_time?->format('H:i') }} - {{ $appointment->service?->translated_name }}
                                        </div>
                                        {{-- Check package session FIRST (higher priority) --}}
                                        @if($appointment->is_package_session && $appointment->packageSubscription)
                                            @php
                                                $sub = $appointment->packageSubscription;
                                                $package = $sub->package;
                                                if ($package && !$package->relationLoaded('items')) {
                                                    $package->load('items');
                                                }
                                                $serviceItem = $package?->items?->firstWhere('service_id', $appointment->service_id);
                                                $pulsesPerSession = $serviceItem?->pulses_per_session ?? 0;
                                                // Item is pulse-based if consumption_type is 'pulses' (regardless of pulses_per_session)
                                                $isServicePulseBased = $serviceItem?->consumption_type === 'pulses';
                                                $sessionsUsed = $sub->getSessionsUsedByService($appointment->service_id);
                                                $totalQuantity = $serviceItem?->quantity ?? 0;
                                            @endphp
                                            <div class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 flex items-center gap-1">
                                                <x-heroicon-o-gift class="w-3 h-3" />
                                                @if($isServicePulseBased)
                                                    {{-- For pulse-based: if pulses_per_session is set, multiply; otherwise quantity IS total pulses --}}
                                                    @php
                                                        if ($pulsesPerSession > 0) {
                                                            $pulsesUsed = $sessionsUsed * $pulsesPerSession;
                                                            $totalPulses = $totalQuantity * $pulsesPerSession;
                                                        } else {
                                                            // quantity IS the total pulses, sessionsUsed tracks pulse consumption
                                                            $pulsesUsed = $sessionsUsed;
                                                            $totalPulses = $totalQuantity;
                                                        }
                                                    @endphp
                                                    {{ number_format($pulsesUsed) }}/{{ number_format($totalPulses) }} {{ __('packages::packages.labels.pulses') }}
                                                @else
                                                    {{ $sessionsUsed }}/{{ $totalQuantity }} {{ __('packages::packages.labels.sessions') }}
                                                @endif
                                            </div>
                                        @elseif($appointment->treatmentPlanAppointment)
                                            <div class="text-xs text-green-600 dark:text-green-400 mt-1">
                                                Session {{ $appointment->treatmentPlanAppointment->session_number }} of {{ $appointment->treatmentPlanAppointment->item->recommended_sessions }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button
                                            wire:click="viewSession('{{ $appointment->id }}')"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-xs font-medium hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                                        >
                                            <x-heroicon-o-eye class="w-3 h-3" />
                                            {{ __('booking::dashboard.actions.view') }}
                                        </button>
                                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500" />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="px-4 py-6 text-center">
                            <x-heroicon-o-clipboard-document-check class="w-8 h-8 mx-auto text-gray-300 dark:text-gray-600" />
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('booking::dashboard.workspace.no_session') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
