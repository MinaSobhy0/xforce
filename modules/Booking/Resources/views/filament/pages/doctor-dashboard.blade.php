<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Column: Appointment Queue --}}
        <div class="lg:col-span-1 space-y-4">
            {{-- Statistics Cards --}}
            @php $stats = $this->getStatistics(); @endphp
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-amber-50 dark:bg-amber-900/20 p-4 border border-amber-200 dark:border-amber-700">
                    <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $stats['waiting'] }}</div>
                    <div class="text-sm text-amber-700 dark:text-amber-300">{{ __('booking::dashboard.stats.waiting') }}</div>
                </div>
                <div class="rounded-xl bg-blue-50 dark:bg-blue-900/20 p-4 border border-blue-200 dark:border-blue-700">
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $stats['in_progress'] }}</div>
                    <div class="text-sm text-blue-700 dark:text-blue-300">{{ __('booking::dashboard.stats.in_progress') }}</div>
                </div>
                <div class="rounded-xl bg-green-50 dark:bg-green-900/20 p-4 border border-green-200 dark:border-green-700">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $stats['completed'] }}</div>
                    <div class="text-sm text-green-700 dark:text-green-300">{{ __('booking::dashboard.stats.completed') }}</div>
                </div>
                <div class="rounded-xl bg-gray-50 dark:bg-gray-800 p-4 border border-gray-200 dark:border-gray-700">
                    <div class="text-2xl font-bold text-gray-600 dark:text-gray-400">{{ $stats['upcoming'] }}</div>
                    <div class="text-sm text-gray-700 dark:text-gray-300">{{ __('booking::dashboard.stats.upcoming') }}</div>
                </div>
            </div>

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

                    {{-- Checked In (Waiting) --}}
                    @if($appointments['checked_in']->isNotEmpty())
                        <div class="px-4 py-2 bg-amber-50 dark:bg-amber-900/20">
                            <span class="text-xs font-medium text-amber-700 dark:text-amber-300 uppercase tracking-wide">
                                {{ __('booking::dashboard.queue.checked_in') }} ({{ $appointments['checked_in']->count() }})
                            </span>
                        </div>
                        @foreach($appointments['checked_in'] as $appointment)
                            <div class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors {{ $activeAppointmentId === $appointment->id ? 'bg-primary-50 dark:bg-primary-900/20 border-l-4 border-primary-500' : '' }}">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ $appointment->patient->full_name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $appointment->start_time->format('H:i') }} - {{ $appointment->service?->translated_name }}
                                        </div>
                                        @if($appointment->treatmentPlanAppointment)
                                            <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                Session {{ $appointment->treatmentPlanAppointment->session_number }} of {{ $appointment->treatmentPlanAppointment->item->recommended_sessions }}
                                            </div>
                                        @endif
                                    </div>
                                    <button
                                        wire:click="startSession('{{ $appointment->id }}')"
                                        class="px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg transition-colors"
                                    >
                                        {{ __('booking::dashboard.actions.start') }}
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    @endif

                    {{-- In Progress --}}
                    @if($appointments['in_progress']->isNotEmpty())
                        <div class="px-4 py-2 bg-blue-50 dark:bg-blue-900/20">
                            <span class="text-xs font-medium text-blue-700 dark:text-blue-300 uppercase tracking-wide">
                                {{ __('booking::dashboard.queue.in_progress') }} ({{ $appointments['in_progress']->count() }})
                            </span>
                        </div>
                        @foreach($appointments['in_progress'] as $appointment)
                            <div class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors {{ $activeAppointmentId === $appointment->id ? 'bg-primary-50 dark:bg-primary-900/20 border-l-4 border-primary-500' : '' }}">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ $appointment->patient->full_name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $appointment->start_time->format('H:i') }} - {{ $appointment->service?->translated_name }}
                                        </div>
                                    </div>
                                    @if($activeAppointmentId !== $appointment->id)
                                        <button
                                            wire:click="resumeSession('{{ $appointment->id }}')"
                                            class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors"
                                        >
                                            {{ __('booking::dashboard.actions.resume') }}
                                        </button>
                                    @else
                                        <span class="px-2 py-1 bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 text-xs font-medium rounded-full">
                                            {{ __('booking::dashboard.queue.active') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @endif

                    {{-- Confirmed --}}
                    @if($appointments['confirmed']->isNotEmpty())
                        <div class="px-4 py-2 bg-gray-50 dark:bg-gray-800">
                            <span class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                                {{ __('booking::dashboard.queue.confirmed') }} ({{ $appointments['confirmed']->count() }})
                            </span>
                        </div>
                        @foreach($appointments['confirmed'] as $appointment)
                            <div class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ $appointment->patient->full_name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $appointment->start_time->format('H:i') }} - {{ $appointment->service?->translated_name }}
                                        </div>
                                    </div>
                                    <button
                                        wire:click="checkInAppointment('{{ $appointment->id }}')"
                                        class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-lg transition-colors"
                                    >
                                        {{ __('booking::dashboard.actions.check_in') }}
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    @endif

                    {{-- Scheduled --}}
                    @if($appointments['scheduled']->isNotEmpty())
                        <div class="px-4 py-2 bg-gray-50 dark:bg-gray-800">
                            <span class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                                {{ __('booking::dashboard.queue.scheduled') }} ({{ $appointments['scheduled']->count() }})
                            </span>
                        </div>
                        @foreach($appointments['scheduled'] as $appointment)
                            <div class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors opacity-70">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ $appointment->patient->full_name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $appointment->start_time->format('H:i') }} - {{ $appointment->service?->translated_name }}
                                        </div>
                                    </div>
                                    <button
                                        wire:click="confirmAppointment('{{ $appointment->id }}')"
                                        class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg transition-colors"
                                    >
                                        {{ __('booking::dashboard.actions.confirm') }}
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    @endif

                    {{-- Completed --}}
                    @if($appointments['completed']->isNotEmpty())
                        <div class="px-4 py-2 bg-green-50 dark:bg-green-900/20">
                            <span class="text-xs font-medium text-green-700 dark:text-green-300 uppercase tracking-wide">
                                {{ __('booking::dashboard.queue.completed') }} ({{ $appointments['completed']->count() }})
                            </span>
                        </div>
                        @foreach($appointments['completed']->take(3) as $appointment)
                            <div class="px-4 py-3 opacity-60">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white line-through">
                                            {{ $appointment->patient->full_name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $appointment->start_time->format('H:i') }} - {{ $appointment->service?->translated_name }}
                                        </div>
                                    </div>
                                    <x-heroicon-o-check-circle class="w-5 h-5 text-green-500" />
                                </div>
                            </div>
                        @endforeach
                    @endif

                    {{-- Empty State --}}
                    @if($stats['total'] === 0)
                        <div class="px-4 py-8 text-center">
                            <x-heroicon-o-calendar class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600" />
                            <p class="mt-2 text-gray-500 dark:text-gray-400">{{ __('booking::dashboard.queue.empty') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right Column: Session Workspace --}}
        <div class="lg:col-span-2">
            @if($activeAppointmentId)
                @php $activeAppointment = $this->getActiveAppointment(); @endphp

                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                    {{-- Session Header --}}
                    <div class="px-6 py-4 bg-primary-50 dark:bg-primary-900/20 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ $activePatientData['name'] ?? 'Patient' }}
                                </h2>
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $activeAppointment?->service?->translated_name }}
                                    @if($sessionNumber = $this->getSessionNumber())
                                        <span class="text-primary-600 dark:text-primary-400 font-medium ml-2">{{ $sessionNumber }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <button
                                    wire:click="cancelActiveSession"
                                    class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
                                >
                                    {{ __('booking::dashboard.actions.back_to_queue') }}
                                </button>
                                <button
                                    wire:click="completeSession"
                                    wire:confirm="{{ __('booking::dashboard.messages.confirm_complete') }}"
                                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors flex items-center gap-2"
                                >
                                    <x-heroicon-o-check class="w-5 h-5" />
                                    {{ __('booking::dashboard.actions.complete') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Tabs --}}
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <nav class="flex -mb-px px-6 space-x-6">
                            @foreach(['info' => 'Patient Info', 'medical' => 'Medical History', 'photos' => 'Photos', 'notes' => 'Notes', 'plan' => 'Treatment Plan'] as $tabKey => $tabLabel)
                                <button
                                    wire:click="setActiveTab('{{ $tabKey }}')"
                                    class="py-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === $tabKey ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}"
                                >
                                    {{ __('booking::dashboard.tabs.' . $tabKey) }}
                                </button>
                            @endforeach
                        </nav>
                    </div>

                    {{-- Tab Content --}}
                    <div class="p-6 min-h-[400px]">
                        {{-- Patient Info Tab --}}
                        @if($activeTab === 'info')
                            <div class="space-y-6">
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::dashboard.patient.code') }}</div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $activePatientData['code'] ?? '-' }}</div>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::dashboard.patient.age') }}</div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $activePatientData['age'] ?? '-' }} {{ __('booking::dashboard.patient.years') }}</div>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::dashboard.patient.phone') }}</div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $activePatientData['phone'] ?? '-' }}</div>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::dashboard.patient.member_since') }}</div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $activePatientData['member_since'] ?? '-' }}</div>
                                    </div>
                                </div>

                                @php $medicalHistory = $this->getPatientMedicalHistory(); @endphp
                                @if($medicalHistory)
                                    {{-- Allergies Alert --}}
                                    @if(!empty($medicalHistory->allergies))
                                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg p-4">
                                            <div class="flex items-center gap-2 text-red-700 dark:text-red-400 font-medium mb-2">
                                                <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                                                {{ __('booking::dashboard.patient.allergies') }}
                                            </div>
                                            <div class="text-red-600 dark:text-red-300">
                                                {{ implode(', ', $medicalHistory->allergies) }}
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Contraindications --}}
                                    @if(!empty($medicalHistory->contraindications))
                                        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-4">
                                            <div class="flex items-center gap-2 text-amber-700 dark:text-amber-400 font-medium mb-2">
                                                <x-heroicon-o-shield-exclamation class="w-5 h-5" />
                                                {{ __('booking::dashboard.patient.contraindications') }}
                                            </div>
                                            <div class="text-amber-600 dark:text-amber-300">
                                                @foreach($medicalHistory->contraindications as $c)
                                                    <span class="inline-block px-2 py-1 bg-amber-100 dark:bg-amber-900/40 rounded text-sm mr-2 mb-1">
                                                        {{ \Modules\Patients\Models\PatientMedicalHistory::CONTRAINDICATIONS[$c] ?? $c }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Current Medications --}}
                                    @if(!empty($medicalHistory->current_medications))
                                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                                            <div class="flex items-center gap-2 text-blue-700 dark:text-blue-400 font-medium mb-2">
                                                <x-heroicon-o-beaker class="w-5 h-5" />
                                                {{ __('booking::dashboard.patient.medications') }}
                                            </div>
                                            <div class="text-blue-600 dark:text-blue-300">
                                                {{ implode(', ', $medicalHistory->current_medications) }}
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        @endif

                        {{-- Medical History Tab --}}
                        @if($activeTab === 'medical')
                            @php $medicalHistory = $this->getPatientMedicalHistory(); @endphp
                            @if($medicalHistory)
                                <div class="space-y-6">
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::dashboard.medical.fitzpatrick') }}</div>
                                            <div class="font-medium text-gray-900 dark:text-white">
                                                {{ \Modules\Patients\Models\PatientMedicalHistory::FITZPATRICK_TYPES[$medicalHistory->fitzpatrick_type] ?? '-' }}
                                            </div>
                                        </div>
                                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::dashboard.medical.blood_type') }}</div>
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $medicalHistory->blood_type ?? '-' }}</div>
                                        </div>
                                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::dashboard.medical.bmi') }}</div>
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $medicalHistory->bmi ?? '-' }}</div>
                                        </div>
                                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::dashboard.medical.smoker') }}</div>
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $medicalHistory->is_smoker ? __('Yes') : __('No') }}</div>
                                        </div>
                                    </div>

                                    @if(!empty($medicalHistory->medical_conditions))
                                        <div>
                                            <h4 class="font-medium text-gray-900 dark:text-white mb-2">{{ __('booking::dashboard.medical.conditions') }}</h4>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($medicalHistory->medical_conditions as $condition)
                                                    <span class="px-3 py-1 bg-gray-100 dark:bg-gray-800 rounded-full text-sm">
                                                        {{ \Modules\Patients\Models\PatientMedicalHistory::COMMON_CONDITIONS[$condition] ?? $condition }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if(!empty($medicalHistory->previous_cosmetic_treatments))
                                        <div>
                                            <h4 class="font-medium text-gray-900 dark:text-white mb-2">{{ __('booking::dashboard.medical.previous_treatments') }}</h4>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($medicalHistory->previous_cosmetic_treatments as $treatment)
                                                    <span class="px-3 py-1 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded-full text-sm">
                                                        {{ $treatment }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if($medicalHistory->notes)
                                        <div>
                                            <h4 class="font-medium text-gray-900 dark:text-white mb-2">{{ __('booking::dashboard.medical.notes') }}</h4>
                                            <p class="text-gray-600 dark:text-gray-400">{{ $medicalHistory->notes }}</p>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                    <x-heroicon-o-document-text class="w-12 h-12 mx-auto mb-2 opacity-50" />
                                    {{ __('booking::dashboard.medical.no_history') }}
                                </div>
                            @endif
                        @endif

                        {{-- Photos Tab --}}
                        @if($activeTab === 'photos')
                            <div class="space-y-6">
                                {{-- Photo Upload Form --}}
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-4">{{ __('booking::dashboard.photos.upload') }}</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('booking::dashboard.photos.file') }}</label>
                                            <input type="file" wire:model="photoUpload" accept="image/*" class="w-full text-sm border border-gray-300 dark:border-gray-600 rounded-lg p-2 dark:bg-gray-700" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('booking::dashboard.photos.type') }}</label>
                                            <select wire:model="photoType" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 dark:bg-gray-700 text-sm">
                                                @foreach(\Modules\Patients\Models\PatientPhoto::TYPES as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('booking::dashboard.photos.body_area') }}</label>
                                            <select wire:model="photoBodyArea" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 dark:bg-gray-700 text-sm">
                                                <option value="">{{ __('booking::dashboard.photos.select_area') }}</option>
                                                @foreach(\Modules\Patients\Models\PatientPhoto::BODY_AREAS as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="flex items-end">
                                            <button wire:click="uploadPhoto" class="w-full px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors">
                                                {{ __('booking::dashboard.photos.upload_btn') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Photo Gallery --}}
                                @php $photos = $this->getPatientPhotos(); @endphp
                                @if($photos->isNotEmpty())
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                        @foreach($photos as $photo)
                                            <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                                @if($photo->thumb_url)
                                                    <img src="{{ $photo->thumb_url }}" alt="{{ $photo->description }}" class="w-full h-32 object-cover" />
                                                @else
                                                    <div class="w-full h-32 bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                                        <x-heroicon-o-photo class="w-8 h-8 text-gray-400" />
                                                    </div>
                                                @endif
                                                <div class="p-2">
                                                    <div class="text-xs font-medium text-gray-900 dark:text-white">{{ $photo->type_label }}</div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $photo->taken_at?->format('M d, Y') }}</div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                        <x-heroicon-o-photo class="w-12 h-12 mx-auto mb-2 opacity-50" />
                                        {{ __('booking::dashboard.photos.no_photos') }}
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Notes Tab --}}
                        @if($activeTab === 'notes')
                            <div class="space-y-6">
                                {{-- Add Note Form --}}
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-4">{{ __('booking::dashboard.notes.add') }}</h4>
                                    <div class="space-y-4">
                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                            <div class="md:col-span-3">
                                                <textarea
                                                    wire:model="sessionNoteContent"
                                                    rows="3"
                                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 dark:bg-gray-700"
                                                    placeholder="{{ __('booking::dashboard.notes.placeholder') }}"
                                                ></textarea>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <select wire:model="sessionNoteType" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 dark:bg-gray-700 text-sm">
                                                    @foreach(\Modules\Patients\Models\PatientNote::TYPES as $key => $label)
                                                        <option value="{{ $key }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <button wire:click="addSessionNote" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors">
                                                    {{ __('booking::dashboard.notes.save') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Notes List --}}
                                @php $notes = $this->getPatientNotes(); @endphp
                                @if($notes->isNotEmpty())
                                    <div class="space-y-3">
                                        @foreach($notes as $note)
                                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                                <div class="flex items-start justify-between mb-2">
                                                    <div>
                                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $note->type_color }}-100 text-{{ $note->type_color }}-700 dark:bg-{{ $note->type_color }}-900/30 dark:text-{{ $note->type_color }}-300">
                                                            {{ $note->type_label }}
                                                        </span>
                                                        @if($note->subject)
                                                            <span class="ml-2 text-sm font-medium text-gray-900 dark:text-white">{{ $note->subject }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ $note->created_at->format('M d, Y H:i') }}
                                                        @if($note->createdBy)
                                                            - {{ $note->createdBy->name }}
                                                        @endif
                                                    </div>
                                                </div>
                                                <p class="text-gray-600 dark:text-gray-400 text-sm">{{ $note->content }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                        <x-heroicon-o-document-text class="w-12 h-12 mx-auto mb-2 opacity-50" />
                                        {{ __('booking::dashboard.notes.no_notes') }}
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Treatment Plan Tab --}}
                        @if($activeTab === 'plan')
                            <div class="space-y-6">
                                {{-- Existing Plans --}}
                                @php $plans = $this->getPatientTreatmentPlans(); @endphp
                                @if($plans->isNotEmpty())
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white mb-4">{{ __('booking::dashboard.plan.active_plans') }}</h4>
                                        <div class="space-y-3">
                                            @foreach($plans as $plan)
                                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                                    <div class="flex items-start justify-between mb-3">
                                                        <div>
                                                            <h5 class="font-medium text-gray-900 dark:text-white">{{ $plan->translated_name }}</h5>
                                                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $plan->code }}</span>
                                                        </div>
                                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $plan->status_color }}-100 text-{{ $plan->status_color }}-700 dark:bg-{{ $plan->status_color }}-900/30">
                                                            {{ $plan->status_label }}
                                                        </span>
                                                    </div>
                                                    {{-- Progress Bar --}}
                                                    <div class="mb-3">
                                                        <div class="flex justify-between text-sm mb-1">
                                                            <span class="text-gray-600 dark:text-gray-400">{{ __('booking::dashboard.plan.progress') }}</span>
                                                            <span class="font-medium text-gray-900 dark:text-white">{{ $plan->progress_percentage }}%</span>
                                                        </div>
                                                        <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                                            <div class="h-full bg-primary-500 rounded-full" style="width: {{ $plan->progress_percentage }}%"></div>
                                                        </div>
                                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                            {{ $plan->total_completed_sessions }} / {{ $plan->total_recommended_sessions }} {{ __('booking::dashboard.plan.sessions') }}
                                                        </div>
                                                    </div>
                                                    {{-- Services --}}
                                                    <div class="space-y-2">
                                                        @foreach($plan->items->take(3) as $item)
                                                            <div class="flex items-center justify-between text-sm">
                                                                <span class="text-gray-600 dark:text-gray-400">{{ $item->service?->translated_name }}</span>
                                                                <span class="font-medium">{{ $item->completed_sessions }}/{{ $item->recommended_sessions }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Create New Plan Form --}}
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-4">{{ __('booking::dashboard.plan.create') }}</h4>
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('booking::dashboard.plan.name') }}</label>
                                            <input
                                                type="text"
                                                wire:model="treatmentPlanData.name"
                                                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 dark:bg-gray-700"
                                                placeholder="{{ __('booking::dashboard.plan.name_placeholder') }}"
                                            />
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('booking::dashboard.plan.services') }}</label>
                                            @foreach($treatmentPlanData['services'] ?? [] as $index => $service)
                                                <div class="grid grid-cols-12 gap-2 mb-2">
                                                    <div class="col-span-5">
                                                        <select
                                                            wire:model="treatmentPlanData.services.{{ $index }}.service_id"
                                                            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 dark:bg-gray-700 text-sm"
                                                        >
                                                            <option value="">{{ __('booking::dashboard.plan.select_service') }}</option>
                                                            @foreach($this->getAvailableServices() as $id => $name)
                                                                <option value="{{ $id }}">{{ $name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-span-2">
                                                        <input
                                                            type="number"
                                                            wire:model="treatmentPlanData.services.{{ $index }}.sessions"
                                                            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 dark:bg-gray-700 text-sm"
                                                            placeholder="{{ __('booking::dashboard.plan.sessions') }}"
                                                            min="1"
                                                        />
                                                    </div>
                                                    <div class="col-span-3">
                                                        <div class="flex items-center gap-1">
                                                            <input
                                                                type="number"
                                                                wire:model="treatmentPlanData.services.{{ $index }}.interval"
                                                                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 dark:bg-gray-700 text-sm"
                                                                placeholder="{{ __('booking::dashboard.plan.interval') }}"
                                                                min="1"
                                                            />
                                                            <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ __('booking::dashboard.plan.days') }}</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-span-2 flex items-center">
                                                        @if(count($treatmentPlanData['services'] ?? []) > 1)
                                                            <button
                                                                wire:click="removeServiceRow({{ $index }})"
                                                                class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg"
                                                            >
                                                                <x-heroicon-o-trash class="w-4 h-4" />
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                            <button
                                                wire:click="addServiceRow"
                                                class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 flex items-center gap-1"
                                            >
                                                <x-heroicon-o-plus class="w-4 h-4" />
                                                {{ __('booking::dashboard.plan.add_service') }}
                                            </button>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('booking::dashboard.plan.notes') }}</label>
                                            <textarea
                                                wire:model="treatmentPlanData.notes"
                                                rows="2"
                                                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 dark:bg-gray-700"
                                                placeholder="{{ __('booking::dashboard.plan.notes_placeholder') }}"
                                            ></textarea>
                                        </div>

                                        <button
                                            wire:click="createTreatmentPlan"
                                            class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors"
                                        >
                                            {{ __('booking::dashboard.plan.create_btn') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                {{-- No Active Session --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-8">
                    <div class="text-center">
                        <x-heroicon-o-clipboard-document-check class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">{{ __('booking::dashboard.workspace.no_session') }}</h3>
                        <p class="text-gray-500 dark:text-gray-400">{{ __('booking::dashboard.workspace.select_patient') }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
