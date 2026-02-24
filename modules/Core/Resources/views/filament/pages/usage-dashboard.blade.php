<x-filament-panels::page>
    {{-- Monthly Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @foreach($this->getStatsItems() as $stat)
            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-lg bg-{{ $stat['color'] }}-100 dark:bg-{{ $stat['color'] }}-900/20">
                        <x-dynamic-component
                            :component="$stat['icon']"
                            class="w-6 h-6 text-{{ $stat['color'] }}-600 dark:text-{{ $stat['color'] }}-400"
                        />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stat['value'] }}</p>
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>

    {{-- Account Limits --}}
    <x-filament::section>
        <x-slot name="heading">
            {{ __('core::core.account_limits') }}
        </x-slot>
        <x-slot name="description">
            {{ __('core::core.account_limits_description') }}
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach($this->getUsageItems() as $item)
                @php
                    $percentage = $this->calculatePercentage($item['current'], $item['limit']);
                    $color = $this->getPercentageColor($percentage);
                @endphp
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <x-dynamic-component
                                :component="$item['icon']"
                                class="w-5 h-5 text-gray-500"
                            />
                            <span class="font-medium text-gray-900 dark:text-white">{{ $item['label'] }}</span>
                        </div>
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $item['current'] }}{{ isset($item['suffix']) ? ' ' . $item['suffix'] : '' }}
                            /
                            {{ $item['limit'] }}{{ isset($item['suffix']) ? ' ' . $item['suffix'] : '' }}
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                        <div
                            class="h-2.5 rounded-full transition-all duration-300
                                @if($color === 'danger') bg-danger-500
                                @elseif($color === 'warning') bg-warning-500
                                @else bg-success-500
                                @endif"
                            style="width: {{ $percentage }}%"
                        ></div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 text-right">{{ $percentage }}% used</p>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    {{-- Unlimited Resources --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">
            {{ __('core::core.unlimited_resources') }}
        </x-slot>
        <x-slot name="description">
            {{ __('core::core.unlimited_resources_description') }}
        </x-slot>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach($this->getUnlimitedItems() as $item)
                <div class="flex items-center gap-3 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="p-2 rounded-lg bg-{{ $item['color'] }}-100 dark:bg-{{ $item['color'] }}-900/20">
                        <x-dynamic-component
                            :component="$item['icon']"
                            class="w-5 h-5 text-{{ $item['color'] }}-600 dark:text-{{ $item['color'] }}-400"
                        />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['label'] }}</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($item['current']) }}</p>
                        <p class="text-xs text-success-600 dark:text-success-400">{{ __('core::core.unlimited') }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

</x-filament-panels::page>
