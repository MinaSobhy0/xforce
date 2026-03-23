<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex gap-3">
            <x-filament::button type="submit">
                {{ __('mobile_api::mobile.save_settings') }}
            </x-filament::button>
        </div>
    </form>

    {{-- Registered Devices Table --}}
    <div class="mt-8">
        <x-filament::section>
            <x-slot name="heading">
                {{ __('mobile_api::mobile.registered_devices') }}
            </x-slot>
            <x-slot name="description">
                {{ __('mobile_api::mobile.registered_devices_description') }}
            </x-slot>

            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
