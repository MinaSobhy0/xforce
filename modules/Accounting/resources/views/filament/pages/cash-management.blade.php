<x-filament-panels::page>
    {{-- Account Selector and Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        {{-- Account Selector --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-sm">
            {{ $this->accountForm }}
        </div>

        {{-- Current Balance --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                {{ __('accounting::accounting.current_balance') }}
            </div>
            <div class="text-2xl font-bold font-mono {{ $currentBalance >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                {{ $this->formatCurrency($currentBalance) }}
            </div>
        </div>

        {{-- Today's Activity --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                {{ __('accounting::accounting.today_activity') }}
            </div>
            <div class="flex items-center gap-4">
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('accounting::accounting.cash_in_short') }}:</span>
                    <span class="font-mono font-semibold text-success-600 dark:text-success-400 ml-1">
                        {{ $this->formatCurrency($todayCashIn) }}
                    </span>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('accounting::accounting.cash_out_short') }}:</span>
                    <span class="font-mono font-semibold text-danger-600 dark:text-danger-400 ml-1">
                        {{ $this->formatCurrency($todayCashOut) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    @if($selected_account_id)
        {{-- Transaction Form --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('accounting::accounting.new_transaction') }}
            </x-slot>

            <x-filament-panels::form wire:submit="saveTransaction">
                {{ $this->form }}

                <div class="flex justify-end mt-4">
                    <x-filament::button type="submit" color="primary">
                        {{ __('accounting::accounting.save_transaction') }}
                    </x-filament::button>
                </div>
            </x-filament-panels::form>
        </x-filament::section>

        {{-- Recent Transactions --}}
        <x-filament::section class="mt-6">
            <x-slot name="heading">
                <div class="flex justify-between items-center w-full">
                    <span>{{ __('accounting::accounting.recent_transactions') }}</span>
                </div>
            </x-slot>

            @if(count($recentTransactions) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.date') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.reference') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.description') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.partner') }}</th>
                                <th class="px-4 py-3 text-right font-semibold text-success-600 dark:text-success-400">{{ __('accounting::accounting.cash_in') }}</th>
                                <th class="px-4 py-3 text-right font-semibold text-danger-600 dark:text-danger-400">{{ __('accounting::accounting.cash_out') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentTransactions as $transaction)
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900">
                                    <td class="px-4 py-3 font-mono text-sm">
                                        {{ $transaction['date'] }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-filament::badge color="info" size="sm">
                                            {{ $transaction['code'] }}
                                        </x-filament::badge>
                                        @if($transaction['reference'])
                                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">
                                                {{ $transaction['reference'] }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 max-w-xs truncate" title="{{ $transaction['description'] ?? '' }}">
                                        {{ $transaction['description'] ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $transaction['partner_name'] ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">
                                        @if($transaction['cash_in'] > 0)
                                            <span class="text-success-600 dark:text-success-400">
                                                {{ $this->formatCurrency($transaction['cash_in']) }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">
                                        @if($transaction['cash_out'] > 0)
                                            <span class="text-danger-600 dark:text-danger-400">
                                                {{ $this->formatCurrency($transaction['cash_out']) }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-8 text-gray-500">
                    <x-heroicon-o-banknotes class="mx-auto h-12 w-12 text-gray-400" />
                    <p class="mt-4">{{ __('accounting::accounting.no_transactions') }}</p>
                </div>
            @endif
        </x-filament::section>
    @else
        {{-- No Account Selected --}}
        <x-filament::section>
            <div class="text-center py-12 text-gray-500">
                <x-heroicon-o-banknotes class="mx-auto h-16 w-16 text-gray-400" />
                <p class="mt-4 text-lg">{{ __('accounting::accounting.messages.select_account_first') }}</p>
                <p class="mt-2 text-sm">{{ __('accounting::accounting.messages.select_account_hint') }}</p>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
