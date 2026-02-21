<x-filament-widgets::widget>
    @if($isAtLimit)
        <div class="fi-wi-stats-overview-stat relative rounded-xl bg-red-50 p-6 dark:bg-red-900/20">
            <div class="flex items-center gap-x-3">
                <x-heroicon-o-exclamation-triangle class="h-8 w-8 text-red-500" />
                <div>
                    <p class="text-lg font-semibold text-red-600 dark:text-red-400">
                        {{ __('core::core.branch_limit_reached') }}
                    </p>
                    <p class="text-sm text-red-500 dark:text-red-300">
                        {{ __('core::core.branch_limit_reached_message', ['max' => $maxBranches, 'current' => $currentCount]) }}
                    </p>
                </div>
            </div>
        </div>
    @elseif($isNearLimit)
        <div class="fi-wi-stats-overview-stat relative rounded-xl bg-yellow-50 p-6 dark:bg-yellow-900/20">
            <div class="flex items-center gap-x-3">
                <x-heroicon-o-exclamation-circle class="h-8 w-8 text-yellow-500" />
                <div>
                    <p class="text-lg font-semibold text-yellow-600 dark:text-yellow-400">
                        {{ __('core::core.branch_limit_near') }}
                    </p>
                    <p class="text-sm text-yellow-500 dark:text-yellow-300">
                        {{ __('core::core.branches_used', ['current' => $currentCount, 'max' => $maxBranches]) }}
                    </p>
                </div>
            </div>
        </div>
    @else
        <div class="fi-wi-stats-overview-stat relative rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-x-3">
                    <x-heroicon-o-building-office-2 class="h-6 w-6 text-gray-400" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('core::core.branches_used', ['current' => $currentCount, 'max' => $maxBranches]) }}
                    </span>
                </div>
                <div class="flex items-center gap-x-2">
                    <div class="h-2 w-32 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        @php
                            $percentage = $maxBranches > 0 ? min(100, ($currentCount / $maxBranches) * 100) : 0;
                        @endphp
                        <div class="h-full rounded-full bg-primary-500" style="width: {{ $percentage }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
