<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Branch Selector --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('attendance::attendance.settings.scope') }}
            </x-slot>
            <x-slot name="description">
                {{ __('attendance::attendance.settings.scope_description') }}
            </x-slot>

            {{ $this->branchForm }}
        </x-filament::section>

        {{-- Manual Check-in --}}
        <x-filament::section
            icon="heroicon-o-hand-raised"
            icon-color="gray"
            collapsible
        >
            <x-slot name="heading">
                {{ __('attendance::attendance.settings.manual.title') }}
            </x-slot>
            <x-slot name="description">
                {{ __('attendance::attendance.settings.manual.description') }}
            </x-slot>

            <form wire:submit.prevent="saveManual">
                {{ $this->manualForm }}

                <div class="mt-4 flex justify-end">
                    <x-filament::button type="submit">
                        {{ __('attendance::attendance.settings.save') }}
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- Geofence Settings --}}
        <x-filament::section
            icon="heroicon-o-map-pin"
            icon-color="info"
            collapsible
        >
            <x-slot name="heading">
                {{ __('attendance::attendance.settings.geofence.title') }}
            </x-slot>
            <x-slot name="description">
                {{ __('attendance::attendance.settings.geofence.description') }}
            </x-slot>

            <form wire:submit.prevent="saveGeofence">
                {{ $this->geofenceForm }}

                <div class="mt-4 flex justify-end">
                    <x-filament::button type="submit">
                        {{ __('attendance::attendance.settings.save') }}
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- Static QR Settings --}}
        <x-filament::section
            icon="heroicon-o-qr-code"
            icon-color="primary"
            collapsible
        >
            <x-slot name="heading">
                {{ __('attendance::attendance.settings.qr_static.title') }}
            </x-slot>
            <x-slot name="description">
                {{ __('attendance::attendance.settings.qr_static.description') }}
            </x-slot>

            <form wire:submit.prevent="saveQrStatic">
                {{ $this->qrStaticForm }}

                <div class="mt-4 flex justify-end gap-2">
                    <x-filament::button
                        type="button"
                        color="gray"
                        wire:click="regenerateQrStatic"
                    >
                        {{ __('attendance::attendance.settings.qr_static.regenerate') }}
                    </x-filament::button>
                    <x-filament::button type="submit">
                        {{ __('attendance::attendance.settings.save') }}
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- Dynamic QR Settings --}}
        <x-filament::section
            icon="heroicon-o-arrow-path"
            icon-color="primary"
            collapsible
        >
            <x-slot name="heading">
                {{ __('attendance::attendance.settings.qr_dynamic.title') }}
            </x-slot>
            <x-slot name="description">
                {{ __('attendance::attendance.settings.qr_dynamic.description') }}
            </x-slot>

            <form wire:submit.prevent="saveQrDynamic">
                {{ $this->qrDynamicForm }}

                <div class="mt-4 flex justify-end gap-2">
                    <x-filament::button
                        type="button"
                        color="gray"
                        wire:click="regenerateQrDynamicSecret"
                    >
                        {{ __('attendance::attendance.settings.qr_dynamic.regenerate_secret') }}
                    </x-filament::button>
                    <x-filament::button type="submit">
                        {{ __('attendance::attendance.settings.save') }}
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- Biometric Settings --}}
        <x-filament::section
            icon="heroicon-o-finger-print"
            icon-color="success"
            collapsible
        >
            <x-slot name="heading">
                {{ __('attendance::attendance.settings.biometric.title') }}
            </x-slot>
            <x-slot name="description">
                {{ __('attendance::attendance.settings.biometric.description') }}
            </x-slot>

            <form wire:submit.prevent="saveBiometric">
                {{ $this->biometricForm }}

                <div class="mt-4 flex justify-end">
                    <x-filament::button type="submit">
                        {{ __('attendance::attendance.settings.save') }}
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    </div>
</x-filament-panels::page>
