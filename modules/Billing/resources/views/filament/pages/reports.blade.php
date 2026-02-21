<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-presentation-chart-line class="w-5 h-5" />
                    {{ __('accounting::accounting.profit_loss') }}
                </div>
            </x-slot>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">{{ __('Income and expenses summary') }}</p>
            <x-filament::button tag="a" href="{{ url('/admin/profit-loss') }}" color="gray" size="sm">
                {{ __('View Report') }}
            </x-filament::button>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-table-cells class="w-5 h-5" />
                    {{ __('accounting::accounting.balance_sheet') }}
                </div>
            </x-slot>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">{{ __('Assets, liabilities, and equity') }}</p>
            <x-filament::button tag="a" href="{{ url('/admin/balance-sheet') }}" color="gray" size="sm">
                {{ __('View Report') }}
            </x-filament::button>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-arrow-trending-up class="w-5 h-5" />
                    {{ __('accounting::accounting.cash_flow') }}
                </div>
            </x-slot>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">{{ __('Cash inflows and outflows') }}</p>
            <x-filament::button tag="a" href="{{ url('/admin/cash-flow') }}" color="gray" size="sm">
                {{ __('View Report') }}
            </x-filament::button>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-scale class="w-5 h-5" />
                    {{ __('accounting::accounting.trial_balance') }}
                </div>
            </x-slot>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">{{ __('Debit and credit balances') }}</p>
            <x-filament::button tag="a" href="{{ url('/admin/trial-balance') }}" color="gray" size="sm">
                {{ __('View Report') }}
            </x-filament::button>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-book-open class="w-5 h-5" />
                    {{ __('accounting::accounting.general_ledger') }}
                </div>
            </x-slot>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">{{ __('All account transactions') }}</p>
            <x-filament::button tag="a" href="{{ url('/admin/general-ledger') }}" color="gray" size="sm">
                {{ __('View Report') }}
            </x-filament::button>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-banknotes class="w-5 h-5" />
                    {{ __('reporting::reporting.revenue_report') }}
                </div>
            </x-slot>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">{{ __('Revenue analysis by treatment') }}</p>
            <x-filament::button tag="a" href="{{ url('/admin/revenue-report') }}" color="gray" size="sm">
                {{ __('View Report') }}
            </x-filament::button>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-calculator class="w-5 h-5" />
                    {{ __('reporting::reporting.financial_summary') }}
                </div>
            </x-slot>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">{{ __('Overall financial overview') }}</p>
            <x-filament::button tag="a" href="{{ url('/admin/financial-summary') }}" color="gray" size="sm">
                {{ __('View Report') }}
            </x-filament::button>
        </x-filament::section>
    </div>
</x-filament-panels::page>
