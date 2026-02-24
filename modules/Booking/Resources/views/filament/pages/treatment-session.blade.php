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
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $patient?->code }} | {{ $patient?->phone }}
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
                </div>
            </div>
        </div>

        {{-- Alert Cards --}}
        @if(!empty($this->getAllergies()) || !empty($this->getContraindications()))
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @if(!empty($this->getAllergies()))
                    <div class="rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 p-4">
                        <div class="flex items-center gap-2 text-red-700 dark:text-red-400 font-semibold mb-2">
                            <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                            {{ __('booking::session.alerts.allergies') }}
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($this->getAllergies() as $allergy)
                                <span class="px-2 py-1 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 rounded text-sm">
                                    {{ $allergy }}
                                </span>
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
            </div>
        @endif

        {{-- Main Content Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Left Column --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Medical Information --}}
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-heart class="w-5 h-5 text-gray-400" />
                            {{ __('booking::session.sections.medical_info') }}
                        </div>
                    </x-slot>

                    @if($medicalHistory)
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                            <div class="text-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('booking::session.medical.fitzpatrick') }}</div>
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $medicalHistory->fitzpatrick_type ?? '-' }}</div>
                            </div>
                            <div class="text-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('booking::session.medical.blood_type') }}</div>
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $medicalHistory->blood_type ?? '-' }}</div>
                            </div>
                            <div class="text-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('booking::session.medical.bmi') }}</div>
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $medicalHistory->bmi ?? '-' }}</div>
                            </div>
                            <div class="text-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('booking::session.medical.smoker') }}</div>
                                <div class="font-semibold {{ $medicalHistory->is_smoker ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $medicalHistory->is_smoker ? __('Yes') : __('No') }}
                                </div>
                            </div>
                        </div>

                        @if(!empty($this->getMedications()))
                            <div class="mb-4">
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('booking::session.medical.medications') }}</div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($this->getMedications() as $med)
                                        <span class="px-2 py-1 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded text-sm">{{ $med }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(!empty($this->getMedicalConditions()))
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('booking::session.medical.conditions') }}</div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($this->getMedicalConditions() as $condition)
                                        <span class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded text-sm">
                                            {{ \Modules\Patients\Models\PatientMedicalHistory::COMMON_CONDITIONS[$condition] ?? $condition }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                            <x-heroicon-o-document-text class="w-10 h-10 mx-auto mb-2 opacity-50" />
                            {{ __('booking::session.medical.no_history') }}
                        </div>
                    @endif
                </x-filament::section>

                {{-- Session Notes --}}
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-pencil-square class="w-5 h-5 text-gray-400" />
                            {{ __('booking::session.sections.notes') }}
                        </div>
                    </x-slot>

                    {{-- Add Note Form --}}
                    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="md:col-span-3">
                                <textarea
                                    wire:model="noteContent"
                                    rows="3"
                                    class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="{{ __('booking::session.notes.placeholder') }}"
                                ></textarea>
                            </div>
                            <div class="flex flex-col gap-2">
                                <select wire:model="noteType" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm">
                                    @foreach(\Modules\Patients\Models\PatientNote::TYPES as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-filament::button wire:click="addNote" class="w-full">
                                    {{ __('booking::session.notes.add') }}
                                </x-filament::button>
                            </div>
                        </div>
                    </div>

                    {{-- Previous Notes --}}
                    @php $notes = $this->getPatientNotes(); @endphp
                    @if($notes->isNotEmpty())
                        <div class="space-y-3 max-h-64 overflow-y-auto">
                            @foreach($notes as $note)
                                <div class="p-3 border border-gray-200 dark:border-gray-700 rounded-lg">
                                    <div class="flex items-start justify-between mb-1">
                                        <span class="px-2 py-0.5 text-xs font-medium rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                            {{ \Modules\Patients\Models\PatientNote::TYPES[$note->type] ?? $note->type }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $note->created_at->format('M d, Y H:i') }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $note->content }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4 text-gray-500 dark:text-gray-400 text-sm">
                            {{ __('booking::session.notes.no_notes') }}
                        </div>
                    @endif
                </x-filament::section>

                {{-- Photos --}}
                <x-filament::section collapsible>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-camera class="w-5 h-5 text-gray-400" />
                            {{ __('booking::session.sections.photos') }}
                        </div>
                    </x-slot>

                    {{-- Upload Photo Form --}}
                    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div class="md:col-span-2">
                                <input type="file" wire:model="photoUpload" accept="image/*" class="w-full text-sm border border-gray-300 dark:border-gray-600 rounded-lg p-2 dark:bg-gray-700" />
                            </div>
                            <div>
                                <select wire:model="photoType" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm">
                                    @foreach(\Modules\Patients\Models\PatientPhoto::TYPES as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <select wire:model="photoBodyArea" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm">
                                    <option value="">{{ __('booking::session.photos.select_area') }}</option>
                                    @foreach(\Modules\Patients\Models\PatientPhoto::BODY_AREAS as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-filament::button wire:click="uploadPhoto" class="w-full">
                                    {{ __('booking::session.photos.upload') }}
                                </x-filament::button>
                            </div>
                        </div>
                    </div>

                    {{-- Photo Gallery --}}
                    @php $photos = $this->getPatientPhotos(); @endphp
                    @if($photos->isNotEmpty())
                        <div class="grid grid-cols-3 md:grid-cols-6 gap-3">
                            @foreach($photos as $photo)
                                <div class="aspect-square rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800">
                                    @if($photo->getFirstMediaUrl('photos', 'thumb'))
                                        <img src="{{ $photo->getFirstMediaUrl('photos', 'thumb') }}" alt="{{ $photo->description }}" class="w-full h-full object-cover" />
                                    @else
                                        <div class="w-full h-full flex items-center justify-center">
                                            <x-heroicon-o-photo class="w-8 h-8 text-gray-400" />
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4 text-gray-500 dark:text-gray-400 text-sm">
                            {{ __('booking::session.photos.no_photos') }}
                        </div>
                    @endif
                </x-filament::section>
            </div>

            {{-- Right Column --}}
            <div class="space-y-6">
                {{-- Current Treatment Plan --}}
                @php $currentPlan = $this->getCurrentTreatmentPlan(); @endphp
                @if($currentPlan)
                    <x-filament::section>
                        <x-slot name="heading">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-clipboard-document-list class="w-5 h-5 text-blue-500" />
                                {{ __('booking::session.sections.current_plan') }}
                            </div>
                        </x-slot>

                        <div class="space-y-4">
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $currentPlan->translated_name }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $currentPlan->code }}</div>
                            </div>

                            {{-- Progress Bar --}}
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600 dark:text-gray-400">{{ __('booking::session.plan.progress') }}</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $currentPlan->progress_percentage }}%</span>
                                </div>
                                <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                    <div class="h-full bg-primary-500 rounded-full transition-all" style="width: {{ $currentPlan->progress_percentage }}%"></div>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ $currentPlan->total_completed_sessions }} / {{ $currentPlan->total_recommended_sessions }} {{ __('booking::session.plan.sessions') }}
                                </div>
                            </div>

                            {{-- Items --}}
                            <div class="space-y-2">
                                @foreach($currentPlan->items as $item)
                                    <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-800 rounded">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item->service?->translated_name }}</span>
                                        <span class="text-sm font-medium {{ $item->completed_sessions >= $item->recommended_sessions ? 'text-green-600' : 'text-gray-600 dark:text-gray-400' }}">
                                            {{ $item->completed_sessions }}/{{ $item->recommended_sessions }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </x-filament::section>
                @endif

                {{-- Previous Appointments --}}
                <x-filament::section collapsible collapsed>
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
                                <div class="p-3 border border-gray-200 dark:border-gray-700 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $appt->service?->translated_name }}</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $appt->date->format('M d, Y') }}</span>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $appt->practitioner?->name }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4 text-gray-500 dark:text-gray-400 text-sm">
                            {{ __('booking::session.previous.no_visits') }}
                        </div>
                    @endif
                </x-filament::section>

                {{-- Create Treatment Plan --}}
                <x-filament::section collapsible collapsed>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-plus-circle class="w-5 h-5 text-green-500" />
                            {{ __('booking::session.sections.create_plan') }}
                        </div>
                    </x-slot>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('booking::session.plan.name') }}</label>
                            <input
                                type="text"
                                wire:model="treatmentPlanData.name"
                                class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm"
                                placeholder="{{ __('booking::session.plan.name_placeholder') }}"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('booking::session.plan.services') }}</label>
                            @foreach($treatmentPlanData['services'] ?? [] as $index => $service)
                                <div class="flex gap-2 mb-2">
                                    <select
                                        wire:model="treatmentPlanData.services.{{ $index }}.service_id"
                                        class="flex-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm"
                                    >
                                        <option value="">{{ __('booking::session.plan.select_service') }}</option>
                                        @foreach($this->getAvailableServices() as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    <input
                                        type="number"
                                        wire:model="treatmentPlanData.services.{{ $index }}.sessions"
                                        class="w-16 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm text-center"
                                        min="1"
                                        placeholder="#"
                                    />
                                    <input
                                        type="number"
                                        wire:model="treatmentPlanData.services.{{ $index }}.interval"
                                        class="w-16 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm text-center"
                                        min="1"
                                        placeholder="{{ __('booking::session.plan.days') }}"
                                    />
                                    @if(count($treatmentPlanData['services'] ?? []) > 1)
                                        <button wire:click="removeServiceRow({{ $index }})" class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded">
                                            <x-heroicon-o-x-mark class="w-4 h-4" />
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                            <button wire:click="addServiceRow" class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 flex items-center gap-1 mt-2">
                                <x-heroicon-o-plus class="w-4 h-4" />
                                {{ __('booking::session.plan.add_service') }}
                            </button>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('booking::session.plan.notes') }}</label>
                            <textarea
                                wire:model="treatmentPlanData.notes"
                                rows="2"
                                class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm"
                            ></textarea>
                        </div>

                        <x-filament::button wire:click="createTreatmentPlan" class="w-full">
                            {{ __('booking::session.plan.create') }}
                        </x-filament::button>
                    </div>
                </x-filament::section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
