@props([
    'states',
    'currentState',
    'transitions' => [],
    'model' => null,
    'compact' => false
])

@php
    $stateKeys = array_keys($states);
    $currentIndex = array_search($currentState, $stateKeys);
    $isRtl = app()->getLocale() === 'ar';
@endphp

<div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
    @if(!$compact)
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                {{ __('Status') }}
            </h3>
            @if($transitions)
                <div class="flex gap-2">
                    @foreach($transitions as $transition => $label)
                        <button
                            type="button"
                            wire:click="transition('{{ $transition }}')"
                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <!-- Status Timeline -->
    <div class="flex items-center {{ $isRtl ? 'flex-row-reverse' : '' }} space-x-4 {{ $isRtl ? 'space-x-reverse' : '' }}">
        @foreach($states as $stateKey => $stateData)
            @php
                $stateIndex = array_search($stateKey, $stateKeys);
                $isPast = $stateIndex < $currentIndex;
                $isCurrent = $stateKey === $currentState;
                $isFuture = $stateIndex > $currentIndex;

                $dotClass = match(true) {
                    $isCurrent => 'bg-blue-600 ring-4 ring-blue-100 dark:ring-blue-900',
                    $isPast => 'bg-green-500',
                    default => 'bg-gray-300 dark:bg-gray-600'
                };

                $lineClass = $isPast ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600';
            @endphp

            <!-- State Dot -->
            <div class="flex flex-col items-center">
                <div class="flex items-center">
                    @if(!$loop->first)
                        <div class="w-8 h-0.5 {{ $lineClass }} {{ $isRtl ? 'ml-2' : 'mr-2' }}"></div>
                    @endif

                    <div class="relative">
                        <div class="w-4 h-4 rounded-full {{ $dotClass }} transition-all duration-200"></div>

                        @if($isCurrent)
                            <div class="absolute inset-0 w-4 h-4 rounded-full bg-blue-600 animate-ping opacity-20"></div>
                        @endif
                    </div>

                    @if(!$loop->last)
                        <div class="w-8 h-0.5 {{ $isFuture ? 'bg-gray-300 dark:bg-gray-600' : $lineClass }} {{ $isRtl ? 'mr-2' : 'ml-2' }}"></div>
                    @endif
                </div>

                <!-- State Label -->
                <div class="mt-2 text-center">
                    <div class="text-xs font-medium {{ $isCurrent ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400' }}">
                        {{ is_array($stateData) ? ($stateData['label'] ?? $stateKey) : $stateData }}
                    </div>

                    @if($isCurrent && isset($stateData['description']))
                        <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                            {{ $stateData['description'] }}
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <!-- Navigation Arrows (if not compact) -->
    @if(!$compact && ($currentIndex > 0 || $currentIndex < count($stateKeys) - 1))
        <div class="flex items-center justify-between mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
            @if($currentIndex > 0)
                <button
                    type="button"
                    class="flex items-center text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    @if($isRtl)
                        <x-heroicon-s-chevron-right class="w-4 h-4 ml-1"/>
                    @else
                        <x-heroicon-s-chevron-left class="w-4 h-4 mr-1"/>
                    @endif
                    {{ __('Previous') }}
                </button>
            @else
                <div></div>
            @endif

            @if($currentIndex < count($stateKeys) - 1)
                <button
                    type="button"
                    class="flex items-center text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    {{ __('Next') }}
                    @if($isRtl)
                        <x-heroicon-s-chevron-left class="w-4 h-4 mr-1"/>
                    @else
                        <x-heroicon-s-chevron-right class="w-4 h-4 ml-1"/>
                    @endif
                </button>
            @else
                <div></div>
            @endif
        </div>
    @endif
</div>