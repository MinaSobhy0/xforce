<div class="space-y-4">
    @if($movements->isEmpty())
        <div class="text-center py-8 text-gray-500">
            {{ __('inventory::inventory.stock_report.no_movements') }}
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ __('inventory::inventory.stock_report.date') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ __('inventory::inventory.stock_report.type') }}</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">{{ __('inventory::inventory.stock_report.quantity') }}</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">{{ __('inventory::inventory.stock_report.unit_cost') }}</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">{{ __('inventory::inventory.stock_report.total') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ __('inventory::inventory.stock_report.from_to') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ __('inventory::inventory.stock_report.reference') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ __('inventory::inventory.stock_report.user') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($movements as $movement)
                        @php
                            $isIncoming = $movement->isIncoming();
                            $typeColor = $isIncoming ? 'success' : 'danger';
                            $typeLabel = \Modules\Inventory\Models\StockMovement::TYPES[$movement->movement_type] ?? $movement->movement_type;
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $movement->created_at->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $typeColor }}-100 text-{{ $typeColor }}-800 dark:bg-{{ $typeColor }}-900 dark:text-{{ $typeColor }}-200">
                                    {{ $typeLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono {{ $movement->quantity >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                                {{ $movement->quantity >= 0 ? '+' : '' }}{{ number_format($movement->quantity) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono">
                                {{ number_format(($movement->unit_cost_minor ?? 0) / 100, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-medium">
                                {{ number_format(abs($movement->quantity) * ($movement->unit_cost_minor ?? 0) / 100, 2) }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($movement->sourceLocation || $movement->destinationLocation)
                                    <span class="text-gray-500">
                                        {{ $movement->sourceLocation?->getTranslation('name', app()->getLocale()) ?? '-' }}
                                    </span>
                                    <span class="mx-1">&rarr;</span>
                                    <span class="text-gray-700 dark:text-gray-300">
                                        {{ $movement->destinationLocation?->getTranslation('name', app()->getLocale()) ?? '-' }}
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                @if($movement->reference_type)
                                    {{ class_basename($movement->reference_type) }}
                                    @if($movement->reference_id)
                                        #{{ $movement->reference_id }}
                                    @endif
                                @else
                                    {{ $movement->notes ?? '-' }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                {{ $movement->createdBy?->full_name ?? '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($movements->count() >= 50)
            <div class="text-center text-sm text-gray-500 py-2">
                {{ __('inventory::inventory.stock_report.showing_latest', ['count' => 50]) }}
            </div>
        @endif
    @endif
</div>
