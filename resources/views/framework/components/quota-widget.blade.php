@props([
    'quotas' => [],
    'compact' => false,
    'showUpgrade' => true
])

@php
    $isRtl = app()->getLocale() === 'ar';
@endphp

<div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 {{ $compact ? 'p-3' : 'p-4' }}">
    @if(!$compact)
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                {{ __('Resource Usage') }}
            </h3>

            @if($showUpgrade)
                <a
                    href="{{ route('billing.plans') }}"
                    class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200"
                >
                    {{ __('Upgrade') }}
                    <x-heroicon-s-arrow-top-right-on-square class="w-3 h-3 {{ $isRtl ? 'mr-1' : 'ml-1' }}"/>
                </a>
            @endif
        </div>
    @endif

    <div class="space-y-{{ $compact ? '2' : '3' }}">
        @forelse($quotas as $resource => $quota)
            @php
                $percentage = $quota['percentage'];
                $current = $quota['current'];
                $limit = $quota['limit'];
                $isUnlimited = $limit === -1;
                $isOverLimit = $percentage >= 100;
                $isNearLimit = $percentage >= 80 && !$isOverLimit;

                $barColorClass = match(true) {
                    $isOverLimit => 'bg-red-500',
                    $isNearLimit => 'bg-yellow-500',
                    default => 'bg-green-500'
                };

                $textColorClass = match(true) {
                    $isOverLimit => 'text-red-600 dark:text-red-400',
                    $isNearLimit => 'text-yellow-600 dark:text-yellow-400',
                    default => 'text-green-600 dark:text-green-400'
                };

                $bgColorClass = match(true) {
                    $isOverLimit => 'bg-red-100 dark:bg-red-900/20',
                    $isNearLimit => 'bg-yellow-100 dark:bg-yellow-900/20',
                    default => 'bg-green-100 dark:bg-green-900/20'
                };

                $resourceLabel = match($resource) {
                    'users' => __('Users'),
                    'patients' => __('Patients'),
                    'appointments' => __('Appointments'),
                    'treatments' => __('Treatments'),
                    'storage' => __('Storage'),
                    'api_requests' => __('API Requests'),
                    'email_sent' => __('Emails Sent'),
                    'sms_sent' => __('SMS Sent'),
                    default => ucfirst(str_replace('_', ' ', $resource))
                };
            @endphp

            <div class="flex items-center justify-between {{ $compact ? 'text-xs' : 'text-sm' }}">
                <div class="flex items-center gap-2 min-w-0 flex-1">
                    <!-- Resource Icon -->
                    <div class="flex-shrink-0">
                        @php
                            $icon = match($resource) {
                                'users' => 'users',
                                'patients' => 'user-group',
                                'appointments' => 'calendar',
                                'treatments' => 'heart',
                                'storage' => 'server',
                                'api_requests' => 'globe-alt',
                                'email_sent' => 'envelope',
                                'sms_sent' => 'device-phone-mobile',
                                default => 'chart-bar'
                            };
                        @endphp
                        <div class="w-4 h-4 {{ $textColorClass }}">
                            <x-dynamic-component :component="'heroicon-s-' . $icon" class="w-4 h-4"/>
                        </div>
                    </div>

                    <!-- Resource Name & Progress Bar -->
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-medium text-gray-900 dark:text-gray-100 truncate">
                                {{ $resourceLabel }}
                            </span>

                            <span class="{{ $textColorClass }} text-xs font-mono">
                                @if($isUnlimited)
                                    {{ number_format($current) }} / {{ __('Unlimited') }}
                                @else
                                    {{ number_format($current) }} / {{ number_format($limit) }}
                                @endif
                            </span>
                        </div>

                        <!-- Progress Bar -->
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                            <div
                                class="{{ $barColorClass }} h-1.5 rounded-full transition-all duration-300"
                                style="width: {{ $isUnlimited ? '0' : min(100, $percentage) }}%"
                            ></div>
                        </div>

                        @if(!$compact && ($isOverLimit || $isNearLimit))
                            <div class="mt-1 text-xs {{ $textColorClass }}">
                                @if($isOverLimit)
                                    <div class="flex items-center gap-1">
                                        <x-heroicon-s-exclamation-triangle class="w-3 h-3"/>
                                        {{ __('Limit exceeded') }}
                                    </div>
                                @else
                                    <div class="flex items-center gap-1">
                                        <x-heroicon-s-exclamation-circle class="w-3 h-3"/>
                                        {{ __('Approaching limit') }}
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Percentage Badge -->
                <div class="flex-shrink-0 {{ $isRtl ? 'mr-2' : 'ml-2' }}">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $bgColorClass }} {{ $textColorClass }}">
                        {{ $isUnlimited ? '∞' : number_format($percentage, 0) }}{{ $isUnlimited ? '' : '%' }}
                    </span>
                </div>
            </div>
        @empty
            <!-- Empty State -->
            <div class="text-center py-4">
                <x-heroicon-o-chart-bar class="mx-auto h-6 w-6 text-gray-400"/>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('No quota data available') }}
                </p>
            </div>
        @endforelse

        @if(!$compact && collect($quotas)->some(fn($q) => $q['percentage'] >= 80))
            <!-- Action Buttons for High Usage -->
            <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                <div class="flex gap-2">
                    <button
                        type="button"
                        onclick="window.open('{{ route('reports.usage') }}', '_blank')"
                        class="flex-1 inline-flex items-center justify-center px-3 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600"
                    >
                        <x-heroicon-s-chart-bar class="w-3 h-3 {{ $isRtl ? 'ml-1' : 'mr-1' }}"/>
                        {{ __('View Details') }}
                    </button>

                    @if($showUpgrade)
                        <button
                            type="button"
                            onclick="window.open('{{ route('billing.plans') }}', '_blank')"
                            class="flex-1 inline-flex items-center justify-center px-3 py-2 text-xs font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"
                        >
                            <x-heroicon-s-arrow-trending-up class="w-3 h-3 {{ $isRtl ? 'ml-1' : 'mr-1' }}"/>
                            {{ __('Upgrade Plan') }}
                        </button>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <!-- Last Updated -->
    @if(!$compact)
        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
            <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
                {{ __('Last updated') }}: {{ now()->format('H:i') }}
            </p>
        </div>
    @endif
</div>