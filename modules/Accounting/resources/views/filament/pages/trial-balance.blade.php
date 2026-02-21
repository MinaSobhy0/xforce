<x-filament-panels::page>
    <x-filament-panels::form wire:submit="loadTrialBalance">
        {{ $this->form }}
    </x-filament-panels::form>

    <div class="mt-6">
        <x-filament::section>
            <x-slot name="heading">
                {{ __('accounting::accounting.trial_balance') }} - {{ $as_of_date }}
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.account_code') }}</th>
                            <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.account_name') }}</th>
                            <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.type') }}</th>
                            <th class="px-4 py-3 text-right font-semibold">{{ __('accounting::accounting.debit') }}</th>
                            <th class="px-4 py-3 text-right font-semibold">{{ __('accounting::accounting.credit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($trialBalance as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="px-4 py-3 font-mono">{{ $row['code'] }}</td>
                                <td class="px-4 py-3">{{ $row['name'] }}</td>
                                <td class="px-4 py-3">
                                    <x-filament::badge>
                                        {{ ucfirst($row['type']) }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    {{ $row['debit'] > 0 ? $this->formatCurrency($row['debit']) : '-' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    {{ $row['credit'] > 0 ? $this->formatCurrency($row['credit']) : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                    {{ __('accounting::accounting.no_data') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 font-bold">
                            <td colspan="3" class="px-4 py-3">{{ __('accounting::accounting.totals') }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $this->formatCurrency($totalDebit) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $this->formatCurrency($totalCredit) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if($totalDebit !== $totalCredit)
                <div class="mt-4">
                    <x-filament::section class="bg-danger-50 dark:bg-danger-900/20">
                        <p class="text-danger-600 dark:text-danger-400 font-semibold">
                            {{ __('accounting::accounting.trial_balance_not_balanced') }}
                            ({{ __('accounting::accounting.difference') }}: {{ $this->formatCurrency(abs($totalDebit - $totalCredit)) }})
                        </p>
                    </x-filament::section>
                </div>
            @else
                <div class="mt-4">
                    <x-filament::section class="bg-success-50 dark:bg-success-900/20">
                        <p class="text-success-600 dark:text-success-400 font-semibold">
                            {{ __('accounting::accounting.trial_balance_balanced') }}
                        </p>
                    </x-filament::section>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
