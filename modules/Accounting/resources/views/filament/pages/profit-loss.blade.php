<x-filament-panels::page>
    <x-filament-panels::form wire:submit="loadProfitLoss">
        {{ $this->form }}
    </x-filament-panels::form>

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Revenues --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('accounting::accounting.revenues') }}
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
                        @forelse($revenues as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="px-4 py-3 font-mono">{{ $row['code'] }}</td>
                                <td class="px-4 py-3">{{ $row['name'] }}</td>
                                <td class="px-4 py-3 text-right font-mono text-success-600 dark:text-success-400">
                                    {{ $this->formatCurrency($row['amount']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-gray-500">
                                    {{ __('accounting::accounting.no_revenues') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 font-bold">
                            <td colspan="2" class="px-4 py-3">{{ __('accounting::accounting.total_revenue') }}</td>
                            <td class="px-4 py-3 text-right font-mono text-success-600 dark:text-success-400">
                                {{ $this->formatCurrency($totalRevenue) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>

        {{-- Expenses --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('accounting::accounting.expenses') }}
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
                        @forelse($expenses as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="px-4 py-3 font-mono">{{ $row['code'] }}</td>
                                <td class="px-4 py-3">{{ $row['name'] }}</td>
                                <td class="px-4 py-3 text-right font-mono text-danger-600 dark:text-danger-400">
                                    {{ $this->formatCurrency($row['amount']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-gray-500">
                                    {{ __('accounting::accounting.no_expenses') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 font-bold">
                            <td colspan="2" class="px-4 py-3">{{ __('accounting::accounting.total_expenses') }}</td>
                            <td class="px-4 py-3 text-right font-mono text-danger-600 dark:text-danger-400">
                                {{ $this->formatCurrency($totalExpenses) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>
    </div>

    {{-- Net Income Summary --}}
    <div class="mt-6">
        <x-filament::section>
            <x-slot name="heading">
                {{ __('accounting::accounting.summary') }}
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 rounded-lg bg-success-50 dark:bg-success-900/20">
                    <p class="text-sm text-success-600 dark:text-success-400">{{ __('accounting::accounting.total_revenue') }}</p>
                    <p class="text-2xl font-bold text-success-700 dark:text-success-300">{{ $this->formatCurrency($totalRevenue) }}</p>
                </div>
                <div class="p-4 rounded-lg bg-danger-50 dark:bg-danger-900/20">
                    <p class="text-sm text-danger-600 dark:text-danger-400">{{ __('accounting::accounting.total_expenses') }}</p>
                    <p class="text-2xl font-bold text-danger-700 dark:text-danger-300">{{ $this->formatCurrency($totalExpenses) }}</p>
                </div>
                <div class="p-4 rounded-lg {{ $netIncome >= 0 ? 'bg-success-50 dark:bg-success-900/20' : 'bg-danger-50 dark:bg-danger-900/20' }}">
                    <p class="text-sm {{ $netIncome >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                        {{ __('accounting::accounting.net_income') }}
                    </p>
                    <p class="text-2xl font-bold {{ $netIncome >= 0 ? 'text-success-700 dark:text-success-300' : 'text-danger-700 dark:text-danger-300' }}">
                        {{ $this->formatCurrency($netIncome) }}
                    </p>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
