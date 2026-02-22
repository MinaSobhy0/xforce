<x-filament-panels::page>
    {{-- Main Form --}}
    <form wire:submit.prevent="createBookings">
        {{ $this->form }}
    </form>

    {{-- Slot Grid --}}
    @if(!empty($this->availableSlots))
        <div class="mt-6">
            @include('booking::components.inline-slot-grid', [
                'slots' => $this->availableSlots,
                'selectedSlots' => $this->getSelectedSlotKeys(),
            ])
        </div>
    @endif

    {{-- Bottom Summary Bar --}}
    @if(!empty($this->bookingItems))
        <div class="h-24"></div>
        @include('booking::components.booking-summary-bar', ['items' => $this->bookingItems])
    @endif
</x-filament-panels::page>
