<x-filament-panels::page>
    <form wire:submit.prevent="createBookings">
        {{ $this->form }}
    </form>
</x-filament-panels::page>
