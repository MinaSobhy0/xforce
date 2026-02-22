<?php

namespace Modules\Booking\Livewire;

use Livewire\Component;

class SlotGrid extends Component
{
    public array $slots = [];
    public array $selectedServices = [];
    public ?string $branchId = null;
    public ?string $date = null;

    // View mode: 'cards' or 'compact'
    public string $viewMode = 'cards';

    // Grouping: 'time', 'service', or 'date'
    public string $groupBy = 'date';

    public function mount(
        array $slots = [],
        array $selectedServices = [],
        ?string $branchId = null,
        ?string $date = null,
        string $viewMode = 'cards',
        string $groupBy = 'date'
    ): void {
        $this->slots = $slots;
        $this->selectedServices = $selectedServices;
        $this->branchId = $branchId;
        $this->date = $date;
        $this->viewMode = $viewMode;
        $this->groupBy = $groupBy;
    }

    public function getGroupedSlots(): array
    {
        $slots = collect($this->slots);

        return match ($this->groupBy) {
            'time' => $slots->groupBy('start_time')->toArray(),
            'service' => $slots->groupBy('service_id')->toArray(),
            'date' => $slots->groupBy('date')->sortKeys()->toArray(),
            default => $slots->groupBy('date')->sortKeys()->toArray(),
        };
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function setGroupBy(string $groupBy): void
    {
        $this->groupBy = $groupBy;
    }

    public function render()
    {
        return view('booking::livewire.booking.slot-grid', [
            'groupedSlots' => $this->getGroupedSlots(),
        ]);
    }
}
