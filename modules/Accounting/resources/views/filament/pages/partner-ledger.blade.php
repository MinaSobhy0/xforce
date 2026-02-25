<x-filament-panels::page>
    <x-filament-panels::form wire:submit="loadReportData">
        {{ $this->form }}
    </x-filament-panels::form>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
        <x-filament::section>
            <div class="text-center">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('accounting::accounting.total_partners') }}</div>
                <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                    {{ $stats['total_partners'] ?? 0 }}
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('accounting::accounting.total_debit') }}</div>
                <div class="text-2xl font-bold text-success-600 dark:text-success-400 font-mono">
                    {{ $this->formatCurrency($stats['total_debit'] ?? 0) }}
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('accounting::accounting.total_credit') }}</div>
                <div class="text-2xl font-bold text-danger-600 dark:text-danger-400 font-mono">
                    {{ $this->formatCurrency($stats['total_credit'] ?? 0) }}
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('accounting::accounting.net_balance') }}</div>
                <div class="text-2xl font-bold font-mono {{ ($stats['net_balance'] ?? 0) >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                    {{ $this->formatCurrency($stats['net_balance'] ?? 0) }}
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Partner Ledger Data --}}
    <div class="mt-6 space-y-6">
        @forelse($partnerData as $partner)
            <x-filament::section :collapsible="true" :collapsed="true">
                <x-slot name="heading">
                    <div class="flex justify-between items-center w-full">
                        <div class="flex items-center gap-3">
                            <span class="font-semibold">{{ $partner['partner_name'] }}</span>
                            <x-filament::badge :color="$partner['partner_type_label'] === __('accounting::accounting.customer') ? 'info' : 'warning'">
                                {{ $partner['partner_type_label'] }}
                            </x-filament::badge>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ __('accounting::accounting.balance') }}:
                            </span>
                            <span class="font-mono font-semibold {{ $partner['closing_balance'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                {{ $this->formatCurrency($partner['closing_balance']) }}
                            </span>
                        </div>
                    </div>
                </x-slot>

                @if(count($partner['transactions']) > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.date') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.reference') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.description') }}</th>
                                    <th class="px-4 py-3 text-right font-semibold">{{ __('accounting::accounting.debit') }}</th>
                                    <th class="px-4 py-3 text-right font-semibold">{{ __('accounting::accounting.credit') }}</th>
                                    <th class="px-4 py-3 text-right font-semibold">{{ __('accounting::accounting.balance') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($partner['transactions'] as $transaction)
                                    <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900">
                                        <td class="px-4 py-3 font-mono">{{ $transaction['date'] }}</td>
                                        <td class="px-4 py-3">
                                            <x-filament::badge color="info">
                                                {{ $transaction['reference'] }}
                                            </x-filament::badge>
                                        </td>
                                        <td class="px-4 py-3">{{ $transaction['description'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-right font-mono text-success-600 dark:text-success-400">
                                            {{ $transaction['debit'] > 0 ? $this->formatCurrency($transaction['debit']) : '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono text-danger-600 dark:text-danger-400">
                                            {{ $transaction['credit'] > 0 ? $this->formatCurrency($transaction['credit']) : '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono font-semibold {{ $transaction['balance'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                            {{ $this->formatCurrency($transaction['balance']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800">
                                    <td colspan="3" class="px-4 py-3 font-semibold">{{ __('accounting::accounting.totals') }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-success-600 dark:text-success-400">
                                        {{ $this->formatCurrency($partner['total_debit']) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-danger-600 dark:text-danger-400">
                                        {{ $this->formatCurrency($partner['total_credit']) }}
                                    </td>
                                    <td></td>
                                </tr>
                                <tr class="bg-primary-50 dark:bg-primary-900/20">
                                    <td colspan="5" class="px-4 py-3 font-semibold">{{ __('accounting::accounting.closing_balance') }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-xl {{ $partner['closing_balance'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                        {{ $this->formatCurrency($partner['closing_balance']) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4 text-gray-500">
                        <p>{{ __('accounting::accounting.no_entries') }}</p>
                        <p class="mt-2">
                            <span class="font-semibold">{{ __('accounting::accounting.closing_balance') }}:</span>
                            <span class="font-mono {{ $partner['closing_balance'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                {{ $this->formatCurrency($partner['closing_balance']) }}
                            </span>
                        </p>
                    </div>
                @endif
            </x-filament::section>
        @empty
            <x-filament::section>
                <div class="text-center py-8 text-gray-500">
                    <x-heroicon-o-users class="mx-auto h-12 w-12 text-gray-400" />
                    <p class="mt-4">{{ __('accounting::accounting.no_partner_activity') }}</p>
                </div>
            </x-filament::section>
        @endforelse
    </div>
</x-filament-panels::page>
