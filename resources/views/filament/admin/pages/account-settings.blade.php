<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Profile Section --}}
        <x-filament::section>
            <x-slot name="heading">Profile Information</x-slot>
            <x-slot name="description">Update your account's profile information.</x-slot>

            <form wire:submit="updateProfile">
                {{ $this->profileForm }}

                <div class="mt-6">
                    <x-filament::button type="submit">
                        Save Profile
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- Password Section --}}
        <x-filament::section>
            <x-slot name="heading">Update Password</x-slot>
            <x-slot name="description">Ensure your account is using a secure password.</x-slot>

            <form wire:submit="updatePassword">
                {{ $this->passwordForm }}

                <div class="mt-6">
                    <x-filament::button type="submit">
                        Update Password
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- Clinic Info Section (Read Only) --}}
        @php
            $tenant = auth()->user()->tenant;
        @endphp

        @if($tenant)
            <x-filament::section>
                <x-slot name="heading">Clinic Information</x-slot>
                <x-slot name="description">Your clinic details. Contact support to make changes.</x-slot>

                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Clinic Name</dt>
                        <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $tenant->name }}</dd>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Subdomain</dt>
                        <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $tenant->slug }}.x-linic.com</dd>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Country</dt>
                        <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $tenant->country ?? '-' }}</dd>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Timezone</dt>
                        <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $tenant->timezone ?? '-' }}</dd>
                    </div>
                </dl>

                <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        Need to update your clinic information?
                        <a href="{{ route('filament.admin.resources.my-support-tickets.create') }}" class="underline font-medium">Contact support</a>
                    </p>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
