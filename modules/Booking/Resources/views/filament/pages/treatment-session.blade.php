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
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Medical Information --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-heart class="w-5 h-5 text-red-500" />
                        {{ __('booking::session.sections.medical_info') }}
                    </div>
                </x-slot>

                @if($medicalHistory)
                    <div class="grid grid-cols-4 gap-3 mb-4">
                        <div class="text-center p-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('booking::session.medical.fitzpatrick') }}</div>
                            <div class="font-semibold text-gray-900 dark:text-white text-sm">{{ $medicalHistory->fitzpatrick_type ?? '-' }}</div>
                        </div>
                        <div class="text-center p-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('booking::session.medical.blood_type') }}</div>
                            <div class="font-semibold text-gray-900 dark:text-white text-sm">{{ $medicalHistory->blood_type ?? '-' }}</div>
                        </div>
                        <div class="text-center p-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('booking::session.medical.bmi') }}</div>
                            <div class="font-semibold text-gray-900 dark:text-white text-sm">{{ $medicalHistory->bmi ?? '-' }}</div>
                        </div>
                        <div class="text-center p-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('booking::session.medical.smoker') }}</div>
                            <div class="font-semibold text-sm {{ $medicalHistory->is_smoker ? 'text-red-600' : 'text-green-600' }}">
                                {{ $medicalHistory->is_smoker ? __('Yes') : __('No') }}
                            </div>
                        </div>
                    </div>

                    @if(!empty($this->getMedications()))
                        <div class="mb-3">
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('booking::session.medical.medications') }}</div>
                            <div class="flex flex-wrap gap-1">
                                @foreach($this->getMedications() as $med)
                                    <span class="px-2 py-0.5 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded text-xs">{{ $med }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(!empty($this->getMedicalConditions()))
                        <div>
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('booking::session.medical.conditions') }}</div>
                            <div class="flex flex-wrap gap-1">
                                @foreach($this->getMedicalConditions() as $condition)
                                    <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded text-xs">
                                        {{ \Modules\Patients\Models\PatientMedicalHistory::COMMON_CONDITIONS[$condition] ?? $condition }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @else
                    <div class="text-center py-4 text-gray-500 dark:text-gray-400 text-sm">
                        <x-heroicon-o-document-text class="w-8 h-8 mx-auto mb-2 opacity-50" />
                        {{ __('booking::session.medical.no_history') }}
                    </div>
                @endif
            </x-filament::section>

            {{-- Current Treatment Plan --}}
            @php $currentPlan = $this->getCurrentTreatmentPlan(); @endphp
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-clipboard-document-list class="w-5 h-5 text-blue-500" />
                        {{ __('booking::session.sections.current_plan') }}
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
                                <div class="flex items-center justify-between py-1 px-2 bg-gray-50 dark:bg-gray-800 rounded text-xs">
                                    <span class="text-gray-700 dark:text-gray-300">{{ $item->service?->translated_name }}</span>
                                    <span class="font-medium {{ $item->completed_sessions >= $item->recommended_sessions ? 'text-green-600' : 'text-gray-600 dark:text-gray-400' }}">
                                        {{ $item->completed_sessions }}/{{ $item->recommended_sessions }}
                                    </span>
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

        {{-- Equipment & Parameters Section (if service has parameters) --}}
        @if($this->hasServiceParameters())
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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
                        <div class="space-y-2 mb-3">
                            @foreach($sessionEquipment as $index => $equipment)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg {{ $equipment['is_preset'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' }}">
                                    <div class="flex items-center gap-2 p-2">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-gray-900 dark:text-white text-sm">{{ $equipment['name'] }}</span>
                                                @if($equipment['is_preset'])
                                                    <span class="px-1.5 py-0.5 text-xs bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 rounded">{{ __('booking::session.equipment.preset') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        @if(!$equipment['is_preset'])
                                            <button wire:click="removeEquipment('{{ $equipment['equipment_id'] }}')" class="p-1 text-gray-400 hover:text-red-500 rounded">
                                                <x-heroicon-o-x-mark class="w-4 h-4" />
                                            </button>
                                        @endif
                                    </div>

                                    @if(!empty($equipment['has_tracking']))
                                        @php $equipmentParams = $this->getEquipmentParametersByCategory($equipment['equipment_id']); @endphp
                                        @if(!empty($equipmentParams))
                                            <div class="border-t border-gray-200 dark:border-gray-700 p-2 bg-gray-50/50 dark:bg-gray-800/50">
                                                @foreach($equipmentParams as $category => $categoryData)
                                                    @if(count($categoryData['parameters']) > 0)
                                                        <div class="grid grid-cols-2 gap-2">
                                                            @foreach($categoryData['parameters'] as $param)
                                                                <div>
                                                                    <label class="block text-xs text-gray-600 dark:text-gray-400 mb-0.5">
                                                                        {{ $param->name }}
                                                                        @if($param->unit)<span class="text-gray-400">({{ $param->unit }})</span>@endif
                                                                    </label>
                                                                    @if($param->value_type === 'select')
                                                                        <select wire:change="updateEquipmentParameterValue('{{ $equipment['equipment_id'] }}', '{{ $param->parameter_key }}', $event.target.value)" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-xs py-1">
                                                                            <option value="">-</option>
                                                                            @foreach($param->options ?? [] as $option)
                                                                                <option value="{{ $option['value'] }}" {{ ($equipmentParameterValues[$equipment['equipment_id']][$param->parameter_key] ?? '') == $option['value'] ? 'selected' : '' }}>{{ $option['label'] }}</option>
                                                                            @endforeach
                                                                        </select>
                                                                    @else
                                                                        <input type="{{ in_array($param->value_type, ['integer', 'decimal']) ? 'number' : 'text' }}" value="{{ $equipmentParameterValues[$equipment['equipment_id']][$param->parameter_key] ?? $param->default_value }}" wire:change="updateEquipmentParameterValue('{{ $equipment['equipment_id'] }}', '{{ $param->parameter_key }}', $event.target.value)" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-xs py-1" @if($param->min_value !== null) min="{{ $param->min_value }}" @endif @if($param->max_value !== null) max="{{ $param->max_value }}" @endif />
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
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

                {{-- Clinical Notes --}}
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-document-text class="w-5 h-5 text-gray-400" />
                            {{ __('booking::session.sections.clinical_notes') }}
                        </div>
                    </x-slot>

                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('booking::session.clinical.skin_reaction') }}</label>
                            <select wire:model.live="skinReaction" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                                @foreach($this->getSkinReactionOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('booking::session.clinical.pain_level') }}</label>
                            <select wire:model.live="painLevel" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                                <option value="">-</option>
                                @for($i = 0; $i <= 10; $i++)
                                    <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('booking::session.clinical.observations') }}</label>
                        <textarea wire:model.blur="clinicalNotes" rows="2" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm" placeholder="{{ __('booking::session.clinical.observations_placeholder') }}"></textarea>
                    </div>

                    <x-filament::button wire:click="saveClinicalNotes" size="sm" class="w-full">
                        {{ __('booking::session.clinical.save') }}
                    </x-filament::button>
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
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Consumables Section --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-beaker class="w-5 h-5 text-orange-500" />
                            {{ __('booking::session.sections.consumables') }}
                        </div>
                        @if(count($sessionConsumables) > 0)
                            <span class="text-sm font-medium text-gray-500">{{ number_format($this->getTotalConsumablesCost(), 2) }}</span>
                        @endif
                    </div>
                </x-slot>

                <div class="flex gap-2 mb-3 p-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <select wire:model="newConsumableId" class="flex-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                        <option value="">{{ __('booking::session.consumables.select') }}</option>
                        @foreach($this->getAvailableConsumables() as $consumable)
                            <option value="{{ $consumable->id }}">{{ $consumable->getTranslation('name', app()->getLocale()) }}</option>
                        @endforeach
                    </select>
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
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-700 dark:text-gray-300">{{ number_format($consumable['total_cost'], 2) }}</span>
                                    <button wire:click="removeConsumable('{{ $consumable['id'] }}')" class="text-red-500 hover:text-red-700">
                                        <x-heroicon-o-x-mark class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-3 text-gray-500 dark:text-gray-400 text-sm">{{ __('booking::session.consumables.none') }}</div>
                @endif
            </x-filament::section>

            {{-- Products Section --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-shopping-bag class="w-5 h-5 text-green-500" />
                            {{ __('booking::session.sections.products') }}
                        </div>
                        @if(count($sessionProducts) > 0)
                            <span class="text-sm font-medium text-gray-500">{{ number_format($this->getTotalProductsValue(), 2) }}</span>
                        @endif
                    </div>
                </x-slot>

                <div class="flex gap-2 mb-3 p-2 bg-gray-50 dark:bg-gray-800 rounded-lg flex-wrap">
                    <select wire:model="newProductId" class="flex-1 min-w-[120px] border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                        <option value="">{{ __('booking::session.products.select') }}</option>
                        @foreach($this->getAvailableProducts() as $product)
                            <option value="{{ $product->id }}">{{ $product->getTranslation('name', app()->getLocale()) }}</option>
                        @endforeach
                    </select>
                    <input type="number" wire:model="newProductQty" class="w-14 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm text-center" min="1" placeholder="Qty" />
                    <select wire:model="newProductUsageType" class="w-24 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                        <option value="applied">{{ __('booking::session.products.applied') }}</option>
                        <option value="sold">{{ __('booking::session.products.sold') }}</option>
                    </select>
                    <x-filament::button wire:click="addProduct" size="sm">
                        <x-heroicon-o-plus class="w-4 h-4" />
                    </x-filament::button>
                </div>

                @if(count($sessionProducts) > 0)
                    <div class="space-y-1">
                        @foreach($sessionProducts as $product)
                            <div class="flex items-center justify-between p-2 border border-gray-200 dark:border-gray-700 rounded text-sm">
                                <div>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $product['product_name'] }}</span>
                                    <span class="px-1.5 py-0.5 text-xs rounded ml-1 {{ $product['usage_type'] === 'sold' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' }}">
                                        {{ $product['usage_type'] === 'sold' ? __('booking::session.products.sold') : __('booking::session.products.applied') }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-700 dark:text-gray-300">{{ number_format($product['total_price'], 2) }}</span>
                                    <button wire:click="removeProduct('{{ $product['id'] }}')" class="text-red-500 hover:text-red-700">
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

        {{-- Session Notes & Photos Row --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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
                    <div class="grid grid-cols-4 gap-2">
                        @foreach($photos as $photo)
                            <div class="group relative w-20 h-20 rounded overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800">
                                @if($photo->getFirstMediaUrl('photos', 'thumb'))
                                    <a href="{{ $photo->getFirstMediaUrl('photos') }}" target="_blank">
                                        <img src="{{ $photo->getFirstMediaUrl('photos', 'thumb') }}" alt="{{ $photo->description }}" class="w-full h-full object-cover cursor-pointer hover:opacity-90" />
                                    </a>
                                @else
                                    <div class="w-full h-full flex items-center justify-center"><x-heroicon-o-photo class="w-6 h-6 text-gray-400" /></div>
                                @endif
                                {{-- Delete button - only for photos from current appointment --}}
                                @if($photo->appointment_id === $this->appointment?->id)
                                    <button
                                        wire:click="deletePhoto('{{ $photo->id }}')"
                                        wire:confirm="Are you sure you want to delete this photo?"
                                        class="absolute top-0.5 right-0.5 p-0.5 bg-red-500 text-white rounded opacity-0 group-hover:opacity-100 transition-opacity"
                                    >
                                        <x-heroicon-o-trash class="w-3 h-3" />
                                    </button>
                                @endif
                                {{-- Type badge --}}
                                <div class="absolute bottom-0 left-0 right-0 bg-black/50 text-white text-[10px] px-1 truncate">
                                    {{ $photo->type_label }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-3 text-gray-500 text-sm">{{ __('booking::session.photos.no_photos') }}</div>
                @endif
            </x-filament::section>
        </div>

        {{-- Previous Visits & Create Plan Row --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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
            <x-filament::section collapsible collapsed>
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
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('booking::session.plan.services') }}</label>
                        @foreach($treatmentPlanData['services'] ?? [] as $index => $service)
                            <div class="flex gap-1 mb-1">
                                <select wire:model="treatmentPlanData.services.{{ $index }}.service_id" class="flex-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-xs">
                                    <option value="">{{ __('booking::session.plan.select_service') }}</option>
                                    @foreach($this->getAvailableServices() as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                <input type="number" wire:model="treatmentPlanData.services.{{ $index }}.sessions" class="w-12 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-xs text-center" min="1" placeholder="#" />
                                <input type="number" wire:model="treatmentPlanData.services.{{ $index }}.interval" class="w-12 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-xs text-center" min="1" placeholder="d" />
                                @if(count($treatmentPlanData['services'] ?? []) > 1)
                                    <button wire:click="removeServiceRow({{ $index }})" class="text-red-500 p-1"><x-heroicon-o-x-mark class="w-4 h-4" /></button>
                                @endif
                            </div>
                        @endforeach
                        <button wire:click="addServiceRow" class="text-xs text-primary-600 hover:text-primary-700 flex items-center gap-1 mt-1">
                            <x-heroicon-o-plus class="w-3 h-3" />{{ __('booking::session.plan.add_service') }}
                        </button>
                    </div>

                    <x-filament::button wire:click="createTreatmentPlan" class="w-full" size="sm">{{ __('booking::session.plan.create') }}</x-filament::button>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
