<x-filament-panels::page>
    <form wire:submit.prevent="sellPackage">
        {{ $this->form }}

        @if($this->selectedPackage && $this->patient_id)
            <div class="mt-6 flex justify-end gap-x-3">
                <x-filament::button
                    type="button"
                    color="gray"
                    wire:click="$set('package_id', null)"
                >
                    {{ __('packages::packages.actions.cancel') }}
                </x-filament::button>

                <x-filament::button
                    type="submit"
                    color="success"
                    icon="heroicon-o-shopping-cart"
                >
                    {{ __('packages::packages.actions.sell_package') }}
                </x-filament::button>
            </div>
        @endif
    </form>
</x-filament-panels::page>
