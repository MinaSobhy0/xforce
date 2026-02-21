<x-filament-panels::page>
    {{-- Loyalty Summary --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-filament::section>
            <div class="text-center">
                <x-heroicon-o-star class="w-12 h-12 mx-auto text-warning-500 mb-2" />
                <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($totalPoints) }}</p>
                <p class="text-sm text-gray-500">{{ __('patientportal::portal.total_points') }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <x-heroicon-o-trophy class="w-12 h-12 mx-auto text-primary-500 mb-2" />
                <p class="text-3xl font-bold text-gray-900 dark:text-white capitalize">{{ $tier }}</p>
                <p class="text-sm text-gray-500">{{ __('patientportal::portal.current_tier') }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <x-heroicon-o-arrow-trending-up class="w-12 h-12 mx-auto text-success-500 mb-2" />
                @if($nextTier)
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($pointsToNextTier) }}</p>
                    <p class="text-sm text-gray-500">{{ __('patientportal::portal.points_to_tier', ['tier' => ucfirst($nextTier)]) }}</p>
                @else
                    <p class="text-xl font-bold text-success-600">{{ __('patientportal::portal.max_tier') }}</p>
                    <p class="text-sm text-gray-500">{{ __('patientportal::portal.congratulations') }}</p>
                @endif
            </div>
        </x-filament::section>
    </div>

    {{-- Points Breakdown --}}
    <x-filament::section class="mb-6">
        <x-slot name="heading">
            {{ __('patientportal::portal.points_breakdown') }}
        </x-slot>

        <div class="grid grid-cols-2 gap-4">
            <div class="text-center p-4 bg-success-50 dark:bg-success-900/20 rounded-lg">
                <p class="text-2xl font-bold text-success-600">+{{ number_format($earnedPoints) }}</p>
                <p class="text-sm text-gray-500">{{ __('patientportal::portal.points_earned') }}</p>
            </div>
            <div class="text-center p-4 bg-danger-50 dark:bg-danger-900/20 rounded-lg">
                <p class="text-2xl font-bold text-danger-600">-{{ number_format($redeemedPoints) }}</p>
                <p class="text-sm text-gray-500">{{ __('patientportal::portal.points_redeemed') }}</p>
            </div>
        </div>
    </x-filament::section>

    {{-- Transaction History --}}
    <x-filament::section>
        <x-slot name="heading">
            {{ __('patientportal::portal.transaction_history') }}
        </x-slot>

        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
