<x-filament-panels::page>
    <x-filament-panels::form wire:submit="loadBalanceSheet">
        {{ $this->form }}
    </x-filament-panels::form>

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Assets --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('accounting::accounting.assets') }}
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.account_code') }}</th>
                            <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.account_name') }}</th>
                            <th class="px-4 py-3 text-right font-semibold">{{ __('accounting::accounting.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assets as $group)
                            {{-- Type Header --}}
                            <tr class="bg-gray-100 dark:bg-gray-800">
                                <td colspan="2" class="px-4 py-2 font-semibold text-gray-700 dark:text-gray-300">
                                    {{ $group['type_label'] }}
                                </td>
                                <td class="px-4 py-2 text-right font-semibold text-gray-700 dark:text-gray-300 font-mono">
                                    {{ $this->formatCurrency($group['subtotal']) }}
                                </td>
                            </tr>
                            {{-- Accounts in this type --}}
                            @foreach($group['accounts'] as $account)
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-primary-50 dark:hover:bg-primary-900/20 cursor-pointer"
                                    wire:click="openGeneralLedger('{{ $account['id'] }}')"
                                    title="{{ __('accounting::accounting.view_ledger') }}">
                                    <td class="px-4 py-2 font-mono pl-8">{{ $account['code'] }}</td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex items-center gap-1">
                                            {{ $account['name'] }}
                                            <x-heroicon-m-arrow-top-right-on-square class="w-3 h-3 text-gray-400" />
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono">{{ $this->formatCurrency($account['amount']) }}</td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-gray-500">
                                    {{ __('accounting::accounting.no_assets') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 font-bold">
                            <td colspan="2" class="px-4 py-3">{{ __('accounting::accounting.total_assets') }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $this->formatCurrency($totalAssets) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>

        <div class="space-y-6">
            {{-- Liabilities --}}
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('accounting::accounting.liabilities') }}
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.account_code') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.account_name') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ __('accounting::accounting.amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($liabilities as $group)
                                {{-- Type Header --}}
                                <tr class="bg-gray-100 dark:bg-gray-800">
                                    <td colspan="2" class="px-4 py-2 font-semibold text-gray-700 dark:text-gray-300">
                                        {{ $group['type_label'] }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-semibold text-gray-700 dark:text-gray-300 font-mono">
                                        {{ $this->formatCurrency($group['subtotal']) }}
                                    </td>
                                </tr>
                                {{-- Accounts in this type --}}
                                @foreach($group['accounts'] as $account)
                                    <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-primary-50 dark:hover:bg-primary-900/20 cursor-pointer"
                                        wire:click="openGeneralLedger('{{ $account['id'] }}')"
                                        title="{{ __('accounting::accounting.view_ledger') }}">
                                        <td class="px-4 py-2 font-mono pl-8">{{ $account['code'] }}</td>
                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center gap-1">
                                                {{ $account['name'] }}
                                                <x-heroicon-m-arrow-top-right-on-square class="w-3 h-3 text-gray-400" />
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono">{{ $this->formatCurrency($account['amount']) }}</td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-gray-500">
                                        {{ __('accounting::accounting.no_liabilities') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 font-bold">
                                <td colspan="2" class="px-4 py-3">{{ __('accounting::accounting.total_liabilities') }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ $this->formatCurrency($totalLiabilities) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-filament::section>

            {{-- Equity --}}
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('accounting::accounting.equity') }}
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.account_code') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.account_name') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ __('accounting::accounting.amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($equity as $group)
                                {{-- Type Header --}}
                                <tr class="bg-gray-100 dark:bg-gray-800">
                                    <td colspan="2" class="px-4 py-2 font-semibold text-gray-700 dark:text-gray-300">
                                        {{ $group['type_label'] }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-semibold text-gray-700 dark:text-gray-300 font-mono">
                                        {{ $this->formatCurrency($group['subtotal']) }}
                                    </td>
                                </tr>
                                {{-- Accounts in this type --}}
                                @foreach($group['accounts'] as $account)
                                    <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-primary-50 dark:hover:bg-primary-900/20 cursor-pointer"
                                        wire:click="openGeneralLedger('{{ $account['id'] }}')"
                                        title="{{ __('accounting::accounting.view_ledger') }}">
                                        <td class="px-4 py-2 font-mono pl-8">{{ $account['code'] }}</td>
                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center gap-1">
                                                {{ $account['name'] }}
                                                <x-heroicon-m-arrow-top-right-on-square class="w-3 h-3 text-gray-400" />
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono">{{ $this->formatCurrency($account['amount']) }}</td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-gray-500">
                                        {{ __('accounting::accounting.no_equity') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 font-bold">
                                <td colspan="2" class="px-4 py-3">{{ __('accounting::accounting.total_equity') }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ $this->formatCurrency($totalEquity) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-filament::section>
        </div>
    </div>

    {{-- Balance Check --}}
    <div class="mt-6">
        <x-filament::section>
            <x-slot name="heading">
                {{ __('accounting::accounting.balance_check') }}
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 rounded-lg bg-primary-50 dark:bg-primary-900/20">
                    <p class="text-sm text-primary-600 dark:text-primary-400">{{ __('accounting::accounting.total_assets') }}</p>
                    <p class="text-2xl font-bold text-primary-700 dark:text-primary-300">{{ $this->formatCurrency($totalAssets) }}</p>
                </div>
                <div class="p-4 rounded-lg bg-warning-50 dark:bg-warning-900/20">
                    <p class="text-sm text-warning-600 dark:text-warning-400">{{ __('accounting::accounting.total_liabilities') }} + {{ __('accounting::accounting.equity') }}</p>
                    <p class="text-2xl font-bold text-warning-700 dark:text-warning-300">{{ $this->formatCurrency($totalLiabilities + $totalEquity) }}</p>
                </div>
                <div class="p-4 rounded-lg {{ $totalAssets === ($totalLiabilities + $totalEquity) ? 'bg-success-50 dark:bg-success-900/20' : 'bg-danger-50 dark:bg-danger-900/20' }}">
                    <p class="text-sm {{ $totalAssets === ($totalLiabilities + $totalEquity) ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                        {{ __('accounting::accounting.status') }}
                    </p>
                    <p class="text-2xl font-bold {{ $totalAssets === ($totalLiabilities + $totalEquity) ? 'text-success-700 dark:text-success-300' : 'text-danger-700 dark:text-danger-300' }}">
                        @if($totalAssets === ($totalLiabilities + $totalEquity))
                            {{ __('accounting::accounting.balanced') }}
                        @else
                            {{ __('accounting::accounting.not_balanced') }}
                        @endif
                    </p>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
