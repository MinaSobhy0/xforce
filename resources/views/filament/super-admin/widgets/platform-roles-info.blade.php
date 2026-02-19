<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Platform Admin Roles</x-slot>

        <div class="grid md:grid-cols-5 gap-4">
            @foreach ($this->getRoles() as $role)
                <div class="text-center p-3 rounded-lg bg-{{ $role['color'] }}-50 dark:bg-{{ $role['color'] }}-900/20">
                    <div class="font-medium text-{{ $role['color'] }}-700 dark:text-{{ $role['color'] }}-400">
                        {{ $role['name'] }}
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                        {{ $role['description'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
