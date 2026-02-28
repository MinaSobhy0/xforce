<x-filament-panels::page
    @class([
        'fi-resource-view-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    @if($this->isEditing)
        {{-- EDIT MODE --}}

        {{-- Invoice Header Form --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('billing::billing.sections.invoice_details') }}
            </x-slot>
            {{ $this->invoiceForm }}
        </x-filament::section>

        {{-- Line Items Table (Editable) --}}
        <x-filament::section class="mt-6">
            <x-slot name="heading">
                {{ __('billing::billing.sections.line_items') }}
            </x-slot>

            <style>
                .editable-table input,
                .editable-table select {
                    border: none !important;
                    background: transparent !important;
                    box-shadow: none !important;
                    padding: 0.25rem 0.5rem !important;
                }
                .editable-table input:focus,
                .editable-table select:focus {
                    background: rgb(249 250 251) !important;
                    border: 1px solid rgb(209 213 219) !important;
                    border-radius: 0.375rem !important;
                }
                .dark .editable-table input:focus,
                .dark .editable-table select:focus {
                    background: rgb(55 65 81) !important;
                    border: 1px solid rgb(75 85 99) !important;
                }
                .editable-table select[multiple] {
                    min-height: 1.75rem;
                }
            </style>

            <div class="overflow-x-auto editable-table">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="w-8 p-2"></th>
                            <th class="text-start p-2 font-medium text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.line_type') }}</th>
                            <th class="text-start p-2 font-medium text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.description') }}</th>
                            <th class="text-start p-2 font-medium text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.account') }}</th>
                            <th class="text-end p-2 font-medium text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.quantity') }}</th>
                            <th class="text-end p-2 font-medium text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.unit_price') }}</th>
                            <th class="text-end p-2 font-medium text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.discount') }}</th>
                            <th class="text-end p-2 font-medium text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.taxes') }}</th>
                            <th class="w-10 p-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->linesData['lines'] ?? [] as $index => $line)
                            <tr class="border-b dark:border-gray-700 group" wire:key="line-{{ $index }}">
                                <td class="p-1 text-gray-400 cursor-move opacity-0 group-hover:opacity-100 transition-opacity">
                                    <x-heroicon-o-bars-3 class="w-4 h-4" />
                                </td>
                                <td class="p-1">
                                    <select wire:model.live="linesData.lines.{{ $index }}.line_type" class="text-sm text-gray-900 dark:text-white">
                                        <option value="service">{{ __('billing::billing.line_types.service') }}</option>
                                        <option value="product">{{ __('billing::billing.line_types.product') }}</option>
                                        <option value="package">{{ __('billing::billing.line_types.package') }}</option>
                                        <option value="other">{{ __('billing::billing.line_types.other') }}</option>
                                    </select>
                                </td>
                                <td class="p-1">
                                    <input
                                        type="text"
                                        wire:model.blur="linesData.lines.{{ $index }}.description"
                                        placeholder="{{ __('billing::billing.fields.description') }}"
                                        class="w-full text-sm text-gray-900 dark:text-white"
                                    />
                                </td>
                                <td class="p-1">
                                    <select wire:model.blur="linesData.lines.{{ $index }}.account_id" class="w-full text-sm text-gray-900 dark:text-white">
                                        @foreach(\Modules\Accounting\Models\ChartOfAccount::where('type', 'income')->where('is_active', true)->orderBy('code')->get() as $account)
                                            <option value="{{ $account->id }}">[{{ $account->code }}] {{ $account->getTranslation('name', app()->getLocale()) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="p-1">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        wire:model.blur="linesData.lines.{{ $index }}.quantity"
                                        class="w-16 text-sm text-end text-gray-900 dark:text-white"
                                    />
                                </td>
                                <td class="p-1">
                                    <input
                                        type="number"
                                        step="0.01"
                                        wire:model.blur="linesData.lines.{{ $index }}.unit_price"
                                        class="w-20 text-sm text-end text-gray-900 dark:text-white"
                                    />
                                </td>
                                <td class="p-1">
                                    <div class="flex items-center gap-1">
                                        <input
                                            type="number"
                                            step="0.01"
                                            wire:model.blur="linesData.lines.{{ $index }}.discount"
                                            class="w-14 text-sm text-end text-gray-900 dark:text-white"
                                        />
                                        <select wire:model.live="linesData.lines.{{ $index }}.discount_type" class="w-14 text-sm text-gray-900 dark:text-white">
                                            <option value="fixed">{{ current_currency() }}</option>
                                            <option value="percent">%</option>
                                        </select>
                                    </div>
                                </td>
                                <td class="p-1">
                                    <select wire:model.blur="linesData.lines.{{ $index }}.tax_rates" multiple class="w-20 text-sm text-gray-900 dark:text-white">
                                        @foreach(\Modules\Billing\Models\TaxRate::where('is_active', true)->where('type', 'sales')->orderByDesc('rate')->get() as $tax)
                                            <option value="{{ $tax->rate }}">{{ $tax->rate }}%</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="p-1 text-center opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button type="button" wire:click="removeLine({{ $index }})" class="text-danger-600 hover:text-danger-500">
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="p-4 text-center text-gray-500 dark:text-gray-400">
                                    {{ __('billing::billing.placeholders.no_lines') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex justify-center">
                <x-filament::button wire:click="addLine" color="gray" size="sm" icon="heroicon-o-plus">
                    {{ __('billing::billing.actions.add_line_item') }}
                </x-filament::button>
            </div>
        </x-filament::section>

    @else
        {{-- VIEW MODE --}}

        {{-- Header Section --}}
        {{ $this->infolist }}

        {{-- Line Items Table --}}
        <x-filament::section class="mt-6">
            <x-slot name="heading">
                {{ __('billing::billing.sections.line_items') }}
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="text-start p-2 font-medium text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.line_type') }}</th>
                            <th class="text-start p-2 font-medium text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.description') }}</th>
                            <th class="text-start p-2 font-medium text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.account') }}</th>
                            <th class="text-end p-2 font-medium text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.quantity') }}</th>
                            <th class="text-end p-2 font-medium text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.unit_price') }}</th>
                            <th class="text-end p-2 font-medium text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.discount') }}</th>
                            <th class="text-end p-2 font-medium text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.taxes') }}</th>
                            <th class="text-end p-2 font-medium text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($record->lines()->with('account')->orderBy('sort_order')->get() as $line)
                            <tr class="border-b dark:border-gray-700">
                                <td class="p-2">
                                    <x-filament::badge :color="match($line->line_type) {
                                        'service' => 'primary',
                                        'product' => 'success',
                                        'package' => 'warning',
                                        default => 'gray',
                                    }">
                                        {{ __('billing::billing.line_types.' . ($line->line_type ?? 'other')) }}
                                    </x-filament::badge>
                                </td>
                                <td class="p-2 text-gray-900 dark:text-white">{{ $line->description }}</td>
                                <td class="p-2 text-gray-500 dark:text-gray-400 text-sm">
                                    @if($line->account)
                                        [{{ $line->account->code }}] {{ $line->account->name }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="p-2 text-end text-gray-700 dark:text-gray-300">{{ number_format($line->quantity, 2) }}</td>
                                <td class="p-2 text-end text-gray-700 dark:text-gray-300">{{ format_money($line->unit_price_minor) }}</td>
                                <td class="p-2 text-end text-gray-700 dark:text-gray-300">
                                    @if($line->discount_minor > 0)
                                        {{ $line->discount_type === 'percent' ? $line->discount_minor . '%' : format_money($line->discount_minor) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="p-2 text-end text-gray-700 dark:text-gray-300">
                                    @php
                                        $rates = $line->tax_rates ?? [];
                                        $vatRates = array_filter($rates, fn($r) => floatval($r) >= 0);
                                        $whRates = array_filter($rates, fn($r) => floatval($r) < 0);
                                        $parts = [];
                                        if (!empty($vatRates)) {
                                            $parts[] = 'VAT: ' . implode(', ', array_map(fn($r) => number_format((float)$r, 2) . '%', $vatRates));
                                        }
                                        if (!empty($whRates)) {
                                            $parts[] = 'WH: ' . implode(', ', array_map(fn($r) => number_format((float)$r, 2) . '%', $whRates));
                                        }
                                    @endphp
                                    {{ empty($parts) ? '-' : implode(' | ', $parts) }}
                                </td>
                                <td class="p-2 text-end font-bold text-gray-900 dark:text-white">{{ format_money($line->total_minor) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-4 text-center text-gray-500 dark:text-gray-400">
                                    {{ __('billing::billing.placeholders.no_lines') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Financial Summary --}}
        <x-filament::section class="mt-6" compact>
            <x-slot name="heading">
                {{ __('billing::billing.sections.summary') }}
            </x-slot>

            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.subtotal') }}</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ format_money($record->subtotal_minor) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.discount') }}</p>
                    <p class="text-sm text-danger-600 dark:text-danger-400">{{ $record->discount_minor > 0 ? '-' . format_money($record->discount_minor) : '-' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.tax') }}</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $record->tax_minor != 0 ? format_money($record->tax_minor) : '-' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.total') }}</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ format_money($record->total_minor) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.paid') }}</p>
                    <p class="text-sm font-semibold text-success-600 dark:text-success-400">{{ format_money($record->paid_minor) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.remaining') }}</p>
                    <p class="text-sm font-semibold {{ $record->remaining_minor > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">
                        {{ format_money($record->remaining_minor) }}
                    </p>
                </div>
            </div>
        </x-filament::section>

        {{-- Notes Section --}}
        @if($record->notes || $record->internal_notes)
        <x-filament::section class="mt-6" compact collapsible collapsed>
            <x-slot name="heading">
                {{ __('billing::billing.sections.notes') }}
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.customer_notes') }}</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $record->notes ?: '-' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('billing::billing.fields.internal_notes') }}</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $record->internal_notes ?: '-' }}</p>
                </div>
            </div>
        </x-filament::section>
        @endif

        {{-- Payments --}}
        <div class="mt-6">
            @livewire(\Modules\Billing\Filament\Resources\InvoiceResource\RelationManagers\PaymentsRelationManager::class, [
                'ownerRecord' => $record,
                'pageClass' => static::class,
            ], key('payments-relation-manager'))
        </div>
    @endif
</x-filament-panels::page>
