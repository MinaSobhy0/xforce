<div class="space-y-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('giftcards::giftcards.stats.total_issued') }}</div>
            <div class="text-2xl font-bold">{{ number_format($stats['total_issued']) }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-300">{{ format_money($stats['total_issued_value']) }}</div>
        </div>

        <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('giftcards::giftcards.stats.active_cards') }}</div>
            <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($stats['active_count']) }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-300">{{ format_money($stats['outstanding_balance']) }}</div>
        </div>

        <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('giftcards::giftcards.stats.redeemed_value') }}</div>
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ format_money($stats['redeemed_value']) }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-300">{{ $stats['redemption_rate'] }}% {{ __('giftcards::giftcards.stats.redemption_rate') }}</div>
        </div>

        <div class="p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('giftcards::giftcards.stats.expired_cards') }}</div>
            <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ number_format($stats['expired_count']) }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-300">{{ format_money($stats['breakage_value']) }} {{ __('giftcards::giftcards.stats.breakage_value') }}</div>
        </div>
    </div>
</div>
