<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Content - Appointments Table --}}
        <div class="lg:col-span-2">
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('booking::reception.sections.appointments') }}
                </x-slot>

                {{ $this->table }}
            </x-filament::section>
        </div>

        {{-- Sidebar - Patient Flow --}}
        <div class="lg:col-span-1">
            @livewire(\Modules\Booking\Filament\Widgets\PatientFlowWidget::class)
        </div>
    </div>
</x-filament-panels::page>
