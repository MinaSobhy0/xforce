<div class="space-y-4">
    @forelse($subscriptions as $subscription)
        @php
            $package = $subscription->package;
            $daysRemaining = $subscription->days_until_expiry;
            $isExpiringSoon = $daysRemaining !== null && $daysRemaining <= 30;
            $progress = $subscription->usage_progress;
        @endphp
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
            {{-- Header --}}
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                        <x-heroicon-o-gift class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-900 dark:text-white">
                            {{ $package->translated_name }}
                        </h4>
                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ __('packages::packages.fields.purchased_at') }}: {{ $subscription->purchased_at->format('M d, Y') }}</span>
                            @if($isExpiringSoon)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                                    <x-heroicon-s-exclamation-triangle class="w-3 h-3 mr-1" />
                                    {{ __('packages::packages.labels.expires_in', ['days' => $daysRemaining]) }}
                                </span>
                            @else
                                <span>{{ __('packages::packages.fields.expires_at') }}: {{ $subscription->expires_at?->format('M d, Y') ?? '-' }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if($subscription->hasBalance())
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                            {{ __('packages::packages.labels.balance_due') }}: {{ $subscription->formatted_balance }}
                        </span>
                    @endif
                    <a href="{{ route('filament.tenant.pages.create-booking', ['patient_id' => $patientId, 'package_subscription_id' => $subscription->id, 'booking_type' => 'package']) }}"
                       class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md bg-primary-600 text-white hover:bg-primary-700 transition-colors">
                        <x-heroicon-s-calendar class="w-4 h-4 mr-1" />
                        {{ __('packages::packages.actions.book_session') }}
                    </a>
                </div>
            </div>

            {{-- Progress Bar --}}
            @php
                $totalSessions = $package->total_sessions ?? 0;
                $sessionsUsed = $subscription->sessions_used;
                $sessionsBooked = $subscription->sessions_booked;
                $sessionsAvailable = $subscription->sessions_available;
                $usedProgress = $totalSessions > 0 ? ($sessionsUsed / $totalSessions) * 100 : 0;
                $bookedProgress = $totalSessions > 0 ? ($sessionsBooked / $totalSessions) * 100 : 0;
            @endphp
            <div class="px-4 py-2 bg-gray-50/50 dark:bg-gray-900/50">
                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                    <span>{{ __('packages::packages.labels.usage_progress') }}</span>
                    <span>
                        {{ $sessionsUsed }} {{ __('packages::packages.labels.sessions_consumed') }}
                        @if($sessionsBooked > 0)
                            · {{ $sessionsBooked }} {{ __('packages::packages.labels.sessions_booked') }}
                        @endif
                        · {{ $sessionsAvailable }} {{ __('packages::packages.labels.sessions_available') }}
                    </span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 relative overflow-hidden">
                    {{-- Used sessions (solid color) --}}
                    <div class="absolute inset-y-0 left-0 bg-primary-600 transition-all duration-300"
                         style="width: {{ min($usedProgress, 100) }}%"></div>
                    {{-- Booked sessions (striped/lighter color) --}}
                    <div class="absolute inset-y-0 bg-primary-400 transition-all duration-300"
                         style="left: {{ min($usedProgress, 100) }}%; width: {{ min($bookedProgress, 100 - $usedProgress) }}%"></div>
                </div>
                @if($sessionsBooked > 0)
                    <div class="flex items-center gap-4 mt-1 text-xs text-gray-400 dark:text-gray-500">
                        <span class="flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-primary-600"></span>
                            {{ __('packages::packages.labels.consumed') }}
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-primary-400"></span>
                            {{ __('packages::packages.labels.booked') }}
                        </span>
                    </div>
                @endif
            </div>

            {{-- Services Grid --}}
            <div class="px-4 py-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($package->items as $item)
                        @php
                            $service = $item->service;
                            $usedForService = $subscription->getSessionsUsedByService($item->service_id);
                            $bookedForService = $subscription->getSessionsBookedByService($item->service_id);
                            $availableForService = $subscription->getSessionsAvailableByService($item->service_id);
                            $totalForService = $item->quantity;
                            $usedPercent = $totalForService > 0 ? ($usedForService / $totalForService) * 100 : 0;
                            $bookedPercent = $totalForService > 0 ? ($bookedForService / $totalForService) * 100 : 0;
                            $isSessionBased = $item->consumption_type === 'sessions';
                            $isFullyUsed = $availableForService <= 0 && $bookedForService <= 0;
                        @endphp
                        <div class="rounded-lg border border-gray-100 dark:border-gray-700 p-3 bg-gray-50/50 dark:bg-gray-800/50 {{ $isFullyUsed ? 'opacity-50' : '' }}">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1 min-w-0">
                                    <h5 class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        {{ $service?->translated_name ?? 'Service' }}
                                    </h5>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        @if($isSessionBased)
                                            {{ $availableForService }} {{ __('packages::packages.labels.sessions_available') }}
                                            @if($bookedForService > 0)
                                                <span class="text-primary-500">({{ $bookedForService }} {{ __('packages::packages.labels.booked') }})</span>
                                            @endif
                                        @else
                                            {{ $item->pulses_per_session ? ($availableForService * $item->pulses_per_session) : $availableForService }} {{ __('packages::packages.labels.pulses_remaining') }}
                                        @endif
                                    </p>
                                </div>
                                <div class="flex-shrink-0 ml-2">
                                    @if($availableForService > 0 || $bookedForService > 0)
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-success-100 dark:bg-success-900 text-success-700 dark:text-success-300 text-sm font-semibold">
                                            {{ $availableForService }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-400 text-sm">
                                            <x-heroicon-s-check class="w-5 h-5" />
                                        </span>
                                    @endif
                                </div>
                            </div>
                            {{-- Mini progress bar --}}
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 relative overflow-hidden">
                                {{-- Used portion --}}
                                <div class="absolute inset-y-0 left-0 {{ $isFullyUsed ? 'bg-gray-400' : 'bg-success-500' }} transition-all duration-300"
                                     style="width: {{ min($usedPercent, 100) }}%"></div>
                                {{-- Booked portion --}}
                                @if($bookedForService > 0)
                                    <div class="absolute inset-y-0 bg-primary-400 transition-all duration-300"
                                         style="left: {{ min($usedPercent, 100) }}%; width: {{ min($bookedPercent, 100 - $usedPercent) }}%"></div>
                                @endif
                            </div>
                            <div class="mt-1 text-xs text-gray-400 dark:text-gray-500 text-right">
                                {{ $usedForService }}@if($bookedForService > 0)+{{ $bookedForService }}@endif/{{ $totalForService }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @empty
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <x-heroicon-o-gift class="w-12 h-12 mx-auto mb-2 opacity-50" />
            <p>{{ __('packages::packages.messages.no_active_packages') }}</p>
        </div>
    @endforelse
</div>
