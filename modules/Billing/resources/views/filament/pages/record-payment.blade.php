<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-end gap-x-3">
            <x-filament::button
                type="button"
                color="gray"
                tag="a"
                :href="$this->getResource()::getUrl('view', ['record' => $this->record])"
            >
                Cancel
            </x-filament::button>

            <x-filament::button type="submit">
                Record Payment
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
