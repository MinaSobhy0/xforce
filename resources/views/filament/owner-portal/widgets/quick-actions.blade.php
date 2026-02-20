<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Quick Actions
        </x-slot>

        <div class="space-y-2">
            <a href="{{ route('filament.admin.pages.my-subscription') }}"
               class="flex items-center gap-3 p-3 rounded-lg bg-primary-50 dark:bg-primary-900/20 hover:bg-primary-100 dark:hover:bg-primary-900/30 transition-colors">
                <x-heroicon-o-arrow-up-circle class="w-6 h-6 text-primary-500" />
                <div>
                    <p class="font-medium text-gray-900 dark:text-white">Upgrade Plan</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Get more features and limits</p>
                </div>
            </a>

            <a href="{{ route('filament.admin.resources.my-support-tickets.create') }}"
               class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                <x-heroicon-o-chat-bubble-left-right class="w-6 h-6 text-gray-500" />
                <div>
                    <p class="font-medium text-gray-900 dark:text-white">Contact Support</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Get help from our team</p>
                </div>
            </a>

            <a href="{{ route('filament.admin.resources.my-invoices.index') }}"
               class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                <x-heroicon-o-document-text class="w-6 h-6 text-gray-500" />
                <div>
                    <p class="font-medium text-gray-900 dark:text-white">View Invoices</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Download and manage invoices</p>
                </div>
            </a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
