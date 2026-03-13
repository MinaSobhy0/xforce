<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Summary Stats --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            @php
                $stats = $this->getStockSummaryStats();
            @endphp

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary-600">{{ $stats['total_products'] }}</div>
                    <div class="text-sm text-gray-500">{{ __('inventory::inventory.stock_report.total_products') }}</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-success-600">{{ number_format($stats['total_quantity']) }}</div>
                    <div class="text-sm text-gray-500">{{ __('inventory::inventory.stock_report.total_quantity') }}</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-warning-600">{{ $stats['low_stock_count'] }}</div>
                    <div class="text-sm text-gray-500">{{ __('inventory::inventory.stock_report.low_stock_items') }}</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-info-600">{{ number_format($stats['total_value'] / 100, 2) }} EGP</div>
                    <div class="text-sm text-gray-500">{{ __('inventory::inventory.stock_report.total_value') }}</div>
                </div>
            </x-filament::section>
        </div>

        {{-- Stock Table --}}
        {{ $this->table }}
    </div>
</x-filament-panels::page>
