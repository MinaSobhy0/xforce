<x-filament-panels::page>
    <x-filament-panels::form wire:submit="loadCashFlow">
        {{ $this->form }}
    </x-filament-panels::form>

    <div class="mt-6 space-y-6">
        {{-- Operating Activities --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('accounting::accounting.operating_activities') }}
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.description') }}</th>
                            <th class="px-4 py-3 text-right font-semibold">{{ __('accounting::accounting.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($operatingActivities as $activity)
                            <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="px-4 py-3">{{ $activity['description'] }}</td>
                                <td class="px-4 py-3 text-right font-mono {{ $activity['amount'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                    {{ $this->formatCurrency($activity['amount']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-8 text-center text-gray-500">
                                    {{ __('accounting::accounting.no_activities') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 font-bold">
                            <td class="px-4 py-3">{{ __('accounting::accounting.net_operating') }}</td>
                            <td class="px-4 py-3 text-right font-mono {{ $netOperating >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                {{ $this->formatCurrency($netOperating) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>

        {{-- Investing Activities --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('accounting::accounting.investing_activities') }}
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.description') }}</th>
                            <th class="px-4 py-3 text-right font-semibold">{{ __('accounting::accounting.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($investingActivities as $activity)
                            <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="px-4 py-3">{{ $activity['description'] }}</td>
                                <td class="px-4 py-3 text-right font-mono {{ $activity['amount'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                    {{ $this->formatCurrency($activity['amount']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-8 text-center text-gray-500">
                                    {{ __('accounting::accounting.no_investing_activities') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 font-bold">
                            <td class="px-4 py-3">{{ __('accounting::accounting.net_investing') }}</td>
                            <td class="px-4 py-3 text-right font-mono {{ $netInvesting >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                {{ $this->formatCurrency($netInvesting) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>

        {{-- Financing Activities --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('accounting::accounting.financing_activities') }}
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-4 py-3 text-left font-semibold">{{ __('accounting::accounting.description') }}</th>
                            <th class="px-4 py-3 text-right font-semibold">{{ __('accounting::accounting.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($financingActivities as $activity)
                            <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="px-4 py-3">{{ $activity['description'] }}</td>
                                <td class="px-4 py-3 text-right font-mono {{ $activity['amount'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                    {{ $this->formatCurrency($activity['amount']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-8 text-center text-gray-500">
                                    {{ __('accounting::accounting.no_financing_activities') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 font-bold">
                            <td class="px-4 py-3">{{ __('accounting::accounting.net_financing') }}</td>
                            <td class="px-4 py-3 text-right font-mono {{ $netFinancing >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                {{ $this->formatCurrency($netFinancing) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>

        {{-- Net Cash Flow Summary --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('accounting::accounting.cash_flow_summary') }}
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="p-4 rounded-lg {{ $netOperating >= 0 ? 'bg-success-50 dark:bg-success-900/20' : 'bg-danger-50 dark:bg-danger-900/20' }}">
                    <p class="text-sm {{ $netOperating >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                        {{ __('accounting::accounting.operating') }}
                    </p>
                    <p class="text-xl font-bold {{ $netOperating >= 0 ? 'text-success-700 dark:text-success-300' : 'text-danger-700 dark:text-danger-300' }}">
                        {{ $this->formatCurrency($netOperating) }}
                    </p>
                </div>
                <div class="p-4 rounded-lg {{ $netInvesting >= 0 ? 'bg-success-50 dark:bg-success-900/20' : 'bg-danger-50 dark:bg-danger-900/20' }}">
                    <p class="text-sm {{ $netInvesting >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                        {{ __('accounting::accounting.investing') }}
                    </p>
                    <p class="text-xl font-bold {{ $netInvesting >= 0 ? 'text-success-700 dark:text-success-300' : 'text-danger-700 dark:text-danger-300' }}">
                        {{ $this->formatCurrency($netInvesting) }}
                    </p>
                </div>
                <div class="p-4 rounded-lg {{ $netFinancing >= 0 ? 'bg-success-50 dark:bg-success-900/20' : 'bg-danger-50 dark:bg-danger-900/20' }}">
                    <p class="text-sm {{ $netFinancing >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                        {{ __('accounting::accounting.financing') }}
                    </p>
                    <p class="text-xl font-bold {{ $netFinancing >= 0 ? 'text-success-700 dark:text-success-300' : 'text-danger-700 dark:text-danger-300' }}">
                        {{ $this->formatCurrency($netFinancing) }}
                    </p>
                </div>
                <div class="p-4 rounded-lg {{ $netCashFlow >= 0 ? 'bg-success-50 dark:bg-success-900/20' : 'bg-danger-50 dark:bg-danger-900/20' }}">
                    <p class="text-sm {{ $netCashFlow >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                        {{ __('accounting::accounting.net_change') }}
                    </p>
                    <p class="text-2xl font-bold {{ $netCashFlow >= 0 ? 'text-success-700 dark:text-success-300' : 'text-danger-700 dark:text-danger-300' }}">
                        {{ $this->formatCurrency($netCashFlow) }}
                    </p>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
