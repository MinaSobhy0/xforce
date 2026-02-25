<x-filament-panels::page>
    {{-- Algorithm Flow Visualization --}}
    <div class="mb-6">
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4 overflow-x-auto">
            <div class="flex items-center justify-center gap-2 min-w-[600px]">
                {{-- Step 1: Service --}}
                <div class="flex items-center">
                    <div class="bg-primary-100 dark:bg-primary-900/50 border-2 border-primary-400 rounded-lg px-4 py-2 text-center min-w-[100px]">
                        <x-heroicon-o-clipboard-document-list class="w-6 h-6 mx-auto text-primary-600 dark:text-primary-400" />
                        <div class="text-xs font-medium text-primary-700 dark:text-primary-300 mt-1">{{ __('booking::config.service') }}</div>
                    </div>
                    <x-heroicon-o-arrow-right class="w-6 h-6 text-gray-400 mx-2" />
                </div>

                {{-- Step 2: Time --}}
                <div class="flex items-center">
                    <div class="bg-blue-100 dark:bg-blue-900/50 border-2 border-blue-400 rounded-lg px-4 py-2 text-center min-w-[100px]">
                        <x-heroicon-o-clock class="w-6 h-6 mx-auto text-blue-600 dark:text-blue-400" />
                        <div class="text-xs font-medium text-blue-700 dark:text-blue-300 mt-1">{{ __('booking::config.time') }}</div>
                    </div>
                    <x-heroicon-o-arrow-right class="w-6 h-6 text-gray-400 mx-2" />
                </div>

                {{-- Step 3: Doctor --}}
                <div class="flex items-center">
                    <div class="bg-green-100 dark:bg-green-900/50 border-2 border-green-400 rounded-lg px-4 py-2 text-center min-w-[100px]">
                        <x-heroicon-o-user class="w-6 h-6 mx-auto text-green-600 dark:text-green-400" />
                        <div class="text-xs font-medium text-green-700 dark:text-green-300 mt-1">{{ __('booking::config.practitioners') }}</div>
                    </div>
                    <x-heroicon-o-arrow-right class="w-6 h-6 text-gray-400 mx-2" />
                </div>

                {{-- Step 4: Room --}}
                <div class="flex items-center">
                    <div class="bg-yellow-100 dark:bg-yellow-900/50 border-2 border-yellow-400 rounded-lg px-4 py-2 text-center min-w-[100px]">
                        <x-heroicon-o-building-office class="w-6 h-6 mx-auto text-yellow-600 dark:text-yellow-400" />
                        <div class="text-xs font-medium text-yellow-700 dark:text-yellow-300 mt-1">{{ __('booking::config.room') }}</div>
                    </div>
                    <x-heroicon-o-arrow-right class="w-6 h-6 text-gray-400 mx-2" />
                </div>

                {{-- Step 5: Equipment --}}
                <div class="flex items-center">
                    <div class="bg-purple-100 dark:bg-purple-900/50 border-2 border-purple-400 rounded-lg px-4 py-2 text-center min-w-[100px]">
                        <x-heroicon-o-wrench-screwdriver class="w-6 h-6 mx-auto text-purple-600 dark:text-purple-400" />
                        <div class="text-xs font-medium text-purple-700 dark:text-purple-300 mt-1">{{ __('booking::config.filter_equipment') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Configuration Form --}}
    <form wire:submit.prevent="saveConfiguration">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit" color="primary" icon="heroicon-o-check">
                {{ __('booking::config.save_configuration') }}
            </x-filament::button>
        </div>
    </form>

    {{-- Blackout Calendar Preview --}}
    <div class="mt-8">
        <x-filament::section collapsible>
            <x-slot name="heading">
                {{ __('booking::config.holiday_calendar') }}
            </x-slot>
            <x-slot name="headerEnd">
                <x-filament::button
                    size="sm"
                    color="gray"
                    icon="heroicon-o-plus"
                    tag="a"
                    :href="route('filament.tenant.resources.booking-blackout-dates.create')"
                >
                    {{ __('booking::config.add_blackout') }}
                </x-filament::button>
            </x-slot>

            @if($upcomingBlackouts->isEmpty())
                <div class="text-center py-6 text-gray-500">
                    <x-heroicon-o-calendar class="w-8 h-8 mx-auto mb-2 opacity-50" />
                    <p class="text-sm">{{ __('booking::config.no_upcoming_blackouts') }}</p>
                </div>
            @else
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($upcomingBlackouts as $blackout)
                        <div class="py-3 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-full {{ $blackout->is_current ? 'bg-danger-100 dark:bg-danger-900' : 'bg-warning-100 dark:bg-warning-900' }}">
                                    <x-heroicon-o-calendar class="w-5 h-5 {{ $blackout->is_current ? 'text-danger-600' : 'text-warning-600' }}" />
                                </div>
                                <div>
                                    <div class="font-medium">{{ $blackout->name }}</div>
                                    <div class="text-sm text-gray-500">
                                        {{ $blackout->date_range_display }}
                                        @if($blackout->is_recurring)
                                            <span class="text-xs bg-gray-100 dark:bg-gray-700 rounded px-1 ml-1">
                                                {{ $blackout->recurrence_type_label }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ $blackout->scope_description }}
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 text-center">
                    <x-filament::link
                        :href="route('filament.tenant.resources.booking-blackout-dates.index')"
                        icon="heroicon-o-arrow-right"
                        icon-position="after"
                    >
                        {{ __('booking::config.view_all_blackouts') }}
                    </x-filament::link>
                </div>
            @endif
        </x-filament::section>
    </div>

    {{-- Slot Preview --}}
    <div class="mt-6">
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">
                {{ __('booking::config.slot_preview') }}
            </x-slot>
            <x-slot name="description">
                {{ __('booking::config.slot_preview_desc') }}
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ __('booking::config.select_service') }}
                    </label>
                    <select
                        wire:model="previewServiceId"
                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    >
                        <option value="">{{ __('booking::config.choose_service') }}</option>
                        @foreach($serviceOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ __('booking::config.select_date') }}
                    </label>
                    <input
                        type="date"
                        wire:model="previewDate"
                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        min="{{ now()->format('Y-m-d') }}"
                    />
                </div>
                <div class="flex items-end">
                    <x-filament::button wire:click="generatePreview" class="w-full">
                        {{ __('booking::config.generate_preview') }}
                    </x-filament::button>
                </div>
            </div>

            @if($previewSlots !== null)
                @if(empty($previewSlots))
                    <div class="text-center py-6 text-gray-500">
                        <x-heroicon-o-clock class="w-8 h-8 mx-auto mb-2 opacity-50" />
                        <p class="text-sm">{{ __('booking::config.no_slots_available') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800">
                                    <th class="px-4 py-2 text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}">{{ __('booking::config.time') }}</th>
                                    <th class="px-4 py-2 text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}">{{ __('booking::config.status') }}</th>
                                    <th class="px-4 py-2 text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}">{{ __('booking::config.practitioners') }}</th>
                                    <th class="px-4 py-2 text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}">{{ __('booking::config.room') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($previewSlots as $slot)
                                    <tr>
                                        <td class="px-4 py-2 font-mono">{{ $slot['time'] }}</td>
                                        <td class="px-4 py-2">
                                            @if($slot['available'])
                                                <span class="inline-flex items-center gap-1 text-green-600 dark:text-green-400">
                                                    <x-heroicon-o-check-circle class="w-4 h-4" />
                                                    {{ __('booking::config.available') }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-red-600 dark:text-red-400">
                                                    <x-heroicon-o-x-circle class="w-4 h-4" />
                                                    {{ $slot['blocked_reason'] ?? __('booking::config.blocked') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2">{{ $slot['practitioners'] ?: '-' }}</td>
                                        <td class="px-4 py-2">{{ $slot['room'] ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2 text-xs text-gray-500 text-{{ app()->getLocale() === 'ar' ? 'left' : 'right' }}">
                        {{ __('booking::config.showing_first_20') }}
                    </div>
                @endif
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
