<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('booking::reception.flow.title') }}
        </x-slot>

        <div class="space-y-4" wire:poll.15s>
            @php
                $flowData = $this->getPatientFlowData();
                $lanes = $this->getFlowLanes();

                // Color mapping for Tailwind classes (must be static for JIT)
                $colorClasses = [
                    'info' => [
                        'bg' => 'bg-blue-50 dark:bg-blue-900/20',
                        'border' => 'border-blue-200 dark:border-blue-700',
                        'icon' => 'text-blue-600 dark:text-blue-400',
                        'badge' => 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100',
                    ],
                    'warning' => [
                        'bg' => 'bg-amber-50 dark:bg-amber-900/20',
                        'border' => 'border-amber-200 dark:border-amber-700',
                        'icon' => 'text-amber-600 dark:text-amber-400',
                        'badge' => 'bg-amber-100 text-amber-800 dark:bg-amber-800 dark:text-amber-100',
                    ],
                    'secondary' => [
                        'bg' => 'bg-gray-50 dark:bg-gray-800/50',
                        'border' => 'border-gray-200 dark:border-gray-700',
                        'icon' => 'text-gray-600 dark:text-gray-400',
                        'badge' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-100',
                    ],
                    'primary' => [
                        'bg' => 'bg-purple-50 dark:bg-purple-900/20',
                        'border' => 'border-purple-200 dark:border-purple-700',
                        'icon' => 'text-purple-600 dark:text-purple-400',
                        'badge' => 'bg-purple-100 text-purple-800 dark:bg-purple-800 dark:text-purple-100',
                    ],
                    'success' => [
                        'bg' => 'bg-green-50 dark:bg-green-900/20',
                        'border' => 'border-green-200 dark:border-green-700',
                        'icon' => 'text-green-600 dark:text-green-400',
                        'badge' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                    ],
                    'danger' => [
                        'bg' => 'bg-red-50 dark:bg-red-900/20',
                        'border' => 'border-red-200 dark:border-red-700',
                        'icon' => 'text-red-600 dark:text-red-400',
                        'badge' => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
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

                <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    {{-- Lane Header --}}
                    <div class="flex items-center justify-between px-3 py-2 {{ $colors['bg'] }} border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            <x-dynamic-component
                                :component="$lane['icon']"
                                class="w-4 h-4 {{ $colors['icon'] }}"
                            />
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $lane['label'] }}
                            </span>
                        </div>
                        <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium rounded-full {{ $colors['badge'] }}">
                            {{ $appointments->count() }}
                        </span>
                    </div>

                    {{-- Lane Content --}}
                    <div class="p-2 space-y-2 max-h-48 overflow-y-auto bg-white dark:bg-gray-900">
                        @forelse($appointments as $appointment)
                            @php
                                $waitTime = $this->calculateWaitTime($appointment);
                                $waitClass = $waitTime ? ($waitTimeClasses[$waitTime['severity']] ?? $waitTimeClasses['success']) : '';
                            @endphp

                            <div class="flex items-center justify-between p-2 rounded-lg border border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                            {{ $appointment->patient?->full_name ?? __('booking::reception.unknown_patient') }}
                                        </span>
                                        @if($waitTime)
                                            <span class="inline-flex items-center px-1.5 py-0.5 text-xs font-medium rounded {{ $waitClass }}">
                                                {{ $waitTime['formatted'] }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $appointment->start_time?->format('H:i') }}
                                        </span>
                                        @if($appointment->service)
                                            <span class="text-xs text-gray-400 dark:text-gray-500">
                                                &bull;
                                            </span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                {{ Str::limit($appointment->service->name, 20) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                @if($appointment->room)
                                    <div class="flex-shrink-0 ml-2">
                                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                                            {{ $appointment->room->name }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ __('booking::reception.flow.empty') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
