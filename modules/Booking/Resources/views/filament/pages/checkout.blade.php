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
                                            @if($appointment->discount_minor > 0)
                                                <div class="text-xs text-red-500">
                                                    -{{ number_format($appointment->discount_minor / 100, 2) }} {{ __('booking::checkout.discount') }}
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

                    <div class="space-y-4">
                        {{-- Subtotal --}}
                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>{{ __('booking::checkout.summary.subtotal') }}</span>
                            <span class="font-medium text-gray-900 dark:text-white">
                                {{ number_format($subtotalMinor / 100, 2) }} {{ current_currency() }}
                            </span>
                        </div>

                        {{-- Package Deduction --}}
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

                        {{-- Discount Section --}}
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                {{ __('booking::checkout.summary.overall_discount') }}
                            </div>

                            @if($overallDiscountType === 'none')
                                <div class="flex gap-2">
                                    <select
                                        wire:model.live="overallDiscountType"
                                        class="flex-1 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg"
                                    >
                                        <option value="none">{{ __('booking::checkout.discount_types.none') }}</option>
                                        <option value="percent">{{ __('booking::checkout.discount_types.percent') }}</option>
                                        <option value="fixed">{{ __('booking::checkout.discount_types.fixed') }}</option>
                                    </select>
                                </div>
                            @else
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

                                    @if($discountMinor > 0)
                                        <div class="flex justify-between text-red-600 dark:text-red-400">
                                            <span>{{ __('booking::checkout.summary.discount') }}</span>
                                            <span>-{{ number_format($discountMinor / 100, 2) }} {{ current_currency() }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

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
                        @if($this->getCompletedAppointments()->isEmpty() && $this->getOpenAppointments()->isEmpty() && $this->getSoldProducts()->isEmpty())
                            <div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg text-amber-700 dark:text-amber-300 text-sm">
                                <div class="flex items-center gap-2">
                                    <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                                    {{ __('booking::checkout.messages.empty_visit') }}
                                </div>
                            </div>
                        @endif
                    </div>
                </x-filament::section>

                {{-- Quick Stats --}}
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl text-center">
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            {{ $visit?->appointments?->count() ?? 0 }}
                        </div>
                        <div class="text-xs text-blue-600 dark:text-blue-400">
                            {{ __('booking::checkout.stats.total_services') }}
                        </div>
                    </div>
                    <div class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-xl text-center">
                        <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                            {{ $this->getSoldProducts()->count() }}
                        </div>
                        <div class="text-xs text-purple-600 dark:text-purple-400">
                            {{ __('booking::checkout.stats.products_sold') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
