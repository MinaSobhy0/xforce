<x-filament-panels::page>
    {{-- Main Form --}}
    <form wire:submit.prevent="createBookings">
        {{ $this->form }}
    </form>

    {{-- Slot Grid (hidden in quick book mode) --}}
    @if(!empty($this->availableSlots) && !$this->isQuickBook)
        <div class="mt-6">
            @include('booking::components.inline-slot-grid', [
                'slots' => $this->availableSlots,
                'selectedSlots' => $this->getSelectedSlotKeys(),
            ])
        </div>
    @endif

    {{-- Quick Book Confirm Button --}}
    @if($this->isQuickBook)
        @php
            $services = $this->data['services'] ?? [];
            $hasValidService = collect($services)->filter(fn($s) => !empty($s['service_id']))->isNotEmpty();
            $hasPatient = !empty($this->data['patient_id']);
        @endphp
        @if($hasValidService && $hasPatient)
            <div class="h-24"></div>
            <div class="fixed bottom-0 left-0 right-0 z-[100] border-t border-gray-200 bg-white px-6 py-4 shadow-[0_-8px_20px_-4px_rgba(0,0,0,0.15)] dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between gap-4">
                    {{-- Left: Quick book info --}}
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-900/50 dark:text-amber-400">
                                <x-heroicon-o-bolt class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ __('booking::booking.quick_book') }}
                                </p>
                                <p class="text-xs text-amber-600 dark:text-amber-400">
                                    {{ __('booking::booking.needs_scheduling') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Right: Actions --}}
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            wire:click="createBookings"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-75"
                            class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50"
                        >
                            <x-heroicon-o-check class="h-5 w-5" />
                            {{ __('booking::booking.actions.confirm_booking') }}
                            <span wire:loading wire:target="createBookings" class="ml-1">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- Bottom Summary Bar (regular booking mode) --}}
    @if(!empty($this->bookingItems) && !$this->isQuickBook)
        <div class="h-24"></div>
        @include('booking::components.booking-summary-bar', ['items' => $this->bookingItems])
    @endif
</x-filament-panels::page>
