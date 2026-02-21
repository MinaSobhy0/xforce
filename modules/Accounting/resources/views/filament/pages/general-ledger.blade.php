<x-filament-panels::page>
    <x-filament-panels::form wire:submit="loadLedger">
        {{ $this->form }}
    </x-filament-panels::form>

    @if($account_id)
        <div class="mt-6">
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('accounting::accounting.ledger_entries') }}
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
        <div class="mt-6">
            <x-filament::section>
                <div class="text-center py-8 text-gray-500">
                    <x-heroicon-o-book-open class="mx-auto h-12 w-12 text-gray-400" />
                    <p class="mt-4">{{ __('accounting::accounting.select_account_prompt') }}</p>
                </div>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
