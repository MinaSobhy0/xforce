<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Visit Info Header --}}
        <div class="rounded-xl bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 border border-purple-200 dark:border-purple-700 p-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-14 h-14 rounded-full bg-purple-100 dark:bg-purple-800 flex items-center justify-center">
                        <x-heroicon-o-ticket class="w-7 h-7 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div>
                        <div class="font-bold text-gray-900 dark:text-white text-xl">
                            {{ $patient?->full_name }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400 flex items-center gap-2 flex-wrap mt-1">
                            <span class="font-medium text-purple-600 dark:text-purple-400">{{ $visit?->code }}</span>
                            <span class="text-gray-300 dark:text-gray-600">|</span>
                            <span>{{ $patient?->code }}</span>
                            <span class="text-gray-300 dark:text-gray-600">|</span>
                            <span>{{ $patient?->phone }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-6 text-sm">
                    <div class="text-center">
                        <div class="text-gray-500 dark:text-gray-400">{{ __('booking::checkout.info.check_in') }}</div>
                        <div class="font-semibold text-gray-900 dark:text-white">{{ $visit?->check_in_at?->format('H:i') }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-gray-500 dark:text-gray-400">{{ __('booking::checkout.info.duration') }}</div>
                        <div class="font-semibold text-gray-900 dark:text-white">{{ $this->getVisitDuration() }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-gray-500 dark:text-gray-400">{{ __('booking::checkout.info.checked_in_by') }}</div>
                        <div class="font-semibold text-gray-900 dark:text-white">{{ $visit?->checkedInBy?->name ?? '-' }}</div>
                    </div>

                    {{-- Patient Balance --}}
                    @if($patient?->balance_minor != 0)
                        <div class="px-4 py-2 rounded-lg {{ $patient->balance_minor > 0 ? 'bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-800' : 'bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800' }}">
                            <div class="text-xs {{ $patient->balance_minor > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                {{ __('patients::patients.balance.title') }}
                            </div>
                            <div class="font-bold {{ $patient->balance_minor > 0 ? 'text-red-700 dark:text-red-300' : 'text-green-700 dark:text-green-300' }}">
                                {{ number_format(abs($patient->balance_minor) / 100, 2) }} {{ current_currency() }}
                                <span class="text-xs font-normal">
                                    {{ $patient->balance_minor > 0 ? __('patients::patients.balance.owes') : __('patients::patients.balance.credit') }}
                                </span>
                            </div>
                        </div>
                    @endif

                    @if($visit?->chief_complaint)
                        <div class="px-3 py-1 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 rounded-full text-xs">
                            {{ $visit->chief_complaint }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Left Column: Appointments & Products --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Open Sessions (Need Action) --}}
                @if($this->getOpenAppointments()->isNotEmpty())
                    <x-filament::section>
                        <x-slot name="heading">
                            <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                                <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                                {{ __('booking::checkout.sections.open_sessions') }}
                                <span class="px-2 py-0.5 bg-amber-100 dark:bg-amber-900/30 rounded-full text-xs font-medium">
                                    {{ $this->getOpenAppointments()->count() }}
                                </span>
                            </div>
                        </x-slot>

                        <div class="space-y-3">
                            @foreach($this->getOpenAppointments() as $appointment)
                                <div class="flex items-center justify-between p-4 bg-amber-50 dark:bg-amber-900/10 rounded-lg border border-amber-200 dark:border-amber-800">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-800 flex items-center justify-center">
                                            <x-heroicon-o-sparkles class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-white">
                                                {{ $appointment->service?->translated_name }}
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $appointment->practitioner?->full_name ?? '-' }}
                                                <span class="mx-1">|</span>
                                                <span class="px-1.5 py-0.5 text-xs rounded
                                                    @if($appointment->status === 'in_progress') bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300
                                                    @elseif($appointment->status === 'checked_in') bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300
                                                    @else bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400
                                                    @endif">
                                                    {{ __('booking::appointments.statuses.' . $appointment->status) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4">
                                        <div class="text-right">
                                            <div class="font-semibold text-gray-900 dark:text-white">
                                                @if($appointment->is_package_session)
                                                    <span class="text-blue-600 dark:text-blue-400">{{ __('booking::checkout.package_covered') }}</span>
                                                @else
                                                    {{ number_format(($appointment->net_price ?? $appointment->price_minor ?? 0) / 100, 2) }} {{ current_currency() }}
                                                @endif
                                            </div>
                                        </div>

                                        <select
                                            wire:change="updateSessionAction({{ $appointment->id }}, $event.target.value)"
                                            class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg focus:ring-purple-500 focus:border-purple-500"
                                        >
                                            <option value="complete" {{ ($sessionActions[$appointment->id] ?? '') === 'complete' ? 'selected' : '' }}>
                                                {{ __('booking::checkout.session_actions.complete') }}
                                            </option>
                                            <option value="cancel" {{ ($sessionActions[$appointment->id] ?? '') === 'cancel' ? 'selected' : '' }}>
                                                {{ __('booking::checkout.session_actions.cancel') }}
                                            </option>
                                            <option value="reschedule" {{ ($sessionActions[$appointment->id] ?? '') === 'reschedule' ? 'selected' : '' }}>
                                                {{ __('booking::checkout.session_actions.reschedule') }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif

                {{-- Completed Sessions --}}
                @if($this->getCompletedAppointments()->isNotEmpty())
                    <x-filament::section>
                        <x-slot name="heading">
                            <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
                                <x-heroicon-o-check-circle class="w-5 h-5" />
                                {{ __('booking::checkout.sections.completed_sessions') }}
                                <span class="px-2 py-0.5 bg-green-100 dark:bg-green-900/30 rounded-full text-xs font-medium">
                                    {{ $this->getCompletedAppointments()->count() }}
                                </span>
                            </div>
                        </x-slot>

                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($this->getCompletedAppointments() as $appointment)
                                <div class="flex items-center justify-between py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-green-100 dark:bg-green-800 flex items-center justify-center">
                                            <x-heroicon-o-check class="w-4 h-4 text-green-600 dark:text-green-400" />
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-white">
                                                {{ $appointment->service?->translated_name }}
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $appointment->practitioner?->full_name ?? '-' }}
                                                @if($appointment->treatmentPlanAppointment)
                                                    <span class="mx-1">|</span>
                                                    <span class="text-blue-600 dark:text-blue-400">
                                                        {{ __('booking::checkout.session_of', [
                                                            'current' => $appointment->treatmentPlanAppointment->session_number,
                                                            'total' => $appointment->treatmentPlanAppointment->item?->recommended_sessions ?? '?'
                                                        ]) }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        @if($appointment->is_package_session)
                                            <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded text-sm">
                                                {{ __('booking::checkout.package_covered') }}
                                            </span>
                                        @else
                                            <span class="font-semibold text-gray-900 dark:text-white">
                                                {{ number_format(($appointment->net_price ?? $appointment->price_minor ?? 0) / 100, 2) }} {{ current_currency() }}
                                            </span>
                                            @if($appointment->hasDiscount())
                                                <div class="text-xs text-red-500">
                                                    -{{ number_format($appointment->getDiscountAmountMinor() / 100, 2) }} {{ __('booking::checkout.discount') }}
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif

                {{-- Cancelled/No-Show Sessions --}}
                @if($this->getCancelledAppointments()->isNotEmpty())
                    <x-filament::section collapsed>
                        <x-slot name="heading">
                            <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                                <x-heroicon-o-x-circle class="w-5 h-5" />
                                {{ __('booking::checkout.sections.cancelled_sessions') }}
                                <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 rounded-full text-xs font-medium">
                                    {{ $this->getCancelledAppointments()->count() }}
                                </span>
                            </div>
                        </x-slot>

                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($this->getCancelledAppointments() as $appointment)
                                <div class="flex items-center justify-between py-3 opacity-60">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                            <x-heroicon-o-x-mark class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-600 dark:text-gray-400 line-through">
                                                {{ $appointment->service?->translated_name }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ __('booking::appointments.statuses.' . $appointment->status) }}
                                            </div>
                                        </div>
                                    </div>
                                    <span class="text-gray-400 line-through">
                                        {{ number_format(($appointment->price_minor ?? 0) / 100, 2) }} {{ current_currency() }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif

                {{-- Products Sold --}}
                @if($this->getSoldProducts()->isNotEmpty())
                    <x-filament::section>
                        <x-slot name="heading">
                            <div class="flex items-center gap-2 text-purple-600 dark:text-purple-400">
                                <x-heroicon-o-shopping-bag class="w-5 h-5" />
                                {{ __('booking::checkout.sections.products') }}
                                <span class="px-2 py-0.5 bg-purple-100 dark:bg-purple-900/30 rounded-full text-xs font-medium">
                                    {{ $this->getSoldProducts()->count() }}
                                </span>
                            </div>
                        </x-slot>

                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($this->getSoldProducts() as $product)
                                <div class="flex items-center justify-between py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-800 flex items-center justify-center">
                                            <x-heroicon-o-cube class="w-4 h-4 text-purple-600 dark:text-purple-400" />
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-white">
                                                {{ $product->product?->translated_name ?? $product->product?->name }}
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $product->quantity }} x {{ number_format($product->unit_price_minor / 100, 2) }} {{ current_currency() }}
                                            </div>
                                        </div>
                                    </div>
                                    <span class="font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($product->total_price_minor / 100, 2) }} {{ current_currency() }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif

                {{-- Package Purchases --}}
                @if($this->getPendingPackages()->isNotEmpty())
                    <x-filament::section>
                        <x-slot name="heading">
                            <div class="flex items-center gap-2 text-indigo-600 dark:text-indigo-400">
                                <x-heroicon-o-gift class="w-5 h-5" />
                                {{ __('booking::checkout.sections.packages') }}
                                <span class="px-2 py-0.5 bg-indigo-100 dark:bg-indigo-900/30 rounded-full text-xs font-medium">
                                    {{ $this->getPendingPackages()->count() }}
                                </span>
                            </div>
                        </x-slot>

                        <div class="space-y-4">
                            @foreach($this->getPendingPackages() as $package)
                                @php
                                    $priceMinor = $package->pivot->package_price_minor;
                                    $paymentOption = $packagePaymentOptions[$package->id] ?? 'full';
                                    $depositPercent = $package->min_deposit_percent ?? 100;
                                    $depositAmount = (int) ceil($priceMinor * $depositPercent / 100);
                                    $canPayDeposit = $depositPercent < 100;
                                @endphp
                                <div class="p-4 bg-indigo-50 dark:bg-indigo-900/10 rounded-lg border border-indigo-200 dark:border-indigo-800">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-12 h-12 rounded-lg bg-indigo-100 dark:bg-indigo-800 flex items-center justify-center">
                                                <x-heroicon-o-gift class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                                            </div>
                                            <div>
                                                <div class="font-semibold text-gray-900 dark:text-white">
                                                    {{ $package->translated_name }}
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    @if($package->isPulseBased())
                                                        {{ number_format($package->total_pulses) }} {{ __('packages::packages.labels.pulses') }}
                                                    @else
                                                        {{ $package->total_sessions }} {{ __('packages::packages.labels.sessions') }}
                                                    @endif
                                                    <span class="mx-1">|</span>
                                                    {{ $package->validity_days }} {{ __('packages::packages.labels.days_validity') }}
                                                </div>
                                                <div class="text-lg font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                                                    {{ number_format($priceMinor / 100, 2) }} {{ current_currency() }}
                                                </div>
                                            </div>
                                        </div>
                                        <button
                                            wire:click="removePendingPackage({{ $package->id }})"
                                            class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                            title="{{ __('booking::checkout.actions.remove_package') }}"
                                        >
                                            <x-heroicon-o-trash class="w-5 h-5" />
                                        </button>
                                    </div>

                                    @if($canPayDeposit)
                                        <div class="mt-4 pt-4 border-t border-indigo-200 dark:border-indigo-700">
                                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                {{ __('booking::checkout.labels.payment_option') }}
                                            </div>
                                            <div class="flex gap-3">
                                                <label class="flex-1 cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        name="package_payment_{{ $package->id }}"
                                                        value="full"
                                                        wire:click="updatePackagePaymentOption({{ $package->id }}, 'full')"
                                                        {{ $paymentOption === 'full' ? 'checked' : '' }}
                                                        class="sr-only peer"
                                                    />
                                                    <div class="p-3 rounded-lg border-2 text-center transition-all
                                                        peer-checked:border-green-500 peer-checked:bg-green-50 dark:peer-checked:bg-green-900/20
                                                        border-gray-200 dark:border-gray-600 hover:border-gray-300">
                                                        <div class="font-semibold text-gray-900 dark:text-white">
                                                            {{ __('booking::checkout.payment_options.full') }}
                                                        </div>
                                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                                            {{ number_format($priceMinor / 100, 2) }} {{ current_currency() }}
                                                        </div>
                                                    </div>
                                                </label>
                                                <label class="flex-1 cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        name="package_payment_{{ $package->id }}"
                                                        value="deposit"
                                                        wire:click="updatePackagePaymentOption({{ $package->id }}, 'deposit')"
                                                        {{ $paymentOption === 'deposit' ? 'checked' : '' }}
                                                        class="sr-only peer"
                                                    />
                                                    <div class="p-3 rounded-lg border-2 text-center transition-all
                                                        peer-checked:border-amber-500 peer-checked:bg-amber-50 dark:peer-checked:bg-amber-900/20
                                                        border-gray-200 dark:border-gray-600 hover:border-gray-300">
                                                        <div class="font-semibold text-gray-900 dark:text-white">
                                                            {{ __('booking::checkout.payment_options.deposit') }} ({{ $depositPercent }}%)
                                                        </div>
                                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                                            {{ number_format($depositAmount / 100, 2) }} {{ current_currency() }}
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                            @if($paymentOption === 'deposit')
                                                <div class="mt-2 p-2 bg-amber-100 dark:bg-amber-900/30 rounded text-sm text-amber-700 dark:text-amber-300 flex items-center gap-2">
                                                    <x-heroicon-o-information-circle class="w-4 h-4 flex-shrink-0" />
                                                    {{ __('booking::checkout.messages.balance_remaining', [
                                                        'amount' => number_format(($priceMinor - $depositAmount) / 100, 2),
                                                        'currency' => current_currency()
                                                    ]) }}
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif
            </div>

            {{-- Right Column: Summary & Checkout --}}
            <div class="space-y-6">
                {{-- Invoice Summary --}}
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-receipt-percent class="w-5 h-5 text-green-500" />
                            {{ __('booking::checkout.sections.summary') }}
                        </div>
                    </x-slot>

                    @php
                        $totalDiscounts = $lineDiscountsMinor + $discountMinor;
                        $beforeDiscountAmount = $subtotalMinor + $lineDiscountsMinor;
                    @endphp

                    <div class="space-y-4">
                        {{-- Before Discount (only show if there are any discounts) --}}
                        @if($totalDiscounts > 0)
                            <div class="flex justify-between text-gray-500 dark:text-gray-400">
                                <span>{{ __('booking::checkout.summary.before_discount') }}</span>
                                <span class="line-through">
                                    {{ number_format($beforeDiscountAmount / 100, 2) }} {{ current_currency() }}
                                </span>
                            </div>
                        @endif

                        {{-- Session/Line Discounts (discounts already applied to appointments) --}}
                        @if($lineDiscountsMinor > 0)
                            <div class="flex justify-between text-red-600 dark:text-red-400">
                                <span class="flex items-center gap-2">
                                    <x-heroicon-o-tag class="w-4 h-4" />
                                    {{ __('booking::checkout.summary.session_discount') }}
                                </span>
                                <span>-{{ number_format($lineDiscountsMinor / 100, 2) }} {{ current_currency() }}</span>
                            </div>
                        @endif

                        {{-- Subtotal (after line discounts) --}}
                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>{{ __('booking::checkout.summary.subtotal') }}</span>
                            <span class="font-medium text-gray-900 dark:text-white">
                                {{ number_format($subtotalMinor / 100, 2) }} {{ current_currency() }}
                            </span>
                        </div>

                        {{-- Package Deduction (sessions covered by existing packages) --}}
                        @if($this->getPackageSessionsCount() > 0)
                            <div class="flex justify-between text-blue-600 dark:text-blue-400">
                                <span>
                                    {{ __('booking::checkout.summary.package_sessions', ['count' => $this->getPackageSessionsCount()]) }}
                                </span>
                                <span>
                                    -{{ number_format($this->getPackageDeductionTotal() / 100, 2) }} {{ current_currency() }}
                                </span>
                            </div>
                        @endif

                        {{-- Package Purchases --}}
                        @if($packagesSubtotalMinor > 0)
                            <div class="flex justify-between text-indigo-600 dark:text-indigo-400">
                                <span>{{ __('booking::checkout.summary.packages') }}</span>
                                <span>{{ number_format($packagesSubtotalMinor / 100, 2) }} {{ current_currency() }}</span>
                            </div>
                            @if($packagesPayableMinor < $packagesSubtotalMinor)
                                <div class="flex justify-between text-sm text-amber-600 dark:text-amber-400">
                                    <span class="flex items-center gap-1">
                                        <x-heroicon-o-arrow-right class="w-3 h-3" />
                                        {{ __('booking::checkout.summary.paying_now') }}
                                    </span>
                                    <span>{{ number_format($packagesPayableMinor / 100, 2) }} {{ current_currency() }}</span>
                                </div>
                                <div class="flex justify-between text-sm text-gray-500 dark:text-gray-400">
                                    <span class="flex items-center gap-1">
                                        <x-heroicon-o-clock class="w-3 h-3" />
                                        {{ __('booking::checkout.summary.balance_later') }}
                                    </span>
                                    <span>{{ number_format(($packagesSubtotalMinor - $packagesPayableMinor) / 100, 2) }} {{ current_currency() }}</span>
                                </div>
                            @endif
                        @endif

                        {{-- Overall Checkout Discount Section --}}
                        @if($discountMinor > 0)
                            {{-- Overall discount applied --}}
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                <div class="flex justify-between items-center text-red-600 dark:text-red-400">
                                    <span class="flex items-center gap-2">
                                        <x-heroicon-o-receipt-percent class="w-4 h-4" />
                                        {{ __('booking::checkout.summary.checkout_discount') }}
                                        @if($overallDiscountReason)
                                            <span class="text-xs text-gray-500 dark:text-gray-400">({{ $overallDiscountReason }})</span>
                                        @endif
                                    </span>
                                    <span class="flex items-center gap-2">
                                        <span>-{{ number_format($discountMinor / 100, 2) }} {{ current_currency() }}</span>
                                        <button
                                            wire:click="removeDiscount"
                                            class="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"
                                            title="{{ __('booking::checkout.actions.remove_discount') }}"
                                        >
                                            <x-heroicon-o-x-mark class="w-4 h-4" />
                                        </button>
                                    </span>
                                </div>
                            </div>
                        @elseif($lineDiscountsMinor == 0)
                            {{-- No discounts at all - show Add Discount option --}}
                            @if($overallDiscountType === 'none')
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                    <button
                                        wire:click="$set('overallDiscountType', 'percent')"
                                        class="flex items-center gap-2 text-sm text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300"
                                    >
                                        <x-heroicon-o-plus-circle class="w-4 h-4" />
                                        {{ __('booking::checkout.actions.add_discount') }}
                                    </button>
                                </div>
                            @else
                                {{-- Discount form --}}
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                    <div class="space-y-2">
                                        <div class="flex gap-2">
                                            <select
                                                wire:model.live="overallDiscountType"
                                                class="w-24 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg"
                                            >
                                                <option value="percent">%</option>
                                                <option value="fixed">{{ current_currency() }}</option>
                                            </select>
                                            <input
                                                type="number"
                                                wire:model.live="overallDiscountValue"
                                                min="0"
                                                step="{{ $overallDiscountType === 'percent' ? '1' : '0.01' }}"
                                                max="{{ $overallDiscountType === 'percent' ? '100' : '' }}"
                                                class="flex-1 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg"
                                                placeholder="{{ $overallDiscountType === 'percent' ? '10' : '50.00' }}"
                                            />
                                            <button
                                                wire:click="removeDiscount"
                                                class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg"
                                            >
                                                <x-heroicon-o-x-mark class="w-5 h-5" />
                                            </button>
                                        </div>

                                        <input
                                            type="text"
                                            wire:model.blur="overallDiscountReason"
                                            class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg"
                                            placeholder="{{ __('booking::checkout.discount_reason_placeholder') }}"
                                        />
                                    </div>
                                </div>
                            @endif
                        @endif

                        {{-- Total --}}
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                            <div class="flex justify-between text-xl font-bold">
                                <span class="text-gray-900 dark:text-white">{{ __('booking::checkout.summary.total') }}</span>
                                <span class="text-green-600">{{ number_format($totalMinor / 100, 2) }} {{ current_currency() }}</span>
                            </div>
                        </div>

                        {{-- Checkout Button --}}
                        <div class="pt-4">
                            {{ $this->confirmCheckoutAction }}
                        </div>

                        {{-- Empty Visit Warning --}}
                        @if($this->getCompletedAppointments()->isEmpty() && $this->getOpenAppointments()->isEmpty() && $this->getSoldProducts()->isEmpty() && $this->getPendingPackages()->isEmpty())
                            <div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg text-amber-700 dark:text-amber-300 text-sm">
                                <div class="flex items-center gap-2">
                                    <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                                    {{ __('booking::checkout.messages.empty_visit') }}
                                </div>
                            </div>
                        @endif
                    </div>
                </x-filament::section>

            </div>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
