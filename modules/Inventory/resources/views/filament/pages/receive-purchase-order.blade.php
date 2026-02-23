<x-filament-panels::page>
    <x-filament-panels::form wire:submit="receive">
        {{ $this->form }}

        <div class="flex justify-end gap-x-3 mt-6">
            <x-filament::button
                type="button"
                color="gray"
                tag="a"
                :href="$this->getResource()::getUrl('view', ['record' => $this->record->getKey()])"
            >
                {{ __('inventory::inventory.actions.cancel') }}
            </x-filament::button>

            <x-filament::button
                type="submit"
                color="success"
                icon="heroicon-o-check"
            >
                {{ __('inventory::inventory.actions.confirm_receive') }}
            </x-filament::button>
        </div>
    </x-filament-panels::form>
</x-filament-panels::page>
