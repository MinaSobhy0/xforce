<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="{{ url('/admin/profit-loss') }}" class="block p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-500 transition">
            <div class="flex items-center gap-3">
                <x-heroicon-o-presentation-chart-line class="w-8 h-8 text-primary-500" />
                <div>
                    <h3 class="font-semibold">{{ __('P&L Statement') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('Income & expenses') }}</p>
                </div>
            </div>
        </a>
        <a href="{{ url('/admin/balance-sheet') }}" class="block p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-500 transition">
            <div class="flex items-center gap-3">
                <x-heroicon-o-table-cells class="w-8 h-8 text-primary-500" />
                <div>
                    <h3 class="font-semibold">{{ __('Balance Sheet') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('Assets & liabilities') }}</p>
                </div>
            </div>
        </a>
        <a href="{{ url('/admin/cash-flow') }}" class="block p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-500 transition">
            <div class="flex items-center gap-3">
                <x-heroicon-o-arrow-trending-up class="w-8 h-8 text-primary-500" />
                <div>
                    <h3 class="font-semibold">{{ __('Cash Flow') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('Cash movements') }}</p>
                </div>
            </div>
        </a>
        <a href="{{ url('/admin/trial-balance') }}" class="block p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-500 transition">
            <div class="flex items-center gap-3">
                <x-heroicon-o-scale class="w-8 h-8 text-primary-500" />
                <div>
                    <h3 class="font-semibold">{{ __('Trial Balance') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('Debit & credit') }}</p>
                </div>
            </div>
        </a>
        <a href="{{ url('/admin/general-ledger') }}" class="block p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-500 transition">
            <div class="flex items-center gap-3">
                <x-heroicon-o-book-open class="w-8 h-8 text-primary-500" />
                <div>
                    <h3 class="font-semibold">{{ __('General Ledger') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('All transactions') }}</p>
                </div>
            </div>
        </a>
        <a href="{{ url('/admin/revenue-report') }}" class="block p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-500 transition">
            <div class="flex items-center gap-3">
                <x-heroicon-o-banknotes class="w-8 h-8 text-primary-500" />
                <div>
                    <h3 class="font-semibold">{{ __('Revenue Report') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('Revenue analysis') }}</p>
                </div>
            </div>
        </a>
        <a href="{{ url('/admin/financial-summary') }}" class="block p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-500 transition">
            <div class="flex items-center gap-3">
                <x-heroicon-o-calculator class="w-8 h-8 text-primary-500" />
                <div>
                    <h3 class="font-semibold">{{ __('Financial Summary') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('Overview') }}</p>
                </div>
            </div>
        </a>
    </div>
</x-filament-panels::page>
