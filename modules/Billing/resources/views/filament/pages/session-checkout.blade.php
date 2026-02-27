<x-filament-panels::page>
    @if($invoice)
    <div class="space-y-6">
        {{-- Patient Info Header --}}
        <div class="p-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex items-center justify-center w-12 h-12 text-lg font-bold text-white rounded-full bg-primary-600">
                        {{ strtoupper(substr($invoice->patient?->first_name ?? 'P', 0, 1)) }}
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $invoice->patient?->full_name }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('billing::checkout.invoice_code') }}: {{ $invoice->code }}
                        </p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('billing::checkout.appointment') }}
                    </p>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ $invoice->appointment?->service?->translatable_name ?? '-' }}
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Left Column: Services & Products --}}
            <div class="space-y-6 lg:col-span-2">
                {{-- Services Section (Always Selected) --}}
                <div class="p-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h4 class="flex items-center gap-2 mb-4 text-base font-semibold text-gray-900 dark:text-white">
                        <x-heroicon-o-clipboard-document-check class="w-5 h-5 text-primary-500" />
                        {{ __('billing::checkout.services_completed') }}
                        <span class="text-xs font-normal text-gray-500">({{ __('billing::checkout.required') }})</span>
                    </h4>

                    @if(count($serviceLines) > 0)
                        <div class="space-y-2">
                            @foreach($serviceLines as $line)
                                <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                                    <div class="flex items-center gap-3">
                                        <x-heroicon-s-check-circle class="w-5 h-5 text-success-500" />
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">
                                                {{ $line['description'] }}
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                {{ $line['quantity'] }} x {{ number_format($line['unit_price'], 2) }}
                                                @if($line['discount'] > 0)
                                                    <span class="text-success-600">(-{{ number_format($line['discount'], 2) }})</span>
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                    <span class="font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($line['total'], 2) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex justify-between pt-3 mt-3 border-t border-gray-200 dark:border-gray-700">
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('billing::checkout.services_total') }}</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ number_format($this->getServicesTotal(), 2) }}</span>
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('billing::checkout.no_services') }}</p>
                    @endif
                </div>

                {{-- Products Section (Optional) --}}
                <div class="p-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h4 class="flex items-center gap-2 mb-4 text-base font-semibold text-gray-900 dark:text-white">
                        <x-heroicon-o-shopping-bag class="w-5 h-5 text-warning-500" />
                        {{ __('billing::checkout.products_sold') }}
                        <span class="text-xs font-normal text-gray-500">({{ __('billing::checkout.optional') }})</span>
                    </h4>

                    @if(count($productLines) > 0)
                        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('billing::checkout.products_note') }}
                        </p>
                        <div class="space-y-2">
                            @foreach($productLines as $line)
                                <div class="flex items-center justify-between p-3 rounded-lg transition-colors {{ ($selectedProducts[$line['id']] ?? false) ? 'bg-gray-50 dark:bg-gray-800' : 'bg-gray-100 dark:bg-gray-700/50 opacity-60' }}">
                                    <div class="flex items-center gap-3">
                                        <button
                                            wire:click="toggleProduct({{ $line['id'] }})"
                                            class="flex items-center justify-center w-5 h-5 rounded border transition-colors {{ ($selectedProducts[$line['id']] ?? false) ? 'bg-primary-500 border-primary-500 text-white' : 'border-gray-300 dark:border-gray-600' }}"
                                        >
                                            @if($selectedProducts[$line['id']] ?? false)
                                                <x-heroicon-s-check class="w-3 h-3" />
                                            @endif
                                        </button>
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">
                                                {{ $line['description'] }}
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                {{ $line['quantity'] }} x {{ number_format($line['unit_price'], 2) }}
                                            </p>
                                        </div>
                                    </div>
                                    <span class="font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($line['total'], 2) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex justify-between pt-3 mt-3 border-t border-gray-200 dark:border-gray-700">
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('billing::checkout.products_total') }}</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ number_format($this->getSelectedProductsTotal(), 2) }}</span>
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('billing::checkout.no_products') }}</p>
                    @endif
                </div>
            </div>

            {{-- Right Column: Payment --}}
            <div class="space-y-6">
                {{-- Quick Actions --}}
                <div class="p-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h4 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">
                        {{ __('billing::checkout.quick_actions') }}
                    </h4>
                    <div class="flex gap-2">
                        <x-filament::button
                            wire:click="payServicesOnly"
                            color="gray"
                            size="sm"
                            class="flex-1"
                        >
                            {{ __('billing::checkout.pay_services_only') }}
                        </x-filament::button>
                        <x-filament::button
                            wire:click="payAll"
                            color="primary"
                            size="sm"
                            class="flex-1"
                        >
                            {{ __('billing::checkout.pay_all') }}
                        </x-filament::button>
                    </div>
                </div>

                {{-- Payment Methods --}}
                <div class="p-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h4 class="flex items-center gap-2 mb-4 text-base font-semibold text-gray-900 dark:text-white">
                        <x-heroicon-o-credit-card class="w-5 h-5 text-success-500" />
                        {{ __('billing::checkout.payment_methods') }}
                    </h4>

                    @php $journals = $this->getAvailableJournals(); @endphp

                    <div class="space-y-3">
                        @foreach($payments as $index => $payment)
                            <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                        {{ __('billing::checkout.payment') }} #{{ $index + 1 }}
                                    </span>
                                    @if(count($payments) > 1)
                                        <button
                                            wire:click="removePaymentMethod({{ $index }})"
                                            class="text-danger-500 hover:text-danger-700"
                                        >
                                            <x-heroicon-o-x-mark class="w-4 h-4" />
                                        </button>
                                    @endif
                                </div>

                                <div class="space-y-2">
                                    <select
                                        wire:model.live="payments.{{ $index }}.journal_id"
                                        class="w-full text-sm border-gray-300 rounded-lg shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                                    >
                                        <option value="">{{ __('billing::checkout.select_method') }}</option>
                                        @foreach($journals as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>

                                    <div class="flex gap-2">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            wire:model.live.debounce.500ms="payments.{{ $index }}.amount"
                                            placeholder="{{ __('billing::checkout.amount') }}"
                                            class="flex-1 text-sm border-gray-300 rounded-lg shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                                        />
                                        <input
                                            type="text"
                                            wire:model="payments.{{ $index }}.reference"
                                            placeholder="{{ __('billing::checkout.reference') }}"
                                            class="flex-1 text-sm border-gray-300 rounded-lg shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                                        />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <button
                        wire:click="addPaymentMethod"
                        class="flex items-center justify-center w-full gap-2 px-4 py-2 mt-3 text-sm font-medium text-gray-700 transition-colors border border-gray-300 border-dashed rounded-lg dark:text-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800"
                    >
                        <x-heroicon-o-plus class="w-4 h-4" />
                        {{ __('billing::checkout.add_payment_method') }}
                    </button>
                </div>

                {{-- Summary --}}
                <div class="p-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h4 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">
                        {{ __('billing::checkout.summary') }}
                    </h4>

                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>{{ __('billing::checkout.selected_total') }}</span>
                            <span class="font-medium">{{ number_format($selectedTotal / 100, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>{{ __('billing::checkout.payments_total') }}</span>
                            <span class="font-medium">{{ number_format($paymentsTotal / 100, 2) }}</span>
                        </div>
                        <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex justify-between">
                                <span class="font-medium text-gray-900 dark:text-white">{{ __('billing::checkout.balance') }}</span>
                                @php $balance = $paymentsTotal - $selectedTotal; @endphp
                                <span class="font-bold {{ $balance >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                                    {{ number_format(abs($balance) / 100, 2) }}
                                    @if($balance >= 0)
                                        <x-heroicon-s-check-circle class="inline w-4 h-4 ml-1" />
                                    @else
                                        <x-heroicon-s-exclamation-circle class="inline w-4 h-4 ml-1" />
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    <x-filament::button
                        wire:click="completeCheckout"
                        color="success"
                        class="w-full mt-4"
                        :disabled="!$this->isBalanced()"
                    >
                        <x-heroicon-o-check class="w-5 h-5 mr-2" />
                        {{ __('billing::checkout.complete_checkout') }}
                    </x-filament::button>

                    @if(!$this->isBalanced())
                        <p class="mt-2 text-xs text-center text-danger-600">
                            {{ __('billing::checkout.insufficient_payment_warning') }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @else
        <div class="flex items-center justify-center h-64">
            <p class="text-gray-500 dark:text-gray-400">{{ __('billing::checkout.invoice_not_found') }}</p>
        </div>
    @endif
</x-filament-panels::page>
