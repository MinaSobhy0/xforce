<x-filament-panels::page>
    {{-- Date Filter --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-2">
            {{-- Previous Day Button --}}
            <x-filament::icon-button
                icon="heroicon-o-chevron-left"
                wire:click="previousDay"
                :label="__('booking::reception.filters.previous_day')"
                color="gray"
            />

            {{-- Date Picker --}}
            <div class="w-48">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="date"
                        wire:model.live="selectedDate"
                        class="text-center"
                    />
                </x-filament::input.wrapper>
            </div>

            {{-- Next Day Button --}}
            <x-filament::icon-button
                icon="heroicon-o-chevron-right"
                wire:click="nextDay"
                :label="__('booking::reception.filters.next_day')"
                color="gray"
            />

            {{-- Today Button --}}
            @unless($this->isToday())
                <x-filament::button
                    wire:click="goToToday"
                    color="primary"
                    size="sm"
                >
                    {{ __('booking::reception.filters.today') }}
                </x-filament::button>
            @endunless
        </div>

        {{-- Date Display --}}
        <div class="text-sm text-gray-500 dark:text-gray-400">
            @if($this->isToday())
                <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-400">
                    <x-heroicon-s-clock class="w-3 h-3" />
                    {{ __('booking::reception.filters.viewing_today') }}
                </span>
            @else
                <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                    <x-heroicon-o-calendar class="w-3 h-3" />
                    {{ __('booking::reception.filters.viewing_date') }}
                </span>
            @endif
        </div>
    </div>

    {{-- Patient Flow - Full Width Columns --}}
    @livewire(\Modules\Booking\Filament\Widgets\PatientFlowWidget::class, ['selectedDate' => $selectedDate])
</x-filament-panels::page>
