<?php

namespace Modules\Booking\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Collection;

class SlotGrid extends Component
{
    public array $slots = [];
    public array $selectedServices = [];
    public ?string $branchId = null;
    public ?string $date = null;

    // Grouping
    public string $groupBy = 'time'; // 'time' or 'service'

    // Selected practitioners for slots
    public array $selectedPractitioners = [];

    public function mount(
        array $slots = [],
        array $selectedServices = [],
        ?string $branchId = null,
        ?string $date = null
    ): void {
        $this->slots = $slots;
        $this->selectedServices = $selectedServices;
        $this->branchId = $branchId;
        $this->date = $date;
    }

    public function getGroupedSlots(): array
    {
        $slots = collect($this->slots);

        if ($this->groupBy === 'time') {
            return $slots->groupBy('start_time')->toArray();
        }

        return $slots->groupBy('service_id')->toArray();
    }

    public function getTimeSlots(): array
    {
        return collect($this->slots)
            ->pluck('start_time')
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    public function selectSlot(int $index): void
    {
        if (!isset($this->slots[$index])) {
            return;
        }

        $slot = $this->slots[$index];

        // Add selected practitioner if any
        if (isset($this->selectedPractitioners[$index])) {
            $practitionerId = $this->selectedPractitioners[$index];
            $practitioners = collect($slot['available_practitioners'] ?? []);
            $practitioner = $practitioners->firstWhere('id', $practitionerId);

            $slot['selected_practitioner_id'] = $practitionerId;
            $slot['selected_practitioner_name'] = $practitioner['name'] ?? null;
        }

        $this->dispatch('slot-selected', slot: $slot);
    }

    public function selectPractitioner(int $slotIndex, string $practitionerId): void
    {
        $this->selectedPractitioners[$slotIndex] = $practitionerId;
    }

    public function setGroupBy(string $groupBy): void
    {
        $this->groupBy = $groupBy;
    }

    public function getPractitionerColor(string $practitionerId): string
    {
        // Generate consistent color based on practitioner ID
        $colors = [
            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
            'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
            'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-300',
            'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-300',
            'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300',
            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        ];

        $hash = crc32($practitionerId);
        return $colors[$hash % count($colors)];
    }

    public function render()
    {
        return view('booking::livewire.booking.slot-grid', [
            'groupedSlots' => $this->getGroupedSlots(),
            'timeSlots' => $this->getTimeSlots(),
        ]);
    }
}
