<x-filament-panels::page>
    <x-filament-panels::form wire:submit="applyFilters">
        {{ $this->form }}
    </x-filament-panels::form>

    @if($selectedAccountName)
        {{-- Single Account Ledger View --}}
        <div class="mt-6">
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex justify-between items-center">
                        <span>{{ $selectedAccountName }}</span>
                        <x-filament::button
                            wire:click="clearAccountFilter"
                            size="sm"
                            color="gray"
                            icon="heroicon-o-arrow-left"
                        >
                            {{ __('accounting::accounting.back_to_all') }}
                        </x-filament::button>
                    </div>
                </x-slot>

                {{-- Opening Balance --}}
                <div class="mb-4 p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                    <div class="flex justify-between items-center">
                        <span class="font-semibold">{{ __('accounting::accounting.opening_balance') }}</span>
                        <span class="font-mono font-bold {{ $openingBalance >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                            {{ $this->formatCurrency($openingBalance) }}
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.date') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.entry_number') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.description') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ __('accounting::accounting.debit') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ __('accounting::accounting.credit') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ __('accounting::accounting.balance') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ledgerEntries as $entry)
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900">
                                    <td class="px-4 py-3 font-mono">{{ $entry['date'] }}</td>
                                    <td class="px-4 py-3">
                                        <x-filament::badge color="info">
                                            {{ $entry['entry_number'] }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-4 py-3">{{ $entry['description'] }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-success-600 dark:text-success-400">
                                        {{ $entry['debit'] > 0 ? $this->formatCurrency($entry['debit']) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-danger-600 dark:text-danger-400">
                                        {{ $entry['credit'] > 0 ? $this->formatCurrency($entry['credit']) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold {{ $entry['balance'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                        {{ $this->formatCurrency($entry['balance']) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                        {{ __('accounting::accounting.no_entries') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if(count($ledgerEntries) > 0)
                        <tfoot>
                            <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800">
                                <td colspan="3" class="px-4 py-3 font-semibold">{{ __('accounting::accounting.total') }}</td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-success-600 dark:text-success-400">
                                    {{ $this->formatCurrency($totalDebit) }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-danger-600 dark:text-danger-400">
                                    {{ $this->formatCurrency($totalCredit) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>

                {{-- Closing Balance --}}
                <div class="mt-4 p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                    <div class="flex justify-between items-center">
                        <span class="font-semibold">{{ __('accounting::accounting.closing_balance') }}</span>
                        <span class="font-mono font-bold text-xl {{ $closingBalance >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                            {{ $this->formatCurrency($closingBalance) }}
                        </span>
                    </div>
                </div>
            </x-filament::section>
        </div>
    @else
        {{-- All Accounts Summary View --}}
        <div class="mt-6">
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('accounting::accounting.account_balances') }}
                </x-slot>

                @if(count($accountBalances) > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.code') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.account') }}</th>
                                    <th class="px-4 py-3 text-center font-semibold">{{ __('accounting::accounting.type') }}</th>
                                    <th class="px-4 py-3 text-right font-semibold">{{ __('accounting::accounting.opening_balance') }}</th>
                                    <th class="px-4 py-3 text-right font-semibold">{{ __('accounting::accounting.debit') }}</th>
                                    <th class="px-4 py-3 text-right font-semibold">{{ __('accounting::accounting.credit') }}</th>
                                    <th class="px-4 py-3 text-right font-semibold">{{ __('accounting::accounting.closing_balance') }}</th>
                                    <th class="px-4 py-3 text-center font-semibold">{{ __('accounting::accounting.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($accountBalances as $account)
                                    <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900">
                                        <td class="px-4 py-3 font-mono font-semibold">{{ $account['code'] }}</td>
                                        <td class="px-4 py-3">{{ is_array($account['name']) ? ($account['name'][app()->getLocale()] ?? $account['name']['en'] ?? '') : $account['name'] }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <x-filament::badge :color="match($account['type']) {
                                                'asset' => 'info',
                                                'liability' => 'warning',
                                                'equity' => 'success',
                                                'revenue' => 'success',
                                                'expense' => 'danger',
                                                default => 'gray'
                                            }">
                                                {{ ucfirst($account['type']) }}
                                            </x-filament::badge>
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono {{ $account['opening_balance'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                            {{ $this->formatCurrency($account['opening_balance']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono text-success-600 dark:text-success-400">
                                            {{ $account['debit'] > 0 ? $this->formatCurrency($account['debit']) : '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono text-danger-600 dark:text-danger-400">
                                            {{ $account['credit'] > 0 ? $this->formatCurrency($account['credit']) : '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono font-semibold {{ $account['closing_balance'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                            {{ $this->formatCurrency($account['closing_balance']) }}
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <x-filament::button
                                                wire:click="viewAccount('{{ $account['id'] }}')"
                                                size="xs"
                                                color="gray"
                                                icon="heroicon-o-eye"
                                            >
                                                {{ __('accounting::accounting.view') }}
                                            </x-filament::button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800">
                                    <td colspan="4" class="px-4 py-3 font-semibold">{{ __('accounting::accounting.total') }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-success-600 dark:text-success-400">
                                        {{ $this->formatCurrency($totalDebit) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-danger-600 dark:text-danger-400">
                                        {{ $this->formatCurrency($totalCredit) }}
                                    </td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8 text-gray-500">
                        <x-heroicon-o-book-open class="mx-auto h-12 w-12 text-gray-400" />
                        <p class="mt-4">{{ __('accounting::accounting.no_account_activity') }}</p>
                    </div>
                @endif
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
