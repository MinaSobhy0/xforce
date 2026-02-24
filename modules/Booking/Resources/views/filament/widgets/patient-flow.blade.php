<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('booking::reception.flow.title') }}
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4" wire:poll.15s>
            @php
                $flowData = $this->getPatientFlowData();
                $lanes = $this->getFlowLanes();

                // Color mapping for Tailwind classes (must be static for JIT)
                $colorClasses = [
                    'info' => [
                        'bg' => 'bg-blue-50 dark:bg-blue-900/20',
                        'header' => 'bg-blue-100 dark:bg-blue-800/50',
                        'border' => 'border-blue-200 dark:border-blue-700',
                        'icon' => 'text-blue-600 dark:text-blue-400',
                        'badge' => 'bg-blue-500 text-white',
                    ],
                    'warning' => [
                        'bg' => 'bg-amber-50 dark:bg-amber-900/20',
                        'header' => 'bg-amber-100 dark:bg-amber-800/50',
                        'border' => 'border-amber-200 dark:border-amber-700',
                        'icon' => 'text-amber-600 dark:text-amber-400',
                        'badge' => 'bg-amber-500 text-white',
                    ],
                    'secondary' => [
                        'bg' => 'bg-gray-50 dark:bg-gray-800/50',
                        'header' => 'bg-gray-100 dark:bg-gray-700/50',
                        'border' => 'border-gray-200 dark:border-gray-700',
                        'icon' => 'text-gray-600 dark:text-gray-400',
                        'badge' => 'bg-gray-500 text-white',
                    ],
                    'primary' => [
                        'bg' => 'bg-purple-50 dark:bg-purple-900/20',
                        'header' => 'bg-purple-100 dark:bg-purple-800/50',
                        'border' => 'border-purple-200 dark:border-purple-700',
                        'icon' => 'text-purple-600 dark:text-purple-400',
                        'badge' => 'bg-purple-500 text-white',
                    ],
                    'success' => [
                        'bg' => 'bg-green-50 dark:bg-green-900/20',
                        'header' => 'bg-green-100 dark:bg-green-800/50',
                        'border' => 'border-green-200 dark:border-green-700',
                        'icon' => 'text-green-600 dark:text-green-400',
                        'badge' => 'bg-green-500 text-white',
                    ],
                    'danger' => [
                        'bg' => 'bg-red-50 dark:bg-red-900/20',
                        'header' => 'bg-red-100 dark:bg-red-800/50',
                        'border' => 'border-red-200 dark:border-red-700',
                        'icon' => 'text-red-600 dark:text-red-400',
                        'badge' => 'bg-red-500 text-white',
                    ],
                ];

                $waitTimeClasses = [
                    'success' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                    'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-800 dark:text-amber-100',
                    'danger' => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
                ];
            @endphp

            @foreach($lanes as $key => $lane)
                @php
                    $appointments = $flowData[$key] ?? collect();
                    $colors = $colorClasses[$lane['color']] ?? $colorClasses['secondary'];
                @endphp

                <div class="flex flex-col rounded-xl border-2 {{ $colors['border'] }} overflow-hidden min-h-[400px]">
                    {{-- Lane Header --}}
                    <div class="flex items-center justify-between px-4 py-3 {{ $colors['header'] }}">
                        <div class="flex items-center gap-2">
                            <x-dynamic-component
                                :component="$lane['icon']"
                                class="w-5 h-5 {{ $colors['icon'] }}"
                            />
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $lane['label'] }}
                            </span>
                        </div>
                        <span class="inline-flex items-center justify-center w-7 h-7 text-sm font-bold rounded-full {{ $colors['badge'] }}">
                            {{ $appointments->count() }}
                        </span>
                    </div>

                    {{-- Lane Content --}}
                    <div class="flex-1 p-3 space-y-3 overflow-y-auto {{ $colors['bg'] }}">
                        @forelse($appointments as $appointment)
                            @php
                                $waitTime = $this->calculateWaitTime($appointment);
                                $waitClass = $waitTime ? ($waitTimeClasses[$waitTime['severity']] ?? $waitTimeClasses['success']) : '';
                            @endphp

                            <div class="p-3 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-shadow">
                                {{-- Patient Name --}}
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                        {{ $appointment->patient?->full_name ?? __('booking::reception.unknown_patient') }}
                                    </span>
                                    @if($waitTime)
                                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-bold rounded-full {{ $waitClass }}">
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
                                    @if($appointment->room)
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
                        @empty
                            <div class="flex flex-col items-center justify-center py-8 text-gray-400 dark:text-gray-500">
                                <x-dynamic-component
                                    :component="$lane['icon']"
                                    class="w-8 h-8 mb-2 opacity-50"
                                />
                                <span class="text-xs">{{ __('booking::reception.flow.empty') }}</span>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
