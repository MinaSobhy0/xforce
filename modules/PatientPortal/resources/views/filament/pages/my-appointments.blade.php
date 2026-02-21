<x-filament-panels::page>
    <div class="flex justify-end mb-4">
        <x-filament::button
            :href="\Modules\PatientPortal\Filament\Pages\BookAppointment::getUrl()"
            tag="a"
            icon="heroicon-o-plus"
        >
            {{ __('patientportal::portal.book_new') }}
        </x-filament::button>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
