<x-filament-panels::page>
    @php
        $data = $this->getSubscriptionData();
        $tenant = $data['tenant'];
        $plan = $data['plan'];
        $addOns = $data['add_ons'];
        $usage = $data['usage'];
    @endphp

    <div class="space-y-6">
        {{-- Current Plan Card --}}
        <x-filament::section>
            <x-slot name="heading">Current Plan</x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $plan?->name ?? 'No Plan' }}
                    </h3>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">
                        {{ $plan?->description ?? 'Please contact support to set up your subscription.' }}
                    </p>

                    @if($plan)
                        <div class="mt-4">
                            <span class="text-3xl font-bold text-primary-600">
                                EGP {{ number_format(($plan->price_monthly_minor ?? 0) / 100) }}
                            </span>
                            <span class="text-gray-500">/month</span>
                        </div>
                    @endif
                </div>

                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 dark:text-white mb-3">Subscription Status</h4>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Status</dt>
                            <dd>
                                <x-filament::badge :color="match($tenant?->subscription_status) {
                                    'active' => 'success',
                                    'trial' => 'info',
                                    'past_due' => 'warning',
                                    'suspended' => 'danger',
                                    default => 'gray',
                                }">
                                    {{ ucfirst($tenant?->subscription_status ?? 'Unknown') }}
                                </x-filament::badge>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Billing Cycle</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ ucfirst($tenant?->billing_cycle ?? 'Monthly') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Next Invoice</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ $tenant?->subscription_expires_at?->format('M d, Y') ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </x-filament::section>

        {{-- Usage Section --}}
        <x-filament::section>
            <x-slot name="heading">Usage & Limits</x-slot>

            <div class="grid grid-cols-4 gap-3">
                {{-- Users --}}
                @php
                    $usersOverLimit = is_numeric($usage['max_users']) && $usage['max_users'] !== '∞' && $usage['users'] > $usage['max_users'];
                    $usersNearLimit = is_numeric($usage['max_users']) && $usage['max_users'] !== '∞' && $usage['users'] >= ($usage['max_users'] * 0.8);
                @endphp
                <div @class([
                    'rounded-md p-2.5',
                    'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800' => $usersOverLimit,
                    'bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800' => $usersNearLimit && !$usersOverLimit,
                    'bg-gray-50 dark:bg-gray-800' => !$usersNearLimit,
                ])>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs text-gray-500">Users</span>
                        <span @class([
                            'text-xs font-semibold',
                            'text-red-600 dark:text-red-400' => $usersOverLimit,
                            'text-yellow-600 dark:text-yellow-400' => $usersNearLimit && !$usersOverLimit,
                        ])>{{ $usage['users'] }}/{{ is_numeric($usage['max_users']) ? $usage['max_users'] : '∞' }}</span>
                    </div>
                    @if(is_numeric($usage['max_users']) && $usage['max_users'] > 0)
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                            <div @class([
                                'h-1.5 rounded-full',
                                'bg-red-500' => $usersOverLimit,
                                'bg-yellow-500' => $usersNearLimit && !$usersOverLimit,
                                'bg-primary-600' => !$usersNearLimit,
                            ]) style="width: {{ min(100, ($usage['users'] / $usage['max_users']) * 100) }}%"></div>
                        </div>
                    @endif
                </div>

                {{-- Branches --}}
                <div class="bg-gray-50 dark:bg-gray-800 rounded-md p-2.5">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs text-gray-500">Branches</span>
                        <span class="text-xs font-semibold">{{ $usage['branches'] }}/{{ is_numeric($usage['max_branches']) ? $usage['max_branches'] : '∞' }}</span>
                    </div>
                    @if(is_numeric($usage['max_branches']) && $usage['max_branches'] > 0)
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                            <div class="bg-primary-600 h-1.5 rounded-full" style="width: {{ min(100, ($usage['branches'] / $usage['max_branches']) * 100) }}%"></div>
                        </div>
                    @endif
                </div>

                {{-- Patients --}}
                <div class="bg-gray-50 dark:bg-gray-800 rounded-md p-2.5">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs text-gray-500">Patients</span>
                        <span class="text-xs font-semibold">{{ number_format($usage['patients']) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                        <div class="bg-green-500 h-1.5 rounded-full" style="width: 100%"></div>
                    </div>
                </div>

                {{-- Storage --}}
                <div class="bg-gray-50 dark:bg-gray-800 rounded-md p-2.5">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs text-gray-500">Storage</span>
                        <span class="text-xs font-semibold">{{ round($usage['storage_mb'] / 1024, 1) }}GB/{{ is_numeric($usage['max_storage_mb']) ? round($usage['max_storage_mb'] / 1024) . 'GB' : '∞' }}</span>
                    </div>
                    @if(is_numeric($usage['max_storage_mb']) && $usage['max_storage_mb'] > 0)
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                            <div class="bg-primary-600 h-1.5 rounded-full" style="width: {{ min(100, ($usage['storage_mb'] / $usage['max_storage_mb']) * 100) }}%"></div>
                        </div>
                    @endif
                </div>
            </div>
        </x-filament::section>

        {{-- Active Add-Ons --}}
        <x-filament::section>
            <x-slot name="heading">Active Add-Ons</x-slot>

            @if($addOns->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($addOns as $addon)
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $addon->name }}</p>
                                <p class="text-sm text-gray-500">{{ $addon->description }}</p>
                            </div>
                            <span class="text-primary-600 font-medium">
                                EGP {{ number_format($addon->price_monthly_minor / 100) }}/mo
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <x-heroicon-o-puzzle-piece class="w-12 h-12 mx-auto text-gray-400" />
                    <p class="mt-2 text-gray-500">No active add-ons</p>
                    <p class="text-sm text-gray-400">Click "Request Add-On" above to add features to your plan.</p>
                </div>
            @endif
        </x-filament::section>

        {{-- Available Plans Comparison --}}
        <x-filament::section>
            <x-slot name="heading">Available Plans</x-slot>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($this->getAvailablePlans() as $availablePlan)
                    <div @class([
                        'border rounded-lg p-4',
                        'border-primary-500 bg-primary-50 dark:bg-primary-900/20' => $plan?->id === $availablePlan->id,
                        'border-gray-200 dark:border-gray-700' => $plan?->id !== $availablePlan->id,
                    ])>
                        @if($plan?->id === $availablePlan->id)
                            <span class="inline-block px-2 py-1 text-xs font-medium bg-primary-500 text-white rounded mb-2">Current Plan</span>
                        @endif
                        <h4 class="font-bold text-gray-900 dark:text-white">{{ $availablePlan->name }}</h4>
                        <p class="text-2xl font-bold text-primary-600 mt-2">
                            EGP {{ number_format($availablePlan->price_monthly_minor / 100) }}
                            <span class="text-sm font-normal text-gray-500">/mo</span>
                        </p>
                        <ul class="mt-4 space-y-2 text-sm text-gray-600 dark:text-gray-400">
                            <li>{{ $availablePlan->max_users ?? '∞' }} Users</li>
                            <li>{{ $availablePlan->max_branches ?? '∞' }} Branches</li>
                            <li>Unlimited Patients</li>
                            <li>{{ $availablePlan->max_storage_mb ? round($availablePlan->max_storage_mb / 1024) . ' GB' : 'Unlimited' }} Storage</li>
                        </ul>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
