<?php

namespace Modules\Booking\Livewire;

use Livewire\Component;

class BookingCart extends Component
{
    public array $items = [];

    public function mount(array $items = []): void
    {
        $this->items = $items;
    }

    public function removeItem(int $index): void
    {
        $this->dispatch('slot-removed', index: $index);
    }

    public function getTotalDuration(): int
    {
        return collect($this->items)->sum('duration');
    }

    public function getItemCount(): int
    {
        return count($this->items);
    }

    public function clear(): void
    {
        foreach (array_keys($this->items) as $index) {
            $this->dispatch('slot-removed', index: $index);
        }
    }

    public function render()
    {
        return view('booking::livewire.booking.booking-cart', [
            'totalDuration' => $this->getTotalDuration(),
            'itemCount' => $this->getItemCount(),
        ]);
    }
}
