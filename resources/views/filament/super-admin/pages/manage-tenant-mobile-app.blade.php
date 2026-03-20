<x-filament-panels::page>
    {{-- Tenant Info Header --}}
    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center gap-4">
            @if($tenant->getLogoUrl())
                <img src="{{ $tenant->getLogoUrl() }}" alt="{{ $tenant->name }}" class="h-12 w-12 rounded-lg object-cover" />
            @else
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary-100 dark:bg-primary-900">
                    <x-heroicon-o-building-office-2 class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
            @endif
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $tenant->name }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $tenant->slug }}.x-linic.com</p>
            </div>
            <div class="ml-auto">
                <x-filament::badge
                    :color="match($tenant->subscription_status) {
                        'active' => 'success',
                        'trial' => 'info',
                        'past_due' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    }"
                >
                    {{ ucfirst($tenant->subscription_status ?? 'pending') }}
                </x-filament::badge>
            </div>
        </div>
    </div>

    {{-- Configuration Form --}}
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-end gap-3">
            <x-filament::button
                type="submit"
                color="success"
                icon="heroicon-o-check"
            >
                Save Configuration
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
