<x-filament-panels::page>
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
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ $appointment->patient->full_name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $appointment->start_time?->format('H:i') }} - {{ $appointment->service?->translated_name }}
                                        </div>
                                        @if($appointment->treatmentPlanAppointment)
                                            <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                Session {{ $appointment->treatmentPlanAppointment->session_number }} of {{ $appointment->treatmentPlanAppointment->item->recommended_sessions }}
                                            </div>
                                        @endif
                                    </div>
                                    <button
                                        wire:click="resumeSession('{{ $appointment->id }}')"
                                        wire:loading.attr="disabled"
                                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2"
                                    >
                                        <x-heroicon-o-play class="w-4 h-4" />
                                        {{ __('booking::dashboard.actions.resume') }}
                                    </button>
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
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ $appointment->patient->full_name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $appointment->start_time?->format('H:i') }} - {{ $appointment->service?->translated_name }}
                                        </div>
                                        @if($appointment->treatmentPlanAppointment)
                                            <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                Session {{ $appointment->treatmentPlanAppointment->session_number }} of {{ $appointment->treatmentPlanAppointment->item->recommended_sessions }}
                                            </div>
                                        @endif
                                    </div>
                                    <button
                                        wire:click="startSession('{{ $appointment->id }}')"
                                        wire:loading.attr="disabled"
                                        class="px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg transition-colors"
                                    >
                                        {{ __('booking::dashboard.actions.start') }}
                                    </button>
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
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ $appointment->patient->full_name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $appointment->start_time?->format('H:i') }} - {{ $appointment->service?->translated_name }}
                                        </div>
                                        @if($appointment->treatmentPlanAppointment)
                                            <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                Session {{ $appointment->treatmentPlanAppointment->session_number }} of {{ $appointment->treatmentPlanAppointment->item->recommended_sessions }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button
                                            wire:click="startSession('{{ $appointment->id }}')"
                                            wire:loading.attr="disabled"
                                            class="px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg transition-colors"
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
                        <div class="px-4 py-8 text-center">
                            <x-heroicon-o-calendar class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600" />
                            <p class="mt-2 text-gray-500 dark:text-gray-400">{{ __('booking::dashboard.queue.empty') }}</p>
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
                                        @if($appointment->treatmentPlanAppointment)
                                            <div class="text-xs text-green-600 dark:text-green-400 mt-1">
                                                Session {{ $appointment->treatmentPlanAppointment->session_number }} of {{ $appointment->treatmentPlanAppointment->item->recommended_sessions }}
                                            </div>
                                        @endif
                                    </div>
                                    <x-heroicon-o-check-circle class="w-5 h-5 text-green-500" />
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="px-4 py-8 text-center">
                            <x-heroicon-o-clipboard-document-check class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600" />
                            <p class="mt-2 text-gray-500 dark:text-gray-400">{{ __('booking::dashboard.workspace.no_session') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
