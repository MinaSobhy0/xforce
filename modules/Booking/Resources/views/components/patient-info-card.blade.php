<div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
    <div class="flex items-start gap-4">
        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900">
            <x-heroicon-o-user class="h-6 w-6 text-primary-600 dark:text-primary-400" />
        </div>
        <div class="flex-1">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ $patient['name'] }}
            </h4>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $patient['code'] }}
            </p>
            <div class="mt-2 flex flex-wrap gap-4 text-sm">
                @if($patient['phone'])
                    <span class="flex items-center gap-1 text-gray-600 dark:text-gray-300">
                        <x-heroicon-o-phone class="h-4 w-4" />
                        {{ $patient['phone'] }}
                    </span>
                @endif
                @if($patient['email'])
                    <span class="flex items-center gap-1 text-gray-600 dark:text-gray-300">
                        <x-heroicon-o-envelope class="h-4 w-4" />
                        {{ $patient['email'] }}
                    </span>
                @endif
                @if($patient['active_packages'] > 0)
                    <span class="flex items-center gap-1 text-success-600 dark:text-success-400">
                        <x-heroicon-o-gift class="h-4 w-4" />
                        {{ $patient['active_packages'] }} {{ __('booking::booking.labels.active_packages') }}
                    </span>
                @endif
            </div>
        </div>
    </div>
</div>
