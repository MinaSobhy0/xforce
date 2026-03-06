<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Appointment Info Bar --}}
        <div class="rounded-xl bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-700 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-800 flex items-center justify-center">
                        <x-heroicon-o-user class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <div class="font-semibold text-gray-900 dark:text-white text-lg">
                            {{ $patient?->full_name }}
                            @if($patient?->age)
                                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ $patient->age }} {{ __('booking::session.info.years') }})</span>
                            @endif
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400 flex items-center gap-2 flex-wrap">
                            <span>{{ $patient?->code }}</span>
                            <span class="text-gray-300 dark:text-gray-600">|</span>
                            <span>{{ $patient?->phone }}</span>
                            @if($patient?->occupation)
                                <span class="text-gray-300 dark:text-gray-600">|</span>
                                <span>{{ $patient->occupation }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-6 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('booking::session.info.service') }}:</span>
                        <span class="font-medium text-gray-900 dark:text-white ml-1">{{ $appointment?->service?->translated_name }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('booking::session.info.time') }}:</span>
                        <span class="font-medium text-gray-900 dark:text-white ml-1">{{ $appointment?->start_time?->format('H:i') }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('booking::session.info.room') }}:</span>
                        <span class="font-medium text-gray-900 dark:text-white ml-1">{{ $appointment?->room?->name ?? '-' }}</span>
                    </div>
                    @if($appointment?->treatmentPlanAppointment)
                        <div class="px-3 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-full font-medium">
                            {{ __('booking::session.info.session_number', ['current' => $appointment->treatmentPlanAppointment->session_number, 'total' => $appointment->treatmentPlanAppointment->item->recommended_sessions]) }}
                        </div>
                    @endif

                    {{-- Visit Badge --}}
                    @if($visit)
                        <div class="px-3 py-1 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded-full font-medium flex items-center gap-1.5">
                            <x-heroicon-o-ticket class="w-4 h-4" />
                            {{ $visit->code }}
                        </div>
                    @endif

                    {{-- Session Timer --}}
                    @if($sessionData?->session_started_at)
                        <div
                            x-data="{
                                startTime: {{ $sessionData->session_started_at->timestamp * 1000 }},
                                elapsed: 0,
                                timer: null,
                                init() {
                                    this.updateElapsed();
                                    this.timer = setInterval(() => this.updateElapsed(), 1000);
                                },
                                updateElapsed() {
                                    this.elapsed = Math.floor((Date.now() - this.startTime) / 1000);
                                },
                                get hours() {
                                    return Math.floor(this.elapsed / 3600);
                                },
                                get minutes() {
                                    return Math.floor((this.elapsed % 3600) / 60);
                                },
                                get seconds() {
                                    return this.elapsed % 60;
                                },
                                get display() {
                                    if (this.hours > 0) {
                                        return String(this.hours).padStart(2, '0') + ':' + String(this.minutes).padStart(2, '0') + ':' + String(this.seconds).padStart(2, '0');
                                    }
                                    return String(this.minutes).padStart(2, '0') + ':' + String(this.seconds).padStart(2, '0');
                                }
                            }"
                            x-init="init()"
                            class="flex items-center gap-2 px-3 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-full font-medium"
                        >
                            <x-heroicon-o-clock class="w-4 h-4" />
                            <span x-text="display" class="font-mono tabular-nums"></span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Alert Cards - Critical Safety Info --}}
        @if(!empty($this->getAllergies()) || !empty($this->getContraindications()) || $this->hasAmrAlerts())
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @if(!empty($this->getAllergies()))
                    <div class="rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 p-4">
                        <div class="flex items-center gap-2 text-red-700 dark:text-red-400 font-semibold mb-2">
                            <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                            {{ __('booking::session.alerts.allergies') }}
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($this->getAllergies() as $allergy)
                                <span class="px-2 py-1 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 rounded text-sm">{{ $allergy }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($this->getContraindications()))
                    <div class="rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 p-4">
                        <div class="flex items-center gap-2 text-amber-700 dark:text-amber-400 font-semibold mb-2">
                            <x-heroicon-o-shield-exclamation class="w-5 h-5" />
                            {{ __('booking::session.alerts.contraindications') }}
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($this->getContraindications() as $c)
                                <span class="px-2 py-1 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 rounded text-sm">
                                    {{ \Modules\Patients\Models\PatientMedicalHistory::CONTRAINDICATIONS[$c] ?? $c }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($this->hasAmrAlerts())
                    <div class="rounded-xl bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700 p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2 text-purple-700 dark:text-purple-400 font-semibold">
                                <x-heroicon-o-beaker class="w-5 h-5" />
                                {{ __('booking::session.alerts.amr_resistance') }}
                            </div>
                            @if($amrSummary?->has_critical_resistance)
                                <span class="px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded animate-pulse">{{ __('booking::session.alerts.critical') }}</span>
                            @endif
                        </div>
                        @php $mdroFlags = $this->getMdroFlags(); @endphp
                        @if(!empty($mdroFlags))
                            <div class="flex flex-wrap gap-1 mb-2">
                                @foreach($mdroFlags as $flag)
                                    <span class="px-2 py-0.5 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 rounded text-xs font-semibold">{{ $flag }}</span>
                                @endforeach
                            </div>
                        @endif
                        @php $resistances = $this->getKnownResistances(); @endphp
                        @if(!empty($resistances))
                            <div class="flex flex-wrap gap-1">
                                @foreach(array_slice($resistances, 0, 5) as $resistance)
                                    <span class="px-2 py-0.5 bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 rounded text-xs">
                                        {{ \Modules\Patients\Models\PatientAmrTest::getAntibioticLabel($resistance) }}
                                    </span>
                                @endforeach
                                @if(count($resistances) > 5)
                                    <span class="px-2 py-0.5 bg-purple-200 dark:bg-purple-800 text-purple-700 dark:text-purple-300 rounded text-xs font-medium">+{{ count($resistances) - 5 }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        {{-- Main 2-Column Layout: Medical Info + Treatment Plan --}}
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
            {{-- Medical Records --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 bg-primary-100 dark:bg-primary-900/30 rounded-lg">
                                <x-heroicon-o-clipboard-document-list class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                            </div>
                            {{ __('booking::session.sections.medical_info') }}
                        </div>
                        @if($patient)
                            <a href="{{ url('/admin/medical-profile?patient_id=' . $patient->id) }}"
                               target="_blank"
                               class="px-3 py-1.5 border border-primary-500 text-primary-600 dark:text-primary-400 rounded-lg text-sm font-medium hover:bg-primary-50 dark:hover:bg-primary-900/20 flex items-center gap-1.5 transition-colors">
                                <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                                {{ __('booking::session.medical.view_full_profile') }}
                            </a>
                        @endif
                    </div>
                </x-slot>

                @php
                    $criticalAllergies = $medicalProfile?->getCriticalAllergies() ?? collect();
                    $activeAllergies = $medicalProfile?->getActiveAllergies() ?? collect();
                    $activeContraindications = $medicalProfile?->getActiveContraindications() ?? collect();
                    $ongoingMeds = $medicalProfile?->getOngoingMedications() ?? collect();
                @endphp

                {{-- Key Info Row --}}
                <div class="grid grid-cols-2 gap-4 mb-5">
                    <div class="flex items-center gap-2">
                        <span class="text-gray-500 dark:text-gray-400 text-sm">{{ __('booking::session.medical.fitzpatrick') }}</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $medicalProfile?->fitzpatrick_short ?? '-' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-gray-500 dark:text-gray-400 text-sm">{{ __('booking::session.medical.blood_type') }}</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $medicalProfile?->blood_type ?? '-' }}</span>
                    </div>
                </div>

                {{-- Allergies Section --}}
                <div class="mb-4">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="p-1 rounded-full bg-red-100 dark:bg-red-900/30">
                            <x-heroicon-o-exclamation-circle class="w-4 h-4 text-red-500" />
                        </div>
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('booking::session.medical.allergies') }}</span>
                    </div>
                    @if($activeAllergies->isNotEmpty())
                        <div class="flex flex-wrap gap-1.5 ps-7">
                            @foreach($activeAllergies as $allergy)
                                <span class="px-2.5 py-1 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 rounded-lg text-xs font-medium border border-red-200 dark:border-red-800">
                                    {{ $allergy->allergen }}
                                    @if(in_array($allergy->severity, ['severe', 'life_threatening']))
                                        <x-heroicon-s-exclamation-triangle class="w-3 h-3 inline text-red-500" />
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    @else
                        <div class="flex items-center gap-2 ps-7 text-green-600 dark:text-green-400">
                            <x-heroicon-o-check class="w-4 h-4" />
                            <span class="text-sm">{{ __('booking::session.medical.no_allergies') }}</span>
                        </div>
                    @endif
                </div>

                {{-- Medications Section --}}
                <div class="mb-4">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="p-1 rounded-full bg-cyan-100 dark:bg-cyan-900/30">
                            <x-heroicon-o-beaker class="w-4 h-4 text-cyan-500" />
                        </div>
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('booking::session.medical.medications') }}</span>
                    </div>
                    @if($ongoingMeds->isNotEmpty())
                        <div class="flex flex-wrap gap-1.5 ps-7">
                            @foreach($ongoingMeds as $med)
                                <span class="px-2.5 py-1 bg-cyan-50 dark:bg-cyan-900/20 text-cyan-700 dark:text-cyan-300 rounded-lg text-xs font-medium border border-cyan-200 dark:border-cyan-800">
                                    {{ $med->medication_name }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <div class="flex items-center gap-2 ps-7 text-gray-500 dark:text-gray-400">
                            <span class="text-sm">{{ __('booking::session.medical.no_medications') }}</span>
                        </div>
                    @endif
                </div>

                {{-- Contraindications Section --}}
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <div class="p-1 rounded-full bg-amber-100 dark:bg-amber-900/30">
                            <x-heroicon-o-shield-exclamation class="w-4 h-4 text-amber-500" />
                        </div>
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('booking::session.medical.contraindications') }}</span>
                    </div>
                    @if($activeContraindications->isNotEmpty())
                        <div class="flex flex-wrap gap-1.5 ps-7">
                            @foreach($activeContraindications as $contra)
                                <span class="px-2.5 py-1 bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 rounded-lg text-xs font-medium border border-amber-200 dark:border-amber-800">
                                    {{ $contra->name }}
                                    @if($contra->block_booking)
                                        <x-heroicon-s-no-symbol class="w-3 h-3 inline text-red-500" />
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    @else
                        <div class="flex items-center gap-2 ps-7 text-green-600 dark:text-green-400">
                            <x-heroicon-o-check class="w-4 h-4" />
                            <span class="text-sm">{{ __('booking::session.medical.no_contraindications') }}</span>
                        </div>
                    @endif
                </div>

                {{-- Pregnancy/Breastfeeding Alerts --}}
                @if($medicalProfile?->is_pregnant || $medicalProfile?->is_breastfeeding)
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex flex-wrap gap-2">
                            @if($medicalProfile->is_pregnant)
                                <span class="px-3 py-1.5 bg-pink-100 dark:bg-pink-900/30 text-pink-700 dark:text-pink-300 rounded-full text-xs font-medium flex items-center gap-1.5">
                                    <x-heroicon-s-heart class="w-4 h-4" />
                                    {{ __('booking::session.medical.pregnant') }}
                                </span>
                            @endif
                            @if($medicalProfile->is_breastfeeding)
                                <span class="px-3 py-1.5 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded-full text-xs font-medium flex items-center gap-1.5">
                                    <x-heroicon-s-heart class="w-4 h-4" />
                                    {{ __('booking::session.medical.breastfeeding') }}
                                </span>
                            @endif
                        </div>
                    </div>
                @endif
            </x-filament::section>

            {{-- Current Treatment Plan --}}
            @php $currentPlan = $this->getCurrentTreatmentPlan(); @endphp
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-clipboard-document-list class="w-5 h-5 text-blue-500" />
                            {{ __('booking::session.sections.current_plan') }}
                        </div>
                        <button
                            type="button"
                            wire:click="openAddToPlanModal"
                            class="p-1.5 rounded-lg bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors"
                            title="{{ __('booking::session.actions.add_to_plan') }}"
                        >
                            <x-heroicon-o-plus class="w-5 h-5" />
                        </button>
                    </div>
                </x-slot>

                @if($currentPlan)
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-white text-sm">{{ $currentPlan->translated_name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $currentPlan->code }}</div>
                            </div>
                            <span class="text-lg font-bold text-primary-600">{{ $currentPlan->progress_percentage }}%</span>
                        </div>

                        <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                            <div class="h-full bg-primary-500 rounded-full transition-all" style="width: {{ $currentPlan->progress_percentage }}%"></div>
                        </div>

                        <div class="space-y-1">
                            @foreach($currentPlan->items as $item)
                                @php
                                    $isService = $item->item_type === 'service';
                                    $isProduct = $item->item_type === 'product';
                                    $itemName = $item->item_name ?: ($item->service?->translated_name ?? $item->itemable?->translated_name ?? $item->itemable?->name ?? '');
                                    $completed = $isService ? $item->completed_sessions : ($item->completed_quantity ?? 0);
                                    $total = $isService ? $item->recommended_sessions : ($item->quantity ?? 1);
                                    $isDone = $completed >= $total;
                                @endphp
                                <div class="flex items-center justify-between py-1.5 px-2 bg-gray-50 dark:bg-gray-800 rounded text-xs">
                                    <div class="flex items-center gap-2">
                                        @if($isProduct)
                                            <x-heroicon-o-cube class="w-3.5 h-3.5 text-amber-500" />
                                        @else
                                            <x-heroicon-o-sparkles class="w-3.5 h-3.5 text-primary-500" />
                                        @endif
                                        <span class="text-gray-700 dark:text-gray-300">{{ $itemName }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium {{ $isDone ? 'text-green-600' : 'text-gray-600 dark:text-gray-400' }}">
                                            {{ $completed }}/{{ $total }}
                                        </span>
                                        @if($isService)
                                            @if($item->service_id === $appointment->service_id)
                                                {{-- Current session indicator --}}
                                                <span class="px-1.5 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded text-xs">
                                                    {{ __('booking::session.plan.current') }}
                                                </span>
                                            @elseif($isDone)
                                                <x-heroicon-o-check-circle class="w-4 h-4 text-green-500" />
                                            @elseif($item->isCancelled())
                                                <span class="px-1.5 py-0.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded text-xs">
                                                    {{ __('booking::session.plan.cancelled') }}
                                                </span>
                                            @else
                                                {{-- Show status badge - check for active sessions or completed sessions --}}
                                                @php
                                                    $hasActiveSession = $item->planAppointments()
                                                        ->whereHas('appointment', fn($q) => $q->whereIn('status', ['in_progress', 'checked_in', 'confirmed']))
                                                        ->exists();
                                                    $hasStarted = $item->completed_sessions > 0 || $hasActiveSession;
                                                @endphp
                                                @if($hasStarted)
                                                    <span class="px-1.5 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded text-xs">
                                                        {{ __('booking::session.plan.in_progress') }}
                                                    </span>
                                                @else
                                                    <span class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 rounded text-xs">
                                                        {{ __('booking::session.plan.not_started') }}
                                                    </span>
                                                @endif
                                                {{-- Play button --}}
                                                <button
                                                    wire:click="startSessionForItem({{ $item->id }})"
                                                    wire:loading.attr="disabled"
                                                    class="p-1 rounded bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 hover:bg-primary-200 dark:hover:bg-primary-900/50 transition-colors"
                                                    title="{{ __('booking::session.actions.start_session') }}"
                                                >
                                                    <x-heroicon-o-play class="w-3.5 h-3.5" />
                                                </button>
                                            @endif
                                        @elseif($isProduct && !$item->is_delivered)
                                            <span class="px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 rounded text-xs">
                                                {{ __('booking::session.plan.pending_delivery') }}
                                            </span>
                                        @elseif($isProduct && $item->is_delivered)
                                            <x-heroicon-o-check-circle class="w-4 h-4 text-green-500" />
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="text-center py-4 text-gray-500 dark:text-gray-400 text-sm">
                        <x-heroicon-o-clipboard-document-list class="w-8 h-8 mx-auto mb-2 opacity-50" />
                        {{ __('booking::session.plan.no_plan') }}
                    </div>
                @endif
            </x-filament::section>
        </div>

        {{-- Equipment & Clinical Notes Section (always show if equipment available) --}}
        @if($this->hasEquipmentSection())
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                {{-- Equipment --}}
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-cog-6-tooth class="w-5 h-5 text-gray-400" />
                                {{ __('booking::session.sections.equipment') }}
                            </div>
                            @if(count($sessionEquipment) > 0)
                                <span class="text-xs text-gray-500">{{ count($sessionEquipment) }} {{ __('booking::session.equipment.devices') }}</span>
                            @endif
                        </div>
                    </x-slot>

                    @if(count($sessionEquipment) > 0)
                        <div class="space-y-3 mb-3">
                            @foreach($sessionEquipment as $index => $equipment)
                                @php
                                    $shotsRemaining = $equipment['shots_remaining'] ?? null;
                                    $shotsPercentage = $equipment['shots_percentage'] ?? null;
                                    $maxShots = $equipment['max_shots'] ?? null;
                                    $isMaintenanceDue = $equipment['is_maintenance_due'] ?? false;
                                    $nextMaintenance = $equipment['next_maintenance_at'] ?? null;
                                    $isLowShots = $maxShots && $shotsRemaining !== null && $shotsRemaining < ($maxShots * 0.1);
                                @endphp
                                <div class="border rounded-lg overflow-hidden {{ $isMaintenanceDue ? 'border-amber-400 dark:border-amber-500' : ($isLowShots ? 'border-red-400 dark:border-red-500' : 'border-gray-200 dark:border-gray-700') }} {{ $equipment['is_preset'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' }}">
                                    {{-- Equipment Header --}}
                                    <div class="flex items-center gap-2 p-2">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-gray-900 dark:text-white text-sm">{{ $equipment['name'] }}</span>
                                                <span class="text-xs text-gray-500">{{ $equipment['code'] ?? '' }}</span>
                                                @if($equipment['is_preset'])
                                                    <span class="px-1.5 py-0.5 text-xs bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 rounded">{{ __('booking::session.equipment.preset') }}</span>
                                                @endif
                                                @if($isMaintenanceDue)
                                                    <span class="px-1.5 py-0.5 text-xs bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 rounded animate-pulse">{{ __('booking::session.equipment.maintenance_due') }}</span>
                                                @endif
                                            </div>
                                            @if($equipment['category'] ?? null)
                                                <span class="text-xs text-gray-500">{{ \Modules\Equipment\Models\Equipment::CATEGORIES[$equipment['category']] ?? $equipment['category'] }}</span>
                                            @endif
                                        </div>
                                        @if(!$equipment['is_preset'])
                                            <button wire:click="removeEquipment('{{ $equipment['equipment_id'] }}')" class="p-1 text-gray-400 hover:text-red-500 rounded">
                                                <x-heroicon-o-x-mark class="w-4 h-4" />
                                            </button>
                                        @endif
                                    </div>

                                    {{-- Shot Tracking Section --}}
                                    @if($maxShots)
                                        <div class="px-2 pb-2">
                                            <div class="flex items-center justify-between text-xs mb-1">
                                                <span class="text-gray-600 dark:text-gray-400">{{ __('booking::session.equipment.shots_remaining') }}</span>
                                                <span class="{{ $isLowShots ? 'text-red-600 font-semibold' : 'text-gray-700 dark:text-gray-300' }}">
                                                    {{ number_format($shotsRemaining ?? 0) }} / {{ number_format($maxShots) }}
                                                </span>
                                            </div>
                                            <div class="h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                                @php
                                                    $usedPercentage = $shotsPercentage ?? 0;
                                                    $barColor = $usedPercentage > 90 ? 'bg-red-500' : ($usedPercentage > 75 ? 'bg-amber-500' : 'bg-green-500');
                                                @endphp
                                                <div class="{{ $barColor }} h-full rounded-full transition-all" style="width: {{ $usedPercentage }}%"></div>
                                            </div>
                                            @if($isLowShots)
                                                <div class="mt-1 flex items-center gap-1 text-xs text-red-600">
                                                    <x-heroicon-o-exclamation-triangle class="w-3 h-3" />
                                                    {{ __('booking::session.equipment.low_shots_warning') }}
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- Equipment Dynamic Parameters (all tracking parameters are dynamic) --}}
                                    @if(!empty($equipment['has_tracking']))
                                        @php $equipmentParams = $this->getEquipmentParametersByCategory($equipment['equipment_id']); @endphp
                                        @if(!empty($equipmentParams))
                                            <div class="border-t border-gray-200 dark:border-gray-700 p-2 bg-gray-50/50 dark:bg-gray-800/50">
                                                @foreach($equipmentParams as $category => $categoryData)
                                                    @if(count($categoryData['parameters']) > 0)
                                                        <div class="mb-2 last:mb-0">
                                                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5 flex items-center gap-1">
                                                                @if($category === 'energy')
                                                                    <x-heroicon-o-bolt class="w-3 h-3" />
                                                                @elseif($category === 'timing')
                                                                    <x-heroicon-o-clock class="w-3 h-3" />
                                                                @elseif($category === 'safety')
                                                                    <x-heroicon-o-shield-check class="w-3 h-3" />
                                                                @elseif($category === 'cooling')
                                                                    <x-heroicon-o-fire class="w-3 h-3 rotate-180" />
                                                                @elseif($category === 'delivery')
                                                                    <x-heroicon-o-arrow-path class="w-3 h-3" />
                                                                @endif
                                                                {{ $categoryData['label'] }}
                                                            </div>
                                                            <div class="grid grid-cols-2 gap-2">
                                                                @foreach($categoryData['parameters'] as $param)
                                                                    <div>
                                                                        <label class="block text-xs text-gray-600 dark:text-gray-400 mb-0.5">
                                                                            {{ $param->name }}
                                                                            @if($param->unit)<span class="text-gray-400">({{ $param->unit }})</span>@endif
                                                                            @if($param->is_required)<span class="text-red-500">*</span>@endif
                                                                            @if($param->is_cumulative)
                                                                                <span class="text-xs text-blue-500" title="{{ __('booking::session.equipment.cumulative_hint') }}">∑</span>
                                                                            @endif
                                                                        </label>
                                                                        @if($param->value_type === 'select')
                                                                            <select
                                                                                wire:change="updateEquipmentParameterValue('{{ $equipment['equipment_id'] }}', '{{ $param->parameter_key }}', $event.target.value)"
                                                                                class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-xs py-1"
                                                                            >
                                                                                <option value="">-</option>
                                                                                @foreach($param->options ?? [] as $option)
                                                                                    <option value="{{ $option['value'] }}" {{ ($equipmentParameterValues[$equipment['equipment_id']][$param->parameter_key] ?? '') == $option['value'] ? 'selected' : '' }}>{{ $option['label'] }}</option>
                                                                                @endforeach
                                                                            </select>
                                                                        @elseif($param->value_type === 'boolean')
                                                                            <label class="flex items-center gap-2">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    {{ ($equipmentParameterValues[$equipment['equipment_id']][$param->parameter_key] ?? $param->default_value) ? 'checked' : '' }}
                                                                                    wire:change="updateEquipmentParameterValue('{{ $equipment['equipment_id'] }}', '{{ $param->parameter_key }}', $event.target.checked)"
                                                                                    class="rounded border-gray-300 text-primary-600"
                                                                                />
                                                                                <span class="text-xs text-gray-600 dark:text-gray-400">{{ __('Yes') }}</span>
                                                                            </label>
                                                                        @else
                                                                            <input
                                                                                type="{{ in_array($param->value_type, ['integer', 'decimal']) ? 'number' : 'text' }}"
                                                                                value="{{ $equipmentParameterValues[$equipment['equipment_id']][$param->parameter_key] ?? $param->default_value }}"
                                                                                wire:change="updateEquipmentParameterValue('{{ $equipment['equipment_id'] }}', '{{ $param->parameter_key }}', $event.target.value)"
                                                                                class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-xs py-1"
                                                                                @if($param->min_value !== null) min="{{ $param->min_value }}" @endif
                                                                                @if($param->max_value !== null) max="{{ $param->max_value }}" @endif
                                                                                @if($param->step) step="{{ $param->step }}" @elseif($param->value_type === 'decimal') step="0.01" @endif
                                                                                placeholder="{{ $param->default_value ?? '' }}"
                                                                            />
                                                                        @endif
                                                                        @if($param->description)
                                                                            <p class="text-xs text-gray-400 mt-0.5">{{ $param->description }}</p>
                                                                        @endif
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="border-t border-gray-200 dark:border-gray-700 p-2 text-center text-xs text-gray-500">
                                                {{ __('booking::session.equipment.no_tracking_params') }}
                                            </div>
                                        @endif
                                    @endif

                                    {{-- Maintenance Info --}}
                                    @if($nextMaintenance)
                                        <div class="border-t border-gray-200 dark:border-gray-700 px-2 py-1 bg-gray-100/50 dark:bg-gray-800/50">
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="text-gray-500">{{ __('booking::session.equipment.next_maintenance') }}</span>
                                                <span class="{{ $isMaintenanceDue ? 'text-amber-600 font-medium' : 'text-gray-600 dark:text-gray-400' }}">{{ $nextMaintenance }}</span>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @php $availableEquipment = $this->getAvailableEquipment(); @endphp
                    @if($availableEquipment->isNotEmpty())
                        <div class="flex items-center gap-2 p-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <select wire:model="newEquipmentId" class="flex-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                                <option value="">{{ __('booking::session.equipment.add_equipment') }}</option>
                                @foreach($availableEquipment as $eq)
                                    <option value="{{ $eq->id }}">{{ $eq->name }}</option>
                                @endforeach
                            </select>
                            <x-filament::button wire:click="addEquipment" size="sm">
                                <x-heroicon-o-plus class="w-4 h-4" />
                            </x-filament::button>
                        </div>
                    @endif
                </x-filament::section>

            </div>

            {{-- Service Parameters --}}
            @php $parameters = $this->getServiceParameters(); @endphp
            @if(!empty($parameters))
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-adjustments-horizontal class="w-5 h-5 text-gray-400" />
                            {{ __('booking::session.sections.parameters') }}
                        </div>
                    </x-slot>

                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                        @foreach($parameters as $param)
                            @php
                                $key = $param['key'] ?? '';
                                $type = $param['type'] ?? 'text';
                                $label = is_array($param['label'] ?? '') ? ($param['label'][app()->getLocale()] ?? $param['label']['en'] ?? $key) : ($param['label'] ?? $key);
                                $unit = $param['unit'] ?? null;
                            @endphp
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ $label }}@if($unit) <span class="text-gray-400">({{ $unit }})</span>@endif
                                </label>
                                @if($type === 'select')
                                    <select wire:model.live="parameterValues.{{ $key }}" wire:change="updateParameterValue('{{ $key }}', $event.target.value)" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                                        <option value="">-</option>
                                        @foreach($param['options'] ?? [] as $option)
                                            <option value="{{ $option['value'] }}">{{ is_array($option['label'] ?? '') ? ($option['label'][app()->getLocale()] ?? $option['label']['en'] ?? $option['value']) : ($option['label'] ?? $option['value']) }}</option>
                                        @endforeach
                                    </select>
                                @elseif($type === 'boolean')
                                    <input type="checkbox" wire:model.live="parameterValues.{{ $key }}" wire:change="updateParameterValue('{{ $key }}', $event.target.checked)" class="rounded border-gray-300 text-primary-600" />
                                @else
                                    <input type="{{ in_array($type, ['number', 'decimal']) ? 'number' : 'text' }}" wire:model.blur="parameterValues.{{ $key }}" wire:change="updateParameterValue('{{ $key }}', $event.target.value)" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm" @if(isset($param['min'])) min="{{ $param['min'] }}" @endif @if(isset($param['max'])) max="{{ $param['max'] }}" @endif />
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif
        @endif

        {{-- Consumables & Products Row --}}
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
            {{-- Consumables Section --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-beaker class="w-5 h-5 text-orange-500" />
                        {{ __('booking::session.sections.consumables') }}
                        @if(count($sessionConsumables) > 0)
                            <span class="text-xs text-gray-500">({{ count($sessionConsumables) }})</span>
                        @endif
                    </div>
                </x-slot>

                <div class="flex gap-2 mb-3 p-2 bg-gray-50 dark:bg-gray-800 rounded-lg"
                    x-data="{
                        search: '',
                        open: false,
                        items: @js($this->getAvailableConsumables()->map(fn($p) => ['id' => $p->id, 'name' => $p->getTranslation('name', app()->getLocale())])->values()->toArray()),
                        get filtered() {
                            if (!this.search) return this.items;
                            return this.items.filter(item => item.name.toLowerCase().includes(this.search.toLowerCase()));
                        },
                        select(id) {
                            $wire.set('newConsumableId', id);
                            this.open = false;
                            this.search = this.items.find(i => i.id == id)?.name || '';
                        }
                    }"
                    @click.outside="open = false"
                >
                    <div class="flex-1 relative">
                        <input
                            type="text"
                            x-model="search"
                            @focus="open = true"
                            @input="open = true"
                            placeholder="{{ __('booking::session.consumables.select') }}"
                            class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm"
                        />
                        <div x-show="open && filtered.length > 0" x-cloak class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded shadow-lg max-h-48 overflow-y-auto">
                            <template x-for="item in filtered" :key="item.id">
                                <button type="button" @click="select(item.id)" class="w-full px-3 py-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-700" x-text="item.name"></button>
                            </template>
                        </div>
                    </div>
                    <input type="number" wire:model="newConsumableQty" class="w-16 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm text-center" min="0.1" step="0.1" placeholder="Qty" />
                    <x-filament::button wire:click="addConsumable" size="sm">
                        <x-heroicon-o-plus class="w-4 h-4" />
                    </x-filament::button>
                </div>

                @if(count($sessionConsumables) > 0)
                    <div class="space-y-1">
                        @foreach($sessionConsumables as $consumable)
                            <div class="flex items-center justify-between p-2 border border-gray-200 dark:border-gray-700 rounded text-sm">
                                <div>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $consumable['product_name'] }}</span>
                                    <span class="text-xs text-gray-500 ml-1">{{ $consumable['quantity'] }} {{ $consumable['unit'] }}</span>
                                </div>
                                <button type="button" wire:click="removeConsumable('{{ $consumable['id'] }}')" class="text-red-500 hover:text-red-700">
                                    <x-heroicon-o-x-mark class="w-4 h-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-3 text-gray-500 dark:text-gray-400 text-sm">{{ __('booking::session.consumables.none') }}</div>
                @endif
            </x-filament::section>

            {{-- Sell Product Section --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-shopping-cart class="w-5 h-5 text-green-500" />
                        {{ __('booking::session.sections.sell_product') }}
                        @if(count($sessionProducts) > 0)
                            <span class="text-xs text-gray-500">({{ count($sessionProducts) }})</span>
                        @endif
                    </div>
                </x-slot>

                <div class="flex gap-2 mb-3 p-2 bg-gray-50 dark:bg-gray-800 rounded-lg"
                    x-data="{
                        search: '',
                        open: false,
                        items: @js($this->getAvailableProducts()->map(fn($p) => ['id' => $p->id, 'name' => $p->getTranslation('name', app()->getLocale())])->values()->toArray()),
                        get filtered() {
                            if (!this.search) return this.items;
                            return this.items.filter(item => item.name.toLowerCase().includes(this.search.toLowerCase()));
                        },
                        select(id) {
                            $wire.set('newProductId', id);
                            this.open = false;
                            this.search = this.items.find(i => i.id == id)?.name || '';
                        }
                    }"
                    @click.outside="open = false"
                >
                    <div class="flex-1 relative">
                        <input
                            type="text"
                            x-model="search"
                            @focus="open = true"
                            @input="open = true"
                            placeholder="{{ __('booking::session.products.select') }}"
                            class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm"
                        />
                        <div x-show="open && filtered.length > 0" x-cloak class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded shadow-lg max-h-48 overflow-y-auto">
                            <template x-for="item in filtered" :key="item.id">
                                <button type="button" @click="select(item.id)" class="w-full px-3 py-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-700" x-text="item.name"></button>
                            </template>
                        </div>
                    </div>
                    <input type="number" wire:model="newProductQty" class="w-16 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm text-center" min="1" placeholder="Qty" />
                    <x-filament::button wire:click="addProduct" size="sm">
                        <x-heroicon-o-plus class="w-4 h-4" />
                    </x-filament::button>
                </div>

                @if(count($sessionProducts) > 0)
                    <div class="space-y-1">
                        @foreach($sessionProducts as $product)
                            <div class="flex items-center justify-between p-2 border border-gray-200 dark:border-gray-700 rounded text-sm">
                                <div class="flex-1">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $product['product_name'] }}</span>
                                    <span class="text-xs text-gray-500 ml-1">x{{ $product['quantity'] }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-700 dark:text-gray-300">{{ number_format($product['total_price'], 2) }}</span>
                                    <button type="button" wire:click="removeProduct('{{ $product['id'] }}')" class="text-red-500 hover:text-red-700">
                                        <x-heroicon-o-x-mark class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-3 text-gray-500 dark:text-gray-400 text-sm">{{ __('booking::session.products.none') }}</div>
                @endif
            </x-filament::section>

        </div>

        {{-- Prescription Section --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-clipboard-document-list class="w-5 h-5 text-blue-500" />
                        {{ __('prescriptions::prescription.prescription') }}
                    </div>
                    @php $existingPrescriptions = $this->getAppointmentPrescriptions(); @endphp
                    @if($existingPrescriptions->count() > 0)
                        <span class="text-sm text-gray-500">{{ $existingPrescriptions->count() }} {{ __('prescriptions::prescription.prescriptions') }}</span>
                    @endif
                </div>
            </x-slot>

            {{-- Existing Prescriptions --}}
            @if($existingPrescriptions->count() > 0)
                <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($existingPrescriptions as $prescription)
                        <div class="p-3 border border-gray-200 dark:border-gray-700 rounded-lg {{ $prescription->status === 'finalized' ? 'bg-green-50 dark:bg-green-900/10' : 'bg-gray-50 dark:bg-gray-800' }}">
                            <div class="flex items-center justify-between mb-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-900 dark:text-white text-sm">{{ $prescription->prescription_number }}</span>
                                    <span class="px-2 py-0.5 text-xs rounded {{ $prescription->status === 'finalized' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-700' }}">{{ $prescription->status_label }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    @if($prescription->canPrint())
                                        <a href="{{ route('filament.tenant.prescriptions.print', ['prescription' => $prescription->id]) }}" target="_blank" class="p-1 text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded">
                                            <x-heroicon-o-printer class="w-4 h-4" />
                                        </a>
                                    @endif
                                    <a href="{{ route('filament.tenant.resources.prescriptions.view', $prescription) }}" class="p-1 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
                                        <x-heroicon-o-eye class="w-4 h-4" />
                                    </a>
                                </div>
                            </div>
                            <div class="text-xs text-gray-500">{{ $prescription->items->count() }} {{ __('prescriptions::prescription.fields.medications') }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- New Prescription Form --}}
            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ __('prescriptions::prescription.sections.new_prescription') }}</div>

                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('prescriptions::prescription.fields.diagnosis') }}</label>
                    <textarea wire:model="prescriptionDiagnosis" rows="2" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm"></textarea>
                </div>

                <div class="flex items-center justify-between mb-3">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('prescriptions::prescription.sections.medications') }}</label>
                    <button wire:click="addPrescriptionMedication" class="text-sm text-primary-600 hover:text-primary-700 flex items-center gap-1">
                        <x-heroicon-o-plus class="w-4 h-4" />
                        {{ __('prescriptions::prescription.actions.add_medication') }}
                    </button>
                </div>

                {{-- Quick Add from Catalog --}}
                @php $availableMedicines = $this->getAvailableMedicines(); @endphp
                @if($availableMedicines->count() > 0)
                    <div class="mb-3 p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <div class="flex gap-2">
                            <select id="medicine-catalog-select" class="flex-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm" x-data x-on:change="if($el.value) { $wire.addMedicineFromCatalog($el.value); $el.value=''; }">
                                <option value="">{{ __('prescriptions::prescription.catalog.quick_add') }}...</option>
                                @foreach($availableMedicines->groupBy('category') as $category => $medicines)
                                    <optgroup label="{{ __('prescriptions::prescription.categories.' . $category) }}">
                                        @foreach($medicines as $medicine)
                                            <option value="{{ $medicine->id }}">{{ $medicine->full_name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif

                @if(count($prescriptionMedications) > 0)
                    <div class="space-y-2 mb-3">
                        @foreach($prescriptionMedications as $index => $medication)
                            <div x-data="{ isCollapsed: true }" class="rounded-lg bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
                                <div class="flex items-center gap-2 px-3 py-2">
                                    <button type="button" @click="isCollapsed = !isCollapsed" class="text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5 rounded p-1">
                                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform" ::class="isCollapsed ? '-rotate-90' : ''" />
                                    </button>
                                    <button type="button" @click="isCollapsed = !isCollapsed" class="flex-1 text-left">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $medication['medication_name'] ?: __('prescriptions::prescription.fields.medication_name') . ' #' . ($index + 1) }}</span>
                                        @if($medication['dosage'] || $medication['frequency'])
                                            <span class="text-xs text-gray-500 ml-2">
                                                @if($medication['dosage']){{ $medication['dosage'] }}{{ $medication['dosage_unit'] ?? 'mg' }}@endif
                                                @if($medication['frequency']) - {{ $this->getPrescriptionFrequencies()[$medication['frequency']] ?? $medication['frequency'] }}@endif
                                            </span>
                                        @endif
                                    </button>
                                    <button type="button" wire:click="removePrescriptionMedication({{ $index }})" class="text-gray-400 hover:text-red-500 p-1">
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                    </button>
                                </div>

                                <div x-show="!isCollapsed" x-collapse class="border-t border-gray-200 dark:border-white/10 p-3 space-y-3">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs text-gray-700 dark:text-gray-300 mb-1">{{ __('prescriptions::prescription.fields.medication_name') }}</label>
                                            <input type="text" wire:model="prescriptionMedications.{{ $index }}.medication_name" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-700 dark:text-gray-300 mb-1">{{ __('prescriptions::prescription.fields.generic_name') }}</label>
                                            <input type="text" wire:model="prescriptionMedications.{{ $index }}.generic_name" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" />
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-4 gap-2">
                                        <div>
                                            <label class="block text-xs text-gray-700 dark:text-gray-300 mb-1">{{ __('prescriptions::prescription.fields.form') }}</label>
                                            <select wire:model="prescriptionMedications.{{ $index }}.form" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                                @foreach($this->getPrescriptionForms() as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-700 dark:text-gray-300 mb-1">{{ __('prescriptions::prescription.fields.dosage') }}</label>
                                            <input type="text" wire:model="prescriptionMedications.{{ $index }}.dosage" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-700 dark:text-gray-300 mb-1">{{ __('prescriptions::prescription.fields.dosage_unit') }}</label>
                                            <select wire:model="prescriptionMedications.{{ $index }}.dosage_unit" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                                @foreach($this->getPrescriptionDosageUnits() as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-700 dark:text-gray-300 mb-1">{{ __('prescriptions::prescription.fields.route') }}</label>
                                            <select wire:model="prescriptionMedications.{{ $index }}.route" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                                @foreach($this->getPrescriptionRoutes() as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-4 gap-2">
                                        <div>
                                            <label class="block text-xs text-gray-700 dark:text-gray-300 mb-1">{{ __('prescriptions::prescription.fields.frequency') }}</label>
                                            <select wire:model="prescriptionMedications.{{ $index }}.frequency" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                                @foreach($this->getPrescriptionFrequencies() as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-700 dark:text-gray-300 mb-1">{{ __('prescriptions::prescription.fields.duration') }}</label>
                                            <input type="number" wire:model="prescriptionMedications.{{ $index }}.duration" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" min="1" />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-700 dark:text-gray-300 mb-1">{{ __('prescriptions::prescription.fields.duration_unit') }}</label>
                                            <select wire:model="prescriptionMedications.{{ $index }}.duration_unit" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                                @foreach($this->getPrescriptionDurationUnits() as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-700 dark:text-gray-300 mb-1">{{ __('prescriptions::prescription.fields.quantity') }}</label>
                                            <input type="number" wire:model="prescriptionMedications.{{ $index }}.quantity" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" min="1" />
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-xs text-gray-700 dark:text-gray-300 mb-1">{{ __('prescriptions::prescription.fields.instructions') }}</label>
                                            <select wire:model="prescriptionMedications.{{ $index }}.instructions" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                                <option value="">-</option>
                                                @foreach($this->getPrescriptionInstructions() as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-700 dark:text-gray-300 mb-1">{{ __('prescriptions::prescription.fields.special_instructions') }}</label>
                                            <input type="text" wire:model="prescriptionMedications.{{ $index }}.special_instructions" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <x-filament::button wire:click="savePrescriptionDraft" color="gray" size="sm">
                            {{ __('prescriptions::prescription.actions.save_draft') }}
                        </x-filament::button>
                        <x-filament::button wire:click="finalizePrescription" color="success" size="sm">
                            {{ __('prescriptions::prescription.actions.finalize') }}
                        </x-filament::button>
                    </div>
                @else
                    <div class="text-center py-4 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                        <x-heroicon-o-clipboard-document-list class="w-8 h-8 mx-auto text-gray-400 mb-2" />
                        <p class="text-sm text-gray-500 mb-2">{{ __('prescriptions::prescription.placeholders.no_medications') }}</p>
                        <button wire:click="addPrescriptionMedication" class="text-sm text-primary-600 hover:text-primary-700 font-medium">
                            {{ __('prescriptions::prescription.actions.add_medication') }}
                        </button>
                    </div>
                @endif
            </div>
        </x-filament::section>

        {{-- Session Notes --}}
        <x-filament::section collapsible>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-pencil-square class="w-5 h-5 text-gray-400" />
                        {{ __('booking::session.sections.notes') }}
                    </div>
                </x-slot>

                <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="flex gap-2">
                        <textarea wire:model="noteContent" rows="2" class="flex-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm" placeholder="{{ __('booking::session.notes.placeholder') }}"></textarea>
                        <div class="flex flex-col gap-1">
                            <select wire:model="noteType" class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-xs">
                                @foreach(\Modules\Patients\Models\PatientNote::TYPES as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-filament::button wire:click="addNote" size="sm">{{ __('booking::session.notes.add') }}</x-filament::button>
                        </div>
                    </div>
                </div>

                @php $notes = $this->getPatientNotes(); @endphp
                @if($notes->isNotEmpty())
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        @foreach($notes as $note)
                            <div class="p-2 border border-gray-200 dark:border-gray-700 rounded text-sm">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="px-1.5 py-0.5 text-xs font-medium rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">{{ \Modules\Patients\Models\PatientNote::TYPES[$note->type] ?? $note->type }}</span>
                                    <span class="text-xs text-gray-500">{{ $note->created_at->format('M d, H:i') }}</span>
                                </div>
                                <p class="text-gray-700 dark:text-gray-300 text-xs">{{ $note->content }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-3 text-gray-500 text-sm">{{ __('booking::session.notes.no_notes') }}</div>
                @endif
            </x-filament::section>

        {{-- Photos --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-camera class="w-5 h-5 text-gray-400" />
                    {{ __('booking::session.sections.photos') }}
                </div>
            </x-slot>

            <div class="mb-3 p-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <div class="flex gap-2 flex-wrap">
                    <input type="file" wire:model="photoUpload" accept="image/*" class="flex-1 min-w-[150px] text-xs border border-gray-300 dark:border-gray-600 rounded p-1 dark:bg-gray-700" />
                    <select wire:model="photoType" class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-xs">
                        @foreach(\Modules\Patients\Models\PatientPhoto::TYPES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-filament::button wire:click="uploadPhoto" size="sm">{{ __('booking::session.photos.upload') }}</x-filament::button>
                </div>
            </div>

            @php $photos = $this->getPatientPhotos(); @endphp
            @if($photos->isNotEmpty())
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                    @foreach($photos as $photo)
                        <div class="relative rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
                            {{-- Image --}}
                            <div style="aspect-ratio: 1; position: relative;">
                                @if($photo->getFirstMediaUrl('photos', 'thumb'))
                                    <a href="{{ $photo->getFirstMediaUrl('photos') }}" target="_blank">
                                        <img src="{{ $photo->getFirstMediaUrl('photos', 'thumb') }}" alt="{{ $photo->description }}" style="width: 100%; height: 100%; object-fit: cover;" />
                                    </a>
                                @else
                                    <div class="w-full h-full flex items-center justify-center bg-gray-100 dark:bg-gray-700">
                                        <x-heroicon-o-photo class="w-10 h-10 text-gray-400" />
                                    </div>
                                @endif
                                {{-- Delete button - only for photos from current appointment --}}
                                @if($photo->appointment_id === $this->appointment?->id)
                                    <button
                                        type="button"
                                        wire:click="deletePhoto('{{ $photo->id }}')"
                                        onclick="return confirm('Are you sure you want to delete this photo?')"
                                        style="position: absolute; top: 8px; right: 8px; padding: 6px; background: #ef4444; color: white; border-radius: 6px; z-index: 10;"
                                    >
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                    </button>
                                @endif
                            </div>
                            {{-- Info footer --}}
                            <div class="p-2 border-t border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $photo->type_label }}</span>
                                    <span class="text-xs text-gray-500">{{ $photo->taken_at?->format('M d') }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-3 text-gray-500 text-sm">{{ __('booking::session.photos.no_photos') }}</div>
            @endif
        </x-filament::section>

        {{-- Previous Visits & Create Plan Row --}}
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
            {{-- Previous Appointments --}}
            <x-filament::section collapsible>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-clock class="w-5 h-5 text-gray-400" />
                        {{ __('booking::session.sections.previous_visits') }}
                    </div>
                </x-slot>

                @php $previousAppts = $this->getPreviousAppointments(); @endphp
                @if($previousAppts->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($previousAppts as $appt)
                            <div class="flex items-center justify-between p-2 border border-gray-200 dark:border-gray-700 rounded text-sm">
                                <div>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $appt->service?->translated_name }}</span>
                                    <span class="text-xs text-gray-500 ml-1">{{ $appt->practitioner?->name }}</span>
                                </div>
                                <span class="text-xs text-gray-500">{{ $appt->date->format('M d, Y') }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-3 text-gray-500 text-sm">{{ __('booking::session.previous.no_visits') }}</div>
                @endif
            </x-filament::section>

            {{-- Create Treatment Plan --}}
            <x-filament::section collapsible>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-plus-circle class="w-5 h-5 text-green-500" />
                        {{ __('booking::session.sections.create_plan') }}
                    </div>
                </x-slot>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('booking::session.plan.name') }}</label>
                        <input type="text" wire:model="treatmentPlanData.name" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm" />
                    </div>

                    <div>
                        {{-- Column Headers --}}
                        <div class="flex gap-2 mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                            <div class="flex-1">{{ __('booking::session.plan.service') }}</div>
                            <div class="w-20 text-center">{{ __('booking::session.plan.sessions_count') }}</div>
                            <div class="w-20 text-center">{{ __('booking::session.plan.interval_days') }}</div>
                            <div class="w-6"></div>
                        </div>
                        @foreach($treatmentPlanData['services'] ?? [] as $index => $service)
                            <div class="flex gap-2 mb-2">
                                <select wire:model="treatmentPlanData.services.{{ $index }}.service_id" class="flex-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                                    <option value="">{{ __('booking::session.plan.select_service') }}</option>
                                    @foreach($this->getAvailableServices() as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                <input type="number" wire:model="treatmentPlanData.services.{{ $index }}.sessions" class="w-20 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm text-center" min="1" />
                                <input type="number" wire:model="treatmentPlanData.services.{{ $index }}.interval" class="w-20 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm text-center" min="1" />
                                @if(count($treatmentPlanData['services'] ?? []) > 1)
                                    <button type="button" wire:click="removeServiceRow({{ $index }})" class="text-red-500 p-1 w-6"><x-heroicon-o-x-mark class="w-4 h-4" /></button>
                                @else
                                    <div class="w-6"></div>
                                @endif
                            </div>
                        @endforeach
                        <button type="button" wire:click="addServiceRow" class="mt-2 w-full flex items-center justify-center gap-1 p-2 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-500 dark:text-gray-400 hover:border-primary-500 hover:text-primary-500 transition-colors">
                            <x-heroicon-o-plus class="w-5 h-5" />
                            {{ __('booking::session.plan.add_service') }}
                        </button>
                    </div>

                    <x-filament::button wire:click="createTreatmentPlan" class="w-full" size="sm">{{ __('booking::session.plan.create') }}</x-filament::button>
                </div>
            </x-filament::section>
        </div>

        {{-- Invoice Section --}}
        @if($this->hasBillableItems())
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-receipt-percent class="w-5 h-5 text-green-500" />
                            {{ __('booking::session.invoice.title') }}
                        </div>
                        <span class="text-lg font-bold text-green-600">
                            {{ number_format($this->getInvoiceTotal(), 2) }} {{ current_currency() }}
                        </span>
                    </div>
                </x-slot>

                {{-- Invoice Line Items --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-2 px-2 font-medium text-gray-600 dark:text-gray-400">{{ __('booking::session.invoice.item') }}</th>
                                <th class="text-center py-2 px-2 font-medium text-gray-600 dark:text-gray-400 w-20">{{ __('booking::session.invoice.qty') }}</th>
                                <th class="text-right py-2 px-2 font-medium text-gray-600 dark:text-gray-400 w-28">{{ __('booking::session.invoice.unit_price') }}</th>
                                <th class="text-right py-2 px-2 font-medium text-gray-600 dark:text-gray-400 w-36">{{ __('booking::session.invoice.discount') }}</th>
                                <th class="text-right py-2 px-2 font-medium text-gray-600 dark:text-gray-400 w-28">{{ __('booking::session.invoice.total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->getInvoiceItems() as $index => $item)
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    {{-- Item Name --}}
                                    <td class="py-3 px-2">
                                        <div class="flex items-center gap-2">
                                            @if($item['type'] === 'service')
                                                <x-heroicon-o-sparkles class="w-4 h-4 text-blue-500" />
                                            @elseif($item['type'] === 'active_service')
                                                <x-heroicon-o-play-circle class="w-4 h-4 text-green-500" />
                                            @elseif($item['type'] === 'plan_product')
                                                <x-heroicon-o-cube class="w-4 h-4 text-amber-500" />
                                            @else
                                                <x-heroicon-o-shopping-bag class="w-4 h-4 text-purple-500" />
                                            @endif
                                            <div>
                                                <div class="font-medium text-gray-900 dark:text-white">{{ $item['name'] }}</div>
                                                <div class="text-xs text-gray-500">{{ $item['description'] }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Quantity --}}
                                    <td class="py-3 px-2 text-center">
                                        @if($item['editable'] ?? true)
                                            <input
                                                type="number"
                                                value="{{ $item['quantity'] }}"
                                                x-data
                                                x-on:change="$wire.updateInvoiceItemQuantity('{{ $item['type'] }}', '{{ $item['id'] }}', $event.target.value)"
                                                class="w-16 text-center border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm py-1"
                                                min="0.01"
                                                step="0.01"
                                            />
                                        @else
                                            <span class="text-gray-700 dark:text-gray-300">{{ $item['quantity'] }}</span>
                                        @endif
                                    </td>

                                    {{-- Unit Price --}}
                                    <td class="py-3 px-2 text-right">
                                        @if($item['editable'] ?? true)
                                            <div class="relative" wire:loading.class="opacity-50">
                                                <input
                                                    type="number"
                                                    value="{{ $item['unit_price'] }}"
                                                    x-data
                                                    x-on:change="$wire.updateInvoiceItemPrice('{{ $item['type'] }}', '{{ $item['id'] }}', Math.round($event.target.value * 100))"
                                                    class="w-24 text-right border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm py-1"
                                                    step="0.01"
                                                    min="0"
                                                />
                                                <div wire:loading wire:target="updateInvoiceItemPrice" class="absolute inset-0 flex items-center justify-center">
                                                    <x-filament::loading-indicator class="w-4 h-4" />
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-gray-700 dark:text-gray-300">{{ number_format($item['unit_price'], 2) }}</span>
                                        @endif
                                    </td>

                                    {{-- Discount --}}
                                    <td class="py-3 px-2 text-right">
                                        @if($item['editable'] ?? true)
                                            <div class="flex items-center gap-1 justify-end">
                                                <select
                                                    x-data
                                                    x-on:change="$wire.updateInvoiceItemDiscountType('{{ $item['type'] }}', '{{ $item['id'] }}', $event.target.value)"
                                                    class="w-20 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-xs py-1"
                                                >
                                                    <option value="none" @selected($item['discount_type'] === 'none')>-</option>
                                                    <option value="percent" @selected($item['discount_type'] === 'percent')>%</option>
                                                    <option value="fixed" @selected($item['discount_type'] === 'fixed')>{{ current_currency() }}</option>
                                                </select>
                                                <input
                                                    type="number"
                                                    value="{{ $item['discount_value'] }}"
                                                    x-data
                                                    x-on:change="$wire.updateInvoiceItemDiscountValue('{{ $item['type'] }}', '{{ $item['id'] }}', $event.target.value)"
                                                    class="w-16 text-right border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-xs py-1"
                                                    min="0"
                                                    @if($item['discount_type'] === 'percent') max="100" @endif
                                                    step="0.01"
                                                />
                                            </div>
                                        @else
                                            <span class="text-gray-700 dark:text-gray-300">
                                                @if($item['discount'] > 0)
                                                    {{ number_format($item['discount'], 2) }}
                                                @else
                                                    -
                                                @endif
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Total --}}
                                    <td class="py-3 px-2 text-right font-medium text-gray-900 dark:text-white">
                                        {{ number_format($item['total'], 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Totals Section --}}
                <div class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                    <div class="flex justify-end">
                        <div class="w-72 space-y-2">
                            {{-- Subtotal --}}
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">{{ __('booking::session.invoice.subtotal') }}</span>
                                <span class="text-gray-900 dark:text-white font-medium">{{ number_format($this->getInvoiceSubtotal(), 2) }} {{ current_currency() }}</span>
                            </div>

                            {{-- Overall Discount --}}
                            <div x-data="{ showDiscountForm: false }" class="space-y-2">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">{{ __('booking::session.invoice.overall_discount') }}</span>
                                    <div class="flex items-center gap-2">
                                        @if($overallDiscountType !== 'none' && $overallDiscountValue > 0)
                                            <span class="text-red-500">-{{ number_format($this->getOverallDiscountAmount(), 2) }} {{ current_currency() }}</span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                        <button
                                            type="button"
                                            @click="showDiscountForm = !showDiscountForm"
                                            class="p-1 text-gray-400 hover:text-primary-500 rounded"
                                        >
                                            <x-heroicon-o-plus-circle class="w-4 h-4" x-show="!showDiscountForm" />
                                            <x-heroicon-o-minus-circle class="w-4 h-4" x-show="showDiscountForm" x-cloak />
                                        </button>
                                    </div>
                                </div>

                                {{-- Discount Form --}}
                                <div x-show="showDiscountForm" x-cloak x-transition class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg space-y-2">
                                    <div class="grid grid-cols-2 gap-2">
                                        <select
                                            wire:model.live="overallDiscountType"
                                            class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm"
                                        >
                                            <option value="none">{{ __('booking::session.invoice.no_discount') }}</option>
                                            <option value="percent">{{ __('booking::session.invoice.percentage') }}</option>
                                            <option value="fixed">{{ __('booking::session.invoice.fixed_amount') }}</option>
                                        </select>
                                        <input
                                            type="number"
                                            wire:model.live="overallDiscountValue"
                                            class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm"
                                            placeholder="{{ $overallDiscountType === 'percent' ? '%' : current_currency() }}"
                                            min="0"
                                            @if($overallDiscountType === 'percent') max="100" @endif
                                        />
                                    </div>
                                    <input
                                        type="text"
                                        wire:model.live="overallDiscountReason"
                                        class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm"
                                        placeholder="{{ __('booking::session.invoice.discount_reason_placeholder') }}"
                                    />
                                    <x-filament::button
                                        wire:click="applyOverallDiscount"
                                        size="sm"
                                        class="w-full"
                                    >
                                        {{ __('booking::session.invoice.apply_discount') }}
                                    </x-filament::button>
                                </div>
                            </div>

                            {{-- Total --}}
                            <div class="flex justify-between text-lg font-bold border-t border-gray-200 dark:border-gray-700 pt-2">
                                <span class="text-gray-900 dark:text-white">{{ __('booking::session.invoice.total') }}</span>
                                <span class="text-green-600">{{ number_format($this->getInvoiceTotal(), 2) }} {{ current_currency() }}</span>
                            </div>

                            {{-- Package Info (if applicable) --}}
                            @if($appointment?->is_package_session)
                                <div class="flex items-center gap-2 p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-sm">
                                    <x-heroicon-o-gift class="w-5 h-5 text-blue-500" />
                                    <div>
                                        <div class="font-medium text-blue-700 dark:text-blue-300">{{ __('booking::session.invoice.package_session') }}</div>
                                        <div class="text-xs text-blue-600 dark:text-blue-400">{{ __('booking::session.invoice.covered_by_package') }}</div>
                                    </div>
                                </div>
                            @endif

                            {{-- Visit Summary (if other appointments in same visit) --}}
                            @php $visitSummary = $this->getVisitSummary(); @endphp
                            @if($visitSummary && ($visitSummary['other_appointments']->isNotEmpty() || $visitSummary['other_products']->isNotEmpty()))
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-3 mt-3">
                                    <div class="flex items-center gap-2 text-purple-600 dark:text-purple-400 mb-2">
                                        <x-heroicon-o-ticket class="w-4 h-4" />
                                        <span class="text-sm font-medium">{{ __('booking::session.invoice.other_visit_items', ['code' => $visitSummary['code']]) }}</span>
                                    </div>

                                    @if($visitSummary['other_appointments']->isNotEmpty())
                                        <div class="space-y-1 text-sm">
                                            @foreach($visitSummary['other_appointments'] as $otherAppt)
                                                <div class="flex items-center justify-between py-1 px-2 bg-purple-50 dark:bg-purple-900/20 rounded">
                                                    <div class="flex items-center gap-2">
                                                        <x-heroicon-o-sparkles class="w-3 h-3 text-purple-500" />
                                                        <span class="text-gray-700 dark:text-gray-300">{{ $otherAppt['service'] }}</span>
                                                        <span class="text-xs text-gray-500">({{ $otherAppt['practitioner'] }})</span>
                                                        <span class="px-1.5 py-0.5 text-xs rounded
                                                            @if($otherAppt['status'] === 'completed') bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300
                                                            @elseif($otherAppt['status'] === 'in_progress') bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300
                                                            @else bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400
                                                            @endif">{{ $otherAppt['status_label'] }}</span>
                                                    </div>
                                                    <span class="font-medium text-gray-900 dark:text-white">{{ number_format($otherAppt['price'], 2) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if($visitSummary['other_products']->isNotEmpty())
                                        <div class="space-y-1 text-sm mt-2">
                                            @foreach($visitSummary['other_products'] as $otherProd)
                                                <div class="flex items-center justify-between py-1 px-2 bg-purple-50 dark:bg-purple-900/20 rounded">
                                                    <div class="flex items-center gap-2">
                                                        <x-heroicon-o-shopping-bag class="w-3 h-3 text-purple-500" />
                                                        <span class="text-gray-700 dark:text-gray-300">{{ $otherProd['name'] }}</span>
                                                        <span class="text-xs text-gray-500">x{{ $otherProd['quantity'] }}</span>
                                                    </div>
                                                    <span class="font-medium text-gray-900 dark:text-white">{{ number_format($otherProd['total'], 2) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="text-xs text-purple-600 dark:text-purple-400 mt-2 italic">
                                        {{ __('booking::session.invoice.visit_checkout_note') }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
