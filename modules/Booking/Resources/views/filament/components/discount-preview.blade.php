<div class="space-y-2 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
    <div class="flex justify-between text-sm">
        <span class="text-gray-500 dark:text-gray-400">{{ __('booking::session.discount.original_price') }}</span>
        <span class="font-medium">{{ number_format($originalPrice, 2) }}</span>
    </div>
    <div class="flex justify-between text-sm">
        <span class="text-gray-500 dark:text-gray-400">{{ __('booking::session.discount.discount_amount') }}</span>
        <span class="font-medium text-red-600 dark:text-red-400">-{{ number_format($discountAmount, 2) }}</span>
    </div>
    <div class="border-t border-gray-200 dark:border-gray-700 pt-2">
        <div class="flex justify-between text-base">
            <span class="font-semibold text-gray-700 dark:text-gray-300">{{ __('booking::session.discount.final_price') }}</span>
            <span class="font-bold text-primary-600 dark:text-primary-400">{{ number_format($finalPrice, 2) }}</span>
        </div>
    </div>
</div>
