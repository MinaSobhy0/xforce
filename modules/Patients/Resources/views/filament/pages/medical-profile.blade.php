<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Patient Info Bar --}}
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
                            {{ $patient?->code }} | {{ $patient?->phone }} | {{ $patient?->age ? $patient->age . ' ' . __('years') : '' }}
                        </div>
                    </div>
                </div>

                @if($this->hasAlerts())
                    <div class="flex items-center gap-2">
                        <span class="px-3 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded-full text-sm font-medium flex items-center gap-1">
                            <x-heroicon-o-exclamation-triangle class="w-4 h-4" />
                            {{ __('patients::medical_profile.alerts_count', ['count' => $this->getAlertCount()]) }}
                        </span>
                    </div>
                @endif

                @if($profile?->needsReview())
                    <span class="px-3 py-1 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 rounded-full text-sm font-medium flex items-center gap-1">
                        <x-heroicon-o-clock class="w-4 h-4" />
                        {{ __('patients::medical_profile.needs_review') }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Tab Navigation - Filament Style --}}
        <nav class="fi-tabs flex max-w-full gap-x-1 overflow-x-auto mx-auto rounded-xl bg-white p-2 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            @foreach(['overview', 'allergies', 'medications', 'contraindications', 'history', 'skin', 'lifestyle'] as $tab)
                <button
                    wire:click="$set('activeTab', '{{ $tab }}')"
                    class="fi-tabs-item group flex items-center justify-center gap-x-2 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium outline-none transition duration-75 {{ $activeTab === $tab ? 'fi-active bg-gray-50 dark:bg-white/5' : 'hover:bg-gray-50 focus-visible:bg-gray-50 dark:hover:bg-white/5 dark:focus-visible:bg-white/5' }}"
                >
                    <span class="fi-tabs-item-label transition duration-75 {{ $activeTab === $tab ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 group-hover:text-gray-700 group-focus-visible:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200 dark:group-focus-visible:text-gray-200' }}">
                        {{ __('patients::medical_profile.tabs.' . $tab) }}
                    </span>
                </button>
            @endforeach
        </nav>

        {{-- Tab Content --}}
        <div>
            {{-- Overview Tab --}}
            @if($activeTab === 'overview')
                <div class="space-y-6">
                    {{-- Quick Stats - Compact Row --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                            <span class="text-lg font-bold text-red-600 dark:text-red-400">{{ $this->getAllergies()->count() }}</span>
                            <span class="text-xs text-red-700 dark:text-red-300">{{ __('patients::medical_profile.stats.allergies') }}</span>
                        </div>
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                            <span class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ $this->getMedications()->where('is_ongoing', true)->count() }}</span>
                            <span class="text-xs text-blue-700 dark:text-blue-300">{{ __('patients::medical_profile.stats.medications') }}</span>
                        </div>
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                            <span class="text-lg font-bold text-amber-600 dark:text-amber-400">{{ $this->getContraindications()->where('is_active', true)->count() }}</span>
                            <span class="text-xs text-amber-700 dark:text-amber-300">{{ __('patients::medical_profile.stats.contraindications') }}</span>
                        </div>
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-800">
                            <span class="text-lg font-bold text-purple-600 dark:text-purple-400">{{ $this->getMedicalHistories()->count() }}</span>
                            <span class="text-xs text-purple-700 dark:text-purple-300">{{ __('patients::medical_profile.stats.conditions') }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {{-- Basic Profile --}}
                        <x-filament::section>
                            <x-slot name="heading">
                                <div class="flex items-center gap-2">
                                    <x-heroicon-o-identification class="w-5 h-5 text-gray-400" />
                                    {{ __('patients::medical_profile.sections.basic_info') }}
                                </div>
                            </x-slot>

                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.fields.blood_type') }}</label>
                                        <select wire:model.live="blood_type" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                                            <option value="">{{ __('Select...') }}</option>
                                            @foreach(\Modules\Patients\Models\MedicalProfile::BLOOD_TYPES as $value => $label)
                                                <option value="{{ $value }}">{{ __('patients::medical_profile.blood_types.' . $value) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.fields.fitzpatrick_type') }}</label>
                                        <select wire:model.live="fitzpatrick_type" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                                            <option value="">{{ __('Select...') }}</option>
                                            @foreach(\Modules\Patients\Models\MedicalProfile::FITZPATRICK_SHORT as $value => $label)
                                                <option value="{{ $value }}">{{ __('patients::medical_profile.fitzpatrick_types.' . $value) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                @if($patient?->gender === 'female')
                                    <div class="flex gap-6 pt-2">
                                        <label class="flex items-center gap-2">
                                            <input type="checkbox" wire:model.live="is_pregnant" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700" />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('patients::medical_profile.fields.is_pregnant') }}</span>
                                        </label>
                                        <label class="flex items-center gap-2">
                                            <input type="checkbox" wire:model.live="is_breastfeeding" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700" />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('patients::medical_profile.fields.is_breastfeeding') }}</span>
                                        </label>
                                    </div>
                                @endif

                                <x-filament::button wire:click="saveProfile" class="w-full">
                                    {{ __('patients::medical_profile.actions.save_profile') }}
                                </x-filament::button>
                            </div>
                        </x-filament::section>

                        {{-- Last Review Info --}}
                        <x-filament::section>
                            <x-slot name="heading">
                                <div class="flex items-center gap-2">
                                    <x-heroicon-o-clock class="w-5 h-5 text-gray-400" />
                                    {{ __('patients::medical_profile.sections.review_status') }}
                                </div>
                            </x-slot>

                            <div class="text-center py-4">
                                @if($profile?->last_reviewed_at)
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('patients::medical_profile.last_reviewed') }}
                                    </div>
                                    <div class="text-lg font-semibold text-gray-900 dark:text-white mt-1">
                                        {{ $profile->last_reviewed_at->format('M d, Y') }}
                                    </div>
                                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                        {{ $profile->last_reviewed_at->diffForHumans() }}
                                    </div>
                                @else
                                    <div class="text-sm text-amber-600 dark:text-amber-400">
                                        {{ __('patients::medical_profile.never_reviewed') }}
                                    </div>
                                @endif

                                <x-filament::button wire:click="markAsReviewed" color="gray" class="mt-4">
                                    {{ __('patients::medical_profile.actions.mark_reviewed') }}
                                </x-filament::button>
                            </div>
                        </x-filament::section>
                    </div>

                    {{-- Critical Alerts --}}
                    @if($this->getAllergies()->where('severity', 'life_threatening')->count() > 0 || $this->getContraindications()->where('contraindication_type', 'absolute')->count() > 0)
                        <div class="lg:col-span-2">
                            <x-filament::section>
                                <x-slot name="heading">
                                    <div class="flex items-center gap-2 text-red-600 dark:text-red-400">
                                        <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                                        {{ __('patients::medical_profile.sections.critical_alerts') }}
                                    </div>
                                </x-slot>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    @foreach($this->getAllergies()->whereIn('severity', ['severe', 'life_threatening']) as $allergy)
                                        <div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-xl flex items-start gap-3">
                                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-red-100 dark:bg-red-800 flex items-center justify-center">
                                                <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-red-600 dark:text-red-400" />
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-medium text-gray-900 dark:text-white">{{ $allergy->allergen }}</span>
                                                    <span class="px-2 py-0.5 text-xs font-bold rounded-full {{ $allergy->severity === 'life_threatening' ? 'bg-red-600 text-white' : 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-200' }}">
                                                        {{ __('patients::medical_profile.allergy_severities.' . $allergy->severity) }}
                                                    </span>
                                                </div>
                                                @if($allergy->reaction)
                                                    <p class="text-sm text-red-700 dark:text-red-300 mt-1">{{ $allergy->reaction }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach

                                    @foreach($this->getContraindications()->where('contraindication_type', 'absolute') as $contra)
                                        <div class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl flex items-start gap-3">
                                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-800 flex items-center justify-center">
                                                <x-heroicon-o-shield-exclamation class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-medium text-gray-900 dark:text-white">{{ $contra->name }}</span>
                                                    <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-red-600 text-white">
                                                        {{ __('patients::medical_profile.contraindication_types.absolute') }}
                                                    </span>
                                                </div>
                                                @if($contra->description)
                                                    <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">{{ $contra->description }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </x-filament::section>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Allergies Tab --}}
            @if($activeTab === 'allergies')
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500" />
                                {{ __('patients::medical_profile.sections.allergies') }}
                            </div>
                            <span class="text-sm text-gray-500">{{ $this->getAllergies()->count() }} {{ __('recorded') }}</span>
                        </div>
                    </x-slot>

                    {{-- Add Form --}}
                    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <x-heroicon-o-plus-circle class="w-4 h-4 text-primary-500" />
                            {{ __('patients::medical_profile.actions.add_allergy') }}
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.allergy.type') }} *</label>
                                <select wire:model="allergyData.allergy_type" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500">
                                    @foreach(\Modules\Patients\Models\MedicalAllergy::ALLERGY_TYPES as $value => $label)
                                        <option value="{{ $value }}">{{ __('patients::medical_profile.allergy_types.' . $value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.allergy.allergen') }} *</label>
                                <input type="text" wire:model="allergyData.allergen" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500" placeholder="{{ __('e.g., Penicillin') }}" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.allergy.severity') }} *</label>
                                <select wire:model="allergyData.severity" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500">
                                    @foreach(\Modules\Patients\Models\MedicalAllergy::SEVERITIES as $value => $label)
                                        <option value="{{ $value }}">{{ __('patients::medical_profile.allergy_severities.' . $value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-end">
                                <x-filament::button wire:click="addAllergy" class="w-full">
                                    {{ __('patients::medical_profile.actions.add_allergy') }}
                                </x-filament::button>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.allergy.reaction') }}</label>
                            <input type="text" wire:model="allergyData.reaction" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500" placeholder="{{ __('Describe the allergic reaction...') }}" />
                        </div>
                    </div>

                    {{-- List --}}
                    @if($this->getAllergies()->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($this->getAllergies() as $allergy)
                                <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $allergy->isCritical() ? 'bg-red-50 dark:bg-red-900/10 border-red-200 dark:border-red-800 hover:bg-red-100 dark:hover:bg-red-900/20' : '' }}">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-gray-900 dark:text-white">{{ $allergy->allergen }}</span>
                                            <span class="px-2 py-0.5 text-xs font-medium rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                                {{ __('patients::medical_profile.allergy_types.' . $allergy->allergy_type) }}
                                            </span>
                                            <span class="px-2 py-0.5 text-xs font-medium rounded {{ match($allergy->severity) {
                                                'mild' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                                'moderate' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                                                'severe' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                                                'life_threatening' => 'bg-red-600 text-white',
                                                default => 'bg-gray-100 text-gray-800',
                                            } }}">
                                                {{ __('patients::medical_profile.allergy_severities.' . $allergy->severity) }}
                                            </span>
                                        </div>
                                        @if($allergy->reaction)
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $allergy->reaction }}</p>
                                        @endif
                                    </div>
                                    <button wire:click="deleteAllergy('{{ $allergy->id }}')" wire:confirm="{{ __('Are you sure?') }}" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                        <x-heroicon-o-trash class="w-5 h-5" />
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-green-50 dark:bg-green-900/20 flex items-center justify-center">
                                <x-heroicon-o-check-circle class="w-8 h-8 text-green-500 dark:text-green-400" />
                            </div>
                            <p class="text-gray-500 dark:text-gray-400">{{ __('patients::medical_profile.no_allergies') }}</p>
                        </div>
                    @endif
                </x-filament::section>
            @endif

            {{-- Medications Tab --}}
            @if($activeTab === 'medications')
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-beaker class="w-5 h-5 text-blue-500" />
                                {{ __('patients::medical_profile.sections.medications') }}
                            </div>
                            <span class="text-sm text-gray-500">{{ $this->getMedications()->where('is_ongoing', true)->count() }} {{ __('active') }}</span>
                        </div>
                    </x-slot>

                    {{-- Add Form --}}
                    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <x-heroicon-o-plus-circle class="w-4 h-4 text-primary-500" />
                            {{ __('patients::medical_profile.actions.add_medication') }}
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.medication.name') }} *</label>
                                <input type="text" wire:model="medicationData.medication_name" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500" placeholder="{{ __('e.g., Metformin') }}" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.medication.dosage') }}</label>
                                <input type="text" wire:model="medicationData.dosage" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500" placeholder="{{ __('e.g., 500mg') }}" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.medication.frequency') }}</label>
                                <input type="text" wire:model="medicationData.frequency" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500" placeholder="{{ __('e.g., Twice daily') }}" />
                            </div>
                            <div class="flex items-end">
                                <x-filament::button wire:click="addMedication" class="w-full">
                                    {{ __('patients::medical_profile.actions.add_medication') }}
                                </x-filament::button>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center gap-4">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model="medicationData.affects_treatment" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('patients::medical_profile.medication.affects_treatment') }}</span>
                            </label>
                        </div>
                    </div>

                    {{-- List --}}
                    @if($this->getMedications()->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($this->getMedications() as $medication)
                                <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $medication->affects_treatment ? 'bg-amber-50 dark:bg-amber-900/10 border-amber-200 dark:border-amber-800 hover:bg-amber-100 dark:hover:bg-amber-900/20' : '' }}">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-gray-900 dark:text-white">{{ $medication->medication_name }}</span>
                                            @if($medication->dosage)
                                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $medication->dosage }}</span>
                                            @endif
                                            @if($medication->is_ongoing)
                                                <span class="px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                                    {{ __('patients::medical_profile.active') }}
                                                </span>
                                            @endif
                                            @if($medication->affects_treatment)
                                                <span class="px-2 py-0.5 text-xs font-medium rounded bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                                    {{ __('patients::medical_profile.affects_treatment') }}
                                                </span>
                                            @endif
                                        </div>
                                        @if($medication->frequency)
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $medication->frequency }}</p>
                                        @endif
                                    </div>
                                    <button wire:click="deleteMedication('{{ $medication->id }}')" wire:confirm="{{ __('Are you sure?') }}" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                        <x-heroicon-o-trash class="w-5 h-5" />
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                                <x-heroicon-o-beaker class="w-8 h-8 text-blue-500 dark:text-blue-400" />
                            </div>
                            <p class="text-gray-500 dark:text-gray-400">{{ __('patients::medical_profile.no_medications') }}</p>
                        </div>
                    @endif
                </x-filament::section>
            @endif

            {{-- Contraindications Tab --}}
            @if($activeTab === 'contraindications')
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-shield-exclamation class="w-5 h-5 text-amber-500" />
                                {{ __('patients::medical_profile.sections.contraindications') }}
                            </div>
                            <span class="text-sm text-gray-500">{{ $this->getContraindications()->where('is_active', true)->count() }} {{ __('active') }}</span>
                        </div>
                    </x-slot>

                    {{-- Add Form --}}
                    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <x-heroicon-o-plus-circle class="w-4 h-4 text-primary-500" />
                            {{ __('patients::medical_profile.actions.add_contraindication') }}
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.contraindication.type') }} *</label>
                                <select wire:model="contraindicationData.contraindication_type" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500">
                                    @foreach(\Modules\Patients\Models\MedicalContraindication::TYPES as $value => $label)
                                        <option value="{{ $value }}">{{ __('patients::medical_profile.contraindication_types.' . $value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.contraindication.name') }} *</label>
                                <input type="text" wire:model="contraindicationData.name" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500" placeholder="{{ __('e.g., Active skin infection') }}" />
                            </div>
                            <div class="flex items-end">
                                <x-filament::button wire:click="addContraindication" class="w-full">
                                    {{ __('patients::medical_profile.actions.add_contraindication') }}
                                </x-filament::button>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center gap-4">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model="contraindicationData.block_booking" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('patients::medical_profile.contraindication.block_booking') }}</span>
                            </label>
                        </div>
                    </div>

                    {{-- List --}}
                    @if($this->getContraindications()->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($this->getContraindications() as $contra)
                                <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $contra->isAbsolute() ? 'bg-red-50 dark:bg-red-900/10 border-red-200 dark:border-red-800 hover:bg-red-100 dark:hover:bg-red-900/20' : '' }}">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-gray-900 dark:text-white">{{ $contra->name }}</span>
                                            <span class="px-2 py-0.5 text-xs font-medium rounded {{ match($contra->contraindication_type) {
                                                'absolute' => 'bg-red-600 text-white',
                                                'relative' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                                                'temporary' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                                default => 'bg-gray-100 text-gray-800',
                                            } }}">
                                                {{ __('patients::medical_profile.contraindication_types.' . $contra->contraindication_type) }}
                                            </span>
                                            @if($contra->block_booking)
                                                <span class="px-2 py-0.5 text-xs font-medium rounded bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                                    {{ __('patients::medical_profile.blocks_booking') }}
                                                </span>
                                            @endif
                                        </div>
                                        @if($contra->description)
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $contra->description }}</p>
                                        @endif
                                    </div>
                                    <button wire:click="deleteContraindication('{{ $contra->id }}')" wire:confirm="{{ __('Are you sure?') }}" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                        <x-heroicon-o-trash class="w-5 h-5" />
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-green-50 dark:bg-green-900/20 flex items-center justify-center">
                                <x-heroicon-o-check-circle class="w-8 h-8 text-green-500 dark:text-green-400" />
                            </div>
                            <p class="text-gray-500 dark:text-gray-400">{{ __('patients::medical_profile.no_contraindications') }}</p>
                        </div>
                    @endif
                </x-filament::section>
            @endif

            {{-- Medical History Tab --}}
            @if($activeTab === 'history')
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-document-text class="w-5 h-5 text-purple-500" />
                                {{ __('patients::medical_profile.sections.medical_history') }}
                            </div>
                        </div>
                    </x-slot>

                    {{-- Add Form --}}
                    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <x-heroicon-o-plus-circle class="w-4 h-4 text-primary-500" />
                            {{ __('patients::medical_profile.actions.add_history') }}
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.history.type') }} *</label>
                                <select wire:model="historyData.history_type" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500">
                                    @foreach(\Modules\Patients\Models\MedicalHistory::HISTORY_TYPES as $value => $label)
                                        <option value="{{ $value }}">{{ __('patients::medical_profile.history_types.' . $value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.history.name') }} *</label>
                                <input type="text" wire:model="historyData.name" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500" placeholder="{{ __('e.g., Diabetes Type 2') }}" />
                            </div>
                            <div class="flex items-end">
                                <x-filament::button wire:click="addHistory" class="w-full">
                                    {{ __('patients::medical_profile.actions.add_history') }}
                                </x-filament::button>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center gap-4">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model="historyData.is_ongoing" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('patients::medical_profile.history.is_ongoing') }}</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model="historyData.affects_treatment" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('patients::medical_profile.history.affects_treatment') }}</span>
                            </label>
                        </div>
                    </div>

                    {{-- List --}}
                    @if($this->getMedicalHistories()->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($this->getMedicalHistories() as $history)
                                <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-gray-900 dark:text-white">{{ $history->name }}</span>
                                            <span class="px-2 py-0.5 text-xs font-medium rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                                {{ __('patients::medical_profile.history_types.' . $history->history_type) }}
                                            </span>
                                            @if($history->is_ongoing)
                                                <span class="px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                                    {{ __('patients::medical_profile.ongoing') }}
                                                </span>
                                            @endif
                                        </div>
                                        @if($history->description)
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $history->description }}</p>
                                        @endif
                                    </div>
                                    <button wire:click="deleteHistory('{{ $history->id }}')" wire:confirm="{{ __('Are you sure?') }}" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                        <x-heroicon-o-trash class="w-5 h-5" />
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center">
                                <x-heroicon-o-document-text class="w-8 h-8 text-purple-500 dark:text-purple-400" />
                            </div>
                            <p class="text-gray-500 dark:text-gray-400">{{ __('patients::medical_profile.no_history') }}</p>
                        </div>
                    @endif
                </x-filament::section>
            @endif

            {{-- Skin Assessment Tab --}}
            @if($activeTab === 'skin')
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-eye class="w-5 h-5 text-pink-500" />
                                {{ __('patients::medical_profile.sections.skin_assessment') }}
                            </div>
                            <span class="text-sm text-gray-500">{{ $this->getSkinAssessments()->count() }} {{ __('recorded') }}</span>
                        </div>
                    </x-slot>

                    {{-- Add Form --}}
                    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <x-heroicon-o-plus-circle class="w-4 h-4 text-primary-500" />
                            {{ __('patients::medical_profile.actions.add_skin_assessment') }}
                        </h4>

                        {{-- Basic Skin Info --}}
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.skin.fitzpatrick_type') }} *</label>
                                <select wire:model="skinAssessmentData.fitzpatrick_type" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach(\Modules\Patients\Models\MedicalProfile::FITZPATRICK_SHORT as $value => $label)
                                        <option value="{{ $value }}">{{ __('patients::medical_profile.fitzpatrick_types.' . $value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.skin.skin_type') }} *</label>
                                <select wire:model="skinAssessmentData.skin_type_oily" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach(\Modules\Patients\Models\SkinAssessment::SKIN_OILY_TYPES as $value => $label)
                                        <option value="{{ $value }}">{{ __('patients::medical_profile.skin_oily_types.' . $value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.skin.sensitivity') }} *</label>
                                <select wire:model="skinAssessmentData.skin_sensitivity" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach(\Modules\Patients\Models\SkinAssessment::SENSITIVITY_LEVELS as $value => $label)
                                        <option value="{{ $value }}">{{ __('patients::medical_profile.skin_sensitivity_levels.' . $value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.skin.texture') }}</label>
                                <select wire:model="skinAssessmentData.skin_texture" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach(\Modules\Patients\Models\SkinAssessment::TEXTURE_LEVELS as $value => $label)
                                        <option value="{{ $value }}">{{ __('patients::medical_profile.skin_texture_levels.' . $value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Additional Characteristics --}}
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.skin.pore_size') }}</label>
                                <select wire:model="skinAssessmentData.pore_size" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach(\Modules\Patients\Models\SkinAssessment::PORE_SIZES as $value => $label)
                                        <option value="{{ $value }}">{{ __('patients::medical_profile.skin_pore_sizes.' . $value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.skin.skin_tone') }}</label>
                                <select wire:model="skinAssessmentData.skin_tone" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach(\Modules\Patients\Models\SkinAssessment::SKIN_TONE_LEVELS as $value => $label)
                                        <option value="{{ $value }}">{{ __('patients::medical_profile.skin_tone_levels.' . $value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.skin.aging_level') }}</label>
                                <select wire:model="skinAssessmentData.aging_level" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach(\Modules\Patients\Models\SkinAssessment::AGING_LEVELS as $value => $label)
                                        <option value="{{ $value }}">{{ __('patients::medical_profile.skin_aging_levels.' . $value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.skin.sun_damage_level') }}</label>
                                <select wire:model="skinAssessmentData.sun_damage_level" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach(\Modules\Patients\Models\SkinAssessment::SUN_DAMAGE_LEVELS as $value => $label)
                                        <option value="{{ $value }}">{{ __('patients::medical_profile.skin_sun_damage_levels.' . $value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Current Conditions (Checkboxes) --}}
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('patients::medical_profile.skin.current_conditions') }}</label>
                            <div class="flex flex-wrap gap-3">
                                @foreach(\Modules\Patients\Models\SkinAssessment::COMMON_CONDITIONS as $value => $label)
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" value="{{ $value }}" wire:model="skinAssessmentData.current_conditions" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700" />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('patients::medical_profile.skin_conditions.' . $value) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Areas of Concern (Checkboxes) --}}
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('patients::medical_profile.skin.areas_of_concern') }}</label>
                            <div class="flex flex-wrap gap-3">
                                @foreach(\Modules\Patients\Models\SkinAssessment::AREAS_OF_CONCERN as $value => $label)
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" value="{{ $value }}" wire:model="skinAssessmentData.areas_of_concern" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700" />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('patients::medical_profile.skin_areas_of_concern.' . $value) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Clinical Observations & Recommendations --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.skin.clinical_observations') }}</label>
                                <textarea wire:model="skinAssessmentData.clinical_observations" rows="3" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500" placeholder="{{ __('Enter observations...') }}"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.skin.recommendations') }}</label>
                                <textarea wire:model="skinAssessmentData.recommendations" rows="3" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500" placeholder="{{ __('Enter recommendations...') }}"></textarea>
                            </div>
                        </div>

                        <x-filament::button wire:click="addSkinAssessment" class="w-full md:w-auto">
                            {{ __('patients::medical_profile.actions.add_skin_assessment') }}
                        </x-filament::button>
                    </div>

                    {{-- List of Assessments --}}
                    @if($this->getSkinAssessments()->isNotEmpty())
                        <div class="space-y-4">
                            @foreach($this->getSkinAssessments() as $assessment)
                                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $assessment->isHighSensitivity() ? 'bg-amber-50 dark:bg-amber-900/10 border-amber-200 dark:border-amber-800 hover:bg-amber-100 dark:hover:bg-amber-900/20' : '' }}">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-gray-900 dark:text-white">
                                                {{ $assessment->created_at->format('M d, Y') }}
                                            </span>
                                            @if($loop->first)
                                                <span class="px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                                    {{ __('Latest') }}
                                                </span>
                                            @endif
                                        </div>
                                        <button wire:click="deleteSkinAssessment('{{ $assessment->id }}')" wire:confirm="{{ __('Are you sure?') }}" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                            <x-heroicon-o-trash class="w-5 h-5" />
                                        </button>
                                    </div>

                                    {{-- Skin Properties - Compact Inline --}}
                                    <div class="flex flex-wrap gap-3 text-sm">
                                        <div class="inline-flex items-center gap-2 px-3 py-2 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('patients::medical_profile.skin.fitzpatrick_type') }}:</span>
                                            <span class="font-semibold text-gray-900 dark:text-white">{{ $assessment->fitzpatrick_type ? __('patients::medical_profile.fitzpatrick_types.' . $assessment->fitzpatrick_type) : '-' }}</span>
                                        </div>
                                        <div class="inline-flex items-center gap-2 px-3 py-2 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('patients::medical_profile.skin.skin_type') }}:</span>
                                            <span class="font-semibold text-gray-900 dark:text-white">{{ $assessment->skin_type_oily ? __('patients::medical_profile.skin_oily_types.' . $assessment->skin_type_oily) : '-' }}</span>
                                        </div>
                                        <div class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border {{ $assessment->isHighSensitivity() ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-700' : 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700' }}">
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('patients::medical_profile.skin.sensitivity') }}:</span>
                                            <span class="font-semibold {{ $assessment->isHighSensitivity() ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                                                {{ $assessment->skin_sensitivity ? __('patients::medical_profile.skin_sensitivity_levels.' . $assessment->skin_sensitivity) : '-' }}
                                            </span>
                                        </div>
                                        <div class="inline-flex items-center gap-2 px-3 py-2 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('patients::medical_profile.skin.texture') }}:</span>
                                            <span class="font-semibold text-gray-900 dark:text-white">{{ $assessment->skin_texture ? __('patients::medical_profile.skin_texture_levels.' . $assessment->skin_texture) : '-' }}</span>
                                        </div>
                                    </div>

                                    @if(!empty($assessment->current_conditions))
                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('patients::medical_profile.skin.current_conditions') }}:</span>
                                            @foreach($assessment->current_conditions as $condition)
                                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-300">
                                                    {{ __('patients::medical_profile.skin_conditions.' . $condition) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if($assessment->clinical_observations)
                                        <div class="mt-3 text-sm text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-800 rounded-lg px-3 py-2 border border-gray-100 dark:border-gray-700">
                                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('patients::medical_profile.skin.clinical_observations') }}:</span>
                                            {{ $assessment->clinical_observations }}
                                        </div>
                                    @endif

                                    @if(!empty($assessment->getTreatmentConsiderations()))
                                        <div class="mt-3 px-3 py-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg">
                                            <div class="flex items-center gap-2 text-amber-700 dark:text-amber-300 text-sm">
                                                <x-heroicon-o-exclamation-triangle class="w-4 h-4 flex-shrink-0" />
                                                <span class="font-medium">{{ __('patients::medical_profile.treatment_considerations') }}:</span>
                                                <span class="text-amber-600 dark:text-amber-400">
                                                    {{ implode(' • ', $assessment->getTreatmentConsiderations()) }}
                                                </span>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-pink-50 dark:bg-pink-900/20 flex items-center justify-center">
                                <x-heroicon-o-eye class="w-8 h-8 text-pink-500 dark:text-pink-400" />
                            </div>
                            <p class="text-gray-500 dark:text-gray-400">{{ __('patients::medical_profile.no_skin_assessment') }}</p>
                        </div>
                    @endif
                </x-filament::section>
            @endif

            {{-- Lifestyle Tab --}}
            @if($activeTab === 'lifestyle')
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-heart class="w-5 h-5 text-green-500" />
                            {{ __('patients::medical_profile.sections.lifestyle') }}
                        </div>
                    </x-slot>

                    <div class="space-y-6">
                        {{-- Smoking --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.lifestyle.smoking_status') }}</label>
                                <select wire:model.live="lifestyleData.smoking_status" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach(\Modules\Patients\Models\LifestyleInfo::SMOKING_STATUS as $value => $label)
                                        <option value="{{ $value }}">{{ __('patients::medical_profile.smoking_status_options.' . $value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.lifestyle.alcohol_status') }}</label>
                                <select wire:model.live="lifestyleData.alcohol_status" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach(\Modules\Patients\Models\LifestyleInfo::ALCOHOL_STATUS as $value => $label)
                                        <option value="{{ $value }}">{{ __('patients::medical_profile.alcohol_status_options.' . $value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.lifestyle.exercise_level') }}</label>
                                <select wire:model.live="lifestyleData.exercise_level" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach(\Modules\Patients\Models\LifestyleInfo::EXERCISE_LEVELS as $value => $label)
                                        <option value="{{ $value }}">{{ __('patients::medical_profile.exercise_level_options.' . $value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Sun Exposure --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('patients::medical_profile.lifestyle.sun_exposure') }}</label>
                                <select wire:model.live="lifestyleData.sun_exposure_level" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach(\Modules\Patients\Models\LifestyleInfo::SUN_EXPOSURE_LEVELS as $value => $label)
                                        <option value="{{ $value }}">{{ __('patients::medical_profile.sun_exposure_options.' . $value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-end gap-4">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" wire:model.live="lifestyleData.uses_sunscreen" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('patients::medical_profile.lifestyle.uses_sunscreen') }}</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" wire:model.live="lifestyleData.uses_tanning_beds" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('patients::medical_profile.lifestyle.uses_tanning_beds') }}</span>
                                </label>
                            </div>
                        </div>

                        <x-filament::button wire:click="saveLifestyle" class="w-full md:w-auto">
                            {{ __('patients::medical_profile.actions.save_lifestyle') }}
                        </x-filament::button>
                    </div>
                </x-filament::section>
            @endif
        </div>
    </div>
</x-filament-panels::page>
