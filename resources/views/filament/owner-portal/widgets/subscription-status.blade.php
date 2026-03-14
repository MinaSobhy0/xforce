<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            My Subscription
        </x-slot>

        @php
            $data = $this->getSubscriptionData();
            $statusColors = [
                'active' => 'success',
                'trial' => 'info',
                'past_due' => 'warning',
                'suspended' => 'danger',
                'cancelled' => 'gray',
            ];
        @endphp

        <div style="display: flex; flex-direction: row; gap: 12px; flex-wrap: wrap;">
            {{-- Plan Name --}}
            <div style="flex: 1; min-width: 140px;" class="bg-gray-50 dark:bg-gray-800 rounded-md p-2.5">
                <p class="text-xs text-gray-500 dark:text-gray-400">Current Plan</p>
                <p class="text-base font-bold text-gray-900 dark:text-white">{{ $data['plan_name'] }}</p>
            </div>

            {{-- Status --}}
            <div style="flex: 1; min-width: 140px;" class="bg-gray-50 dark:bg-gray-800 rounded-md p-2.5">
                <p class="text-xs text-gray-500 dark:text-gray-400">Status</p>
                <x-filament::badge :color="$statusColors[$data['status']] ?? 'gray'" class="mt-0.5">
                    {{ ucfirst($data['status']) }}
                    @if($data['is_trial'])
                        ({{ ceil($data['days_remaining']) }} days)
                    @endif
                </x-filament::badge>
            </div>

            {{-- Billing Cycle --}}
            <div style="flex: 1; min-width: 140px;" class="bg-gray-50 dark:bg-gray-800 rounded-md p-2.5">
                <p class="text-xs text-gray-500 dark:text-gray-400">Billing Cycle</p>
                <p class="text-base font-bold text-gray-900 dark:text-white">{{ ucfirst($data['billing_cycle']) }}</p>
            </div>

            {{-- Next Invoice --}}
            <div style="flex: 1; min-width: 140px;" class="bg-gray-50 dark:bg-gray-800 rounded-md p-2.5">
                <p class="text-xs text-gray-500 dark:text-gray-400">Next Invoice</p>
                <p class="text-base font-bold text-gray-900 dark:text-white">{{ $data['next_invoice'] }}</p>
            </div>
        </div>

        @if($data['is_trial'])
            <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-information-circle class="w-5 h-5 text-blue-500" />
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        Your trial ends on <strong>{{ $data['trial_ends_at'] }}</strong>.
                        <a href="{{ route('filament.admin.pages.my-subscription') }}" class="underline font-medium">Upgrade now</a> to continue using all features.
                    </p>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
