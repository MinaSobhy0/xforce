<x-filament-panels::page>
    {{-- Journal Selector and Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        {{-- Journal Selector --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-sm">
            {{ $this->journalForm }}
            @if($journalName)
                <div class="mt-2 flex items-center gap-2">
                    <x-filament::badge :color="$this->getJournalTypeColor()">
                        {{ $this->getJournalTypeLabel() }}
                    </x-filament::badge>
                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ $journalName }}</span>
                </div>
            @endif
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

    @if($selected_journal_id)
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

        {{-- Transactions --}}
        <x-filament::section class="mt-6">
            <x-slot name="heading">
                <div class="flex justify-between items-center w-full">
                    <span>{{ __('accounting::accounting.transactions') }}</span>
                    <div class="flex items-center gap-2">
                        <input
                            type="date"
                            wire:model.live="filter_date"
                            class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500"
                        />
                    </div>
                </div>
            </x-slot>

            @if(count($recentTransactions) > 0)
                <div class="overflow-x-auto" id="transactions-table">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.date') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.reference') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.description') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.partner') }}</th>
                                <th class="px-4 py-3 text-right font-semibold text-success-600 dark:text-success-400">{{ __('accounting::accounting.cash_in') }}</th>
                                <th class="px-4 py-3 text-right font-semibold text-danger-600 dark:text-danger-400">{{ __('accounting::accounting.cash_out') }}</th>
                                <th class="px-4 py-3 text-center font-semibold w-16"></th>
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
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            type="button"
                                            wire:click="printEntry({{ $transaction['id'] }})"
                                            class="text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 transition"
                                            title="{{ __('accounting::accounting.print') }}"
                                        >
                                            <x-heroicon-o-printer class="w-5 h-5" />
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-900 font-semibold">
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-right">{{ __('accounting::accounting.totals') }}:</td>
                                <td class="px-4 py-3 text-right font-mono text-success-600 dark:text-success-400">
                                    {{ $this->formatCurrency(collect($recentTransactions)->sum('cash_in')) }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-danger-600 dark:text-danger-400">
                                    {{ $this->formatCurrency(collect($recentTransactions)->sum('cash_out')) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="text-center py-8 text-gray-500">
                    <x-heroicon-o-banknotes class="mx-auto h-12 w-12 text-gray-400" />
                    <p class="mt-4">{{ __('accounting::accounting.no_transactions_on_date') }}</p>
                    <p class="mt-1 text-sm">{{ $filter_date }}</p>
                </div>
            @endif
        </x-filament::section>
    @else
        {{-- No Journal Selected --}}
        <x-filament::section>
            <div class="text-center py-12 text-gray-500">
                <x-heroicon-o-banknotes class="mx-auto h-16 w-16 text-gray-400" />
                <p class="mt-4 text-lg">{{ __('accounting::accounting.messages.select_journal_first') }}</p>
                <p class="mt-2 text-sm">{{ __('accounting::accounting.messages.select_journal_hint') }}</p>
            </div>
        </x-filament::section>
    @endif

    @script
    <script>
        $wire.on('print-cash-voucher', (event) => {
            const data = event.data || event[0]?.data || event[0];
            if (!data || !data.code) {
                alert('No voucher data');
                return;
            }

            const userName = @js(auth()->user()?->name ?? '-');
            const printedOn = new Date().toLocaleString();

            const printContent = `
                <div style="font-family: Arial, sans-serif; font-size: 10pt; width: 148mm; padding: 8mm;">
                    <div style="text-align: center; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 10px;">
                        <h2 style="margin: 0; font-size: 16pt;">{{ __('accounting::accounting.cash_voucher') }}</h2>
                        <p style="margin: 5px 0 0 0; font-size: 12pt; font-weight: bold;">${data.type}</p>
                    </div>

                    <table style="width: 100%; margin-bottom: 15px; font-size: 10pt;">
                        <tr>
                            <td style="width: 50%; padding: 5px 0;">
                                <strong>{{ __('accounting::accounting.voucher_no') }}:</strong> ${data.code}
                            </td>
                            <td style="width: 50%; padding: 5px 0; text-align: right;">
                                <strong>{{ __('accounting::accounting.date') }}:</strong> ${data.date}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding: 5px 0;">
                                <strong>{{ __('accounting::accounting.journal') }}:</strong> ${data.journal || ''}
                            </td>
                        </tr>
                    </table>

                    <div style="border: 2px solid #000; padding: 15px; margin-bottom: 15px; text-align: center;">
                        <div style="font-size: 11pt; margin-bottom: 5px;">{{ __('accounting::accounting.amount') }}</div>
                        <div style="font-size: 20pt; font-weight: bold;">${data.amount_formatted}</div>
                    </div>

                    <table style="width: 100%; margin-bottom: 15px; font-size: 10pt;">
                        ${data.partner_name ? `
                        <tr>
                            <td style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                                <strong>{{ __('accounting::accounting.partner') }}:</strong>
                            </td>
                            <td style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                                ${data.partner_name}
                            </td>
                        </tr>
                        ` : ''}
                        ${data.reference ? `
                        <tr>
                            <td style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                                <strong>{{ __('accounting::accounting.reference') }}:</strong>
                            </td>
                            <td style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                                ${data.reference}
                            </td>
                        </tr>
                        ` : ''}
                        ${data.description ? `
                        <tr>
                            <td style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                                <strong>{{ __('accounting::accounting.description') }}:</strong>
                            </td>
                            <td style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                                ${data.description}
                            </td>
                        </tr>
                        ` : ''}
                    </table>

                    <div style="margin-top: 30px; display: flex; justify-content: space-between; font-size: 9pt;">
                        <div style="text-align: center; width: 45%;">
                            <div style="border-top: 1px solid #000; padding-top: 5px; margin-top: 40px;">
                                {{ __('accounting::accounting.prepared_by') }}
                            </div>
                            <div style="margin-top: 5px; color: #666;">${userName}</div>
                        </div>
                        <div style="text-align: center; width: 45%;">
                            <div style="border-top: 1px solid #000; padding-top: 5px; margin-top: 40px;">
                                {{ __('accounting::accounting.received_by') }}
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 20px; text-align: center; font-size: 8pt; color: #999;">
                        {{ __('accounting::accounting.printed_on') }}: ${printedOn}
                    </div>
                </div>
            `;

            const printWindow = window.open('', '_blank', 'width=600,height=800');
            if (!printWindow) {
                alert('Please allow popups for this site');
                return;
            }
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>{{ __('accounting::accounting.cash_voucher') }}</title>
                    <style>
                        @page { size: A5 portrait; margin: 5mm; }
                        @media print {
                            body { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                        }
                        body { margin: 0; padding: 0; font-family: Arial, sans-serif; }
                    </style>
                </head>
                <body>
                    ${printContent}
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
            }, 250);
        });
    </script>
    @endscript
</x-filament-panels::page>
