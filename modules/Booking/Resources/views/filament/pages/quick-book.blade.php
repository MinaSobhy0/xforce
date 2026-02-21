<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Selection Form --}}
        <x-filament::section>
            {{ $this->form }}
        </x-filament::section>

        {{-- Available Slots Grid --}}
        @if($this->treatment_id && $this->branch_id && $this->selected_date)
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('booking::appointments.available_slots') }} - {{ $this->getFormattedDate() }}
                </x-slot>

                @if(count($this->availableSlots) > 0)
                    <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 gap-2">
                        @foreach($this->availableSlots as $slot)
                            <button
                                type="button"
                                wire:click="selectSlot('{{ $slot['start'] }}', '{{ $slot['end'] }}', '{{ $slot['practitioner_id'] ?? '' }}', '{{ $slot['equipment_id'] ?? '' }}', '{{ $slot['room_id'] ?? '' }}')"
                                class="flex flex-col items-center justify-center p-3 rounded-lg border-2 transition-all duration-200 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary-500
                                    {{ $this->selected_slot_start === $slot['start'] && $this->selected_slot_practitioner_id === ($slot['practitioner_id'] ?? '')
                                        ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                                        : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-primary-300' }}"
                            >
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $slot['start'] }}
                                </span>
                                @if(isset($slot['practitioner_name']) && ($this->practitioner_id === 'any' || empty($this->practitioner_id)))
                                    <span class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate max-w-full">
                                        {{ \Illuminate\Support\Str::limit($slot['practitioner_name'], 12) }}
                                    </span>
                                @endif
                                @if(isset($slot['equipment_name']))
                                    <span class="text-xs text-primary-600 dark:text-primary-400 mt-0.5 truncate max-w-full">
                                        {{ \Illuminate\Support\Str::limit($slot['equipment_name'], 10) }}
                                    </span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <x-heroicon-o-calendar class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ __('booking::appointments.no_slots_available') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('booking::appointments.no_slots_available_hint') }}
                        </p>
                    </div>
                @endif
            </x-filament::section>
        @endif

        {{-- Booking Summary --}}
        @if($this->selected_slot_start)
            @php
                $slotInfo = $this->getSelectedSlotInfo();
            @endphp
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('booking::appointments.booking_summary') }}
                </x-slot>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div class="flex items-center space-x-3 rtl:space-x-reverse">
                            <x-heroicon-o-clock class="h-5 w-5 text-gray-400" />
                            <div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::appointments.fields.time') }}</span>
                                <p class="font-semibold text-gray-900 dark:text-white">
                                    {{ $slotInfo['start'] }} - {{ $slotInfo['end'] }}
                                    <span class="text-sm text-gray-500">({{ $slotInfo['duration'] }} {{ __('booking::appointments.minutes') }})</span>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3 rtl:space-x-reverse">
                            <x-heroicon-o-calendar class="h-5 w-5 text-gray-400" />
                            <div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::appointments.fields.date') }}</span>
                                <p class="font-semibold text-gray-900 dark:text-white">{{ $this->getFormattedDate() }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center space-x-3 rtl:space-x-reverse">
                            <x-heroicon-o-user class="h-5 w-5 text-gray-400" />
                            <div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::appointments.fields.practitioner') }}</span>
                                <p class="font-semibold text-gray-900 dark:text-white">{{ $slotInfo['practitioner_name'] }}</p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3 rtl:space-x-reverse">
                            <x-heroicon-o-beaker class="h-5 w-5 text-gray-400" />
                            <div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('booking::appointments.fields.treatment') }}</span>
                                <p class="font-semibold text-gray-900 dark:text-white">{{ $slotInfo['treatment_name'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <x-filament::button
                        wire:click="bookAppointment"
                        size="lg"
                        icon="heroicon-o-check-circle"
                    >
                        {{ __('booking::appointments.book_now') }}
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif
    </div>

    @push('styles')
        <style>
            .slot-button-selected {
                box-shadow: 0 0 0 2px var(--primary-500);
            }
        </style>
    @endpush
</x-filament-panels::page>
