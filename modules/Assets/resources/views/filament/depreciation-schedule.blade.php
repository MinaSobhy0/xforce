<div class="overflow-x-auto">
    @if(empty($schedule))
        <p class="text-gray-500 dark:text-gray-400 text-center py-4">
            {{ __('No depreciation schedule available.') }}
        </p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-800">
                    <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">
                        {{ __('assets::assets.depreciation_entry.fields.period') }}
                    </th>
                    <th class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-300">
                        {{ __('assets::assets.depreciation_entry.fields.amount') }}
                    </th>
                    <th class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-300">
                        {{ __('assets::assets.depreciation_entry.fields.accumulated') }}
                    </th>
                    <th class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-300">
                        {{ __('assets::assets.depreciation_entry.fields.book_value') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($schedule as $entry)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-2 text-gray-900 dark:text-white">
                            {{ $entry['period'] }}
                        </td>
                        <td class="px-4 py-2 text-right text-gray-900 dark:text-white">
                            {{ format_money($entry['depreciation_amount_minor']) }}
                        </td>
                        <td class="px-4 py-2 text-right text-gray-900 dark:text-white">
                            {{ format_money($entry['accumulated_depreciation_minor']) }}
                        </td>
                        <td class="px-4 py-2 text-right text-gray-900 dark:text-white">
                            {{ format_money($entry['book_value_minor']) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
