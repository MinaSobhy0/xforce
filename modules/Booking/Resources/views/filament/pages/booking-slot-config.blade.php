<x-filament-panels::page>
    {{-- Algorithm Visualization Panel --}}
    <div class="mb-6">
        <x-filament::section collapsible>
            <x-slot name="heading">
                {{ __('booking::config.how_slots_generated') }}
            </x-slot>
            <x-slot name="description">
                {{ __('booking::config.algorithm_description') }}
            </x-slot>

            <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4 overflow-x-auto">
                <div class="min-w-[600px]">
                    {{-- Flowchart visualization --}}
                    <div class="flex flex-col items-center space-y-2 text-sm">
                        {{-- Step 1: Service --}}
                        <div class="bg-primary-100 dark:bg-primary-900/50 border border-primary-300 dark:border-primary-700 rounded-lg px-4 py-2 text-center">
                            <div class="font-semibold text-primary-700 dark:text-primary-300">{{ __('booking::config.step_service') }}</div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">{{ __('booking::config.step_service_desc') }}</div>
                        </div>

                        <x-heroicon-o-arrow-down class="w-5 h-5 text-gray-400" />

                        {{-- Step 2: Date Validation --}}
                        <div class="bg-yellow-100 dark:bg-yellow-900/50 border border-yellow-300 dark:border-yellow-700 rounded-lg px-4 py-2 text-center">
                            <div class="font-semibold text-yellow-700 dark:text-yellow-300">{{ __('booking::config.step_date') }}</div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">{{ __('booking::config.step_date_desc') }}</div>
                        </div>

                        <x-heroicon-o-arrow-down class="w-5 h-5 text-gray-400" />

                        {{-- Step 3: Time Window --}}
                        <div class="bg-blue-100 dark:bg-blue-900/50 border border-blue-300 dark:border-blue-700 rounded-lg px-4 py-2 text-center">
                            <div class="font-semibold text-blue-700 dark:text-blue-300">{{ __('booking::config.step_time_window') }}</div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">{{ __('booking::config.step_time_window_desc') }}</div>
                        </div>

                        <x-heroicon-o-arrow-down class="w-5 h-5 text-gray-400" />

                        {{-- Step 4: Generate Intervals --}}
                        <div class="bg-green-100 dark:bg-green-900/50 border border-green-300 dark:border-green-700 rounded-lg px-4 py-2 text-center">
                            <div class="font-semibold text-green-700 dark:text-green-300">{{ __('booking::config.step_intervals') }}</div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">{{ __('booking::config.step_intervals_desc') }}</div>
                        </div>

                        <x-heroicon-o-arrow-down class="w-5 h-5 text-gray-400" />

                        {{-- Step 5: Filters --}}
                        <div class="bg-purple-100 dark:bg-purple-900/50 border border-purple-300 dark:border-purple-700 rounded-lg px-4 py-3 w-full max-w-md">
                            <div class="font-semibold text-purple-700 dark:text-purple-300 text-center mb-2">{{ __('booking::config.availability_filters') }}</div>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="flex items-center gap-1">
                                    <x-heroicon-o-check-circle class="w-4 h-4 text-green-500" />
                                    <span>{{ __('booking::config.filter_schedule') }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <x-heroicon-o-check-circle class="w-4 h-4 text-green-500" />
                                    <span>{{ __('booking::config.filter_time_off') }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <x-heroicon-o-check-circle class="w-4 h-4 text-green-500" />
                                    <span>{{ __('booking::config.filter_conflicts') }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <x-heroicon-o-check-circle class="w-4 h-4 text-green-500" />
                                    <span>{{ __('booking::config.filter_rooms') }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <x-heroicon-o-check-circle class="w-4 h-4 text-green-500" />
                                    <span>{{ __('booking::config.filter_equipment') }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <x-heroicon-o-check-circle class="w-4 h-4 text-green-500" />
                                    <span>{{ __('booking::config.filter_rules') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Current Configuration Summary (derived from rules) --}}
    <div class="mb-6">
        <x-filament::section>
            <x-slot name="heading">
                {{ __('booking::config.current_configuration') }}
            </x-slot>
            <x-slot name="description">
                {{ __('booking::config.current_configuration_desc') }}
            </x-slot>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                {{-- Slot Duration --}}
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold text-primary-600 dark:text-primary-400">
                        {{ $currentConfig['slot_duration'] }}
                    </div>
                    <div class="text-sm text-gray-500">{{ __('booking::config.minutes') }}</div>
                    <div class="text-xs text-gray-400 mt-1">{{ __('booking::config.default_slot_duration') }}</div>
                </div>

                {{-- Buffer --}}
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                        {{ $currentConfig['buffer_minutes'] }}
                    </div>
                    <div class="text-sm text-gray-500">{{ __('booking::config.minutes') }}</div>
                    <div class="text-xs text-gray-400 mt-1">{{ __('booking::config.buffer_between_appointments') }}</div>
                </div>

                {{-- Working Hours --}}
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ $currentConfig['working_hours']['start'] }} - {{ $currentConfig['working_hours']['end'] }}
                    </div>
                    <div class="text-xs text-gray-400 mt-1">{{ __('booking::config.working_hours') }}</div>
                </div>

                {{-- Advance Booking --}}
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                        {{ $currentConfig['min_advance_hours'] }}h - {{ $currentConfig['max_advance_days'] }}d
                    </div>
                    <div class="text-xs text-gray-400 mt-1">{{ __('booking::config.advance_booking') }}</div>
                </div>
            </div>

            {{-- Feature Flags --}}
            <div class="mt-4 flex flex-wrap gap-2">
                @if($currentConfig['online_booking'])
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">
                        <x-heroicon-o-check-circle class="w-3 h-3" />
                        {{ __('booking::config.enable_online_booking') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300">
                        <x-heroicon-o-x-circle class="w-3 h-3" />
                        {{ __('booking::config.online_booking') }}
                    </span>
                @endif

                @if($currentConfig['allow_same_day'])
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">
                        <x-heroicon-o-check-circle class="w-3 h-3" />
                        {{ __('booking::config.allow_same_day') }}
                    </span>
                @endif

                @if($currentConfig['auto_confirm'])
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                        <x-heroicon-o-check-circle class="w-3 h-3" />
                        {{ __('booking::config.auto_confirm') }}
                    </span>
                @endif

                @if($currentConfig['practitioner_selection'])
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300">
                        <x-heroicon-o-check-circle class="w-3 h-3" />
                        {{ __('booking::config.show_practitioner_selection') }}
                    </span>
                @endif

                @if($currentConfig['deposit_required'])
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300">
                        <x-heroicon-o-banknotes class="w-3 h-3" />
                        {{ __('booking::config.require_deposit') }}: {{ $currentConfig['deposit_percentage'] }}%
                    </span>
                @endif
            </div>

            <div class="mt-4 text-xs text-gray-500">
                <x-heroicon-o-information-circle class="w-4 h-4 inline" />
                {{ __('booking::config.config_from_rules_note') }}
            </div>
        </x-filament::section>
    </div>

    {{-- Quick Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-primary-100 dark:bg-primary-900">
                    <x-heroicon-o-document-text class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <div class="text-2xl font-bold">{{ $activeRulesCount }}</div>
                    <div class="text-sm text-gray-500">{{ __('booking::config.active_rules') }}</div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-danger-100 dark:bg-danger-900">
                    <x-heroicon-o-calendar-days class="w-6 h-6 text-danger-600 dark:text-danger-400" />
                </div>
                <div>
                    <div class="text-2xl font-bold">{{ $blackoutDatesCount }}</div>
                    <div class="text-sm text-gray-500">{{ __('booking::config.upcoming_blackouts') }}</div>
                </div>
            </div>
        </x-filament::section>

        {{-- Rules by Category --}}
        <x-filament::section class="md:col-span-2">
            <div class="text-sm font-medium mb-2">{{ __('booking::config.rules_by_category') }}</div>
            <div class="flex flex-wrap gap-2">
                @foreach(\Modules\Booking\Models\BookingRule::RULE_CATEGORIES as $key => $category)
                    @php $count = $rulesCountByCategory[$key] ?? 0; @endphp
                    @if($count > 0)
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-{{ $category['color'] ?? 'gray' }}-100 text-{{ $category['color'] ?? 'gray' }}-700 dark:bg-{{ $category['color'] ?? 'gray' }}-900 dark:text-{{ $category['color'] ?? 'gray' }}-300">
                            {{ $category['label'] }}: {{ $count }}
                        </span>
                    @endif
                @endforeach
                @if(empty(array_filter($rulesCountByCategory)))
                    <span class="text-gray-500 text-xs">{{ __('booking::config.no_rules') }}</span>
                @endif
            </div>
        </x-filament::section>
    </div>

    {{-- Rules Table --}}
    <div class="mb-8">
        <x-filament::section>
            <x-slot name="heading">
                {{ __('booking::config.booking_rules') }}
            </x-slot>
            <x-slot name="description">
                {{ __('booking::config.rules_description') }}
            </x-slot>

            {{ $this->table }}
        </x-filament::section>
    </div>

    {{-- Blackout Calendar Preview --}}
    <div class="mb-8">
        <x-filament::section>
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
                <div class="text-center py-8 text-gray-500">
                    <x-heroicon-o-calendar class="w-12 h-12 mx-auto mb-2 opacity-50" />
                    <p>{{ __('booking::config.no_upcoming_blackouts') }}</p>
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
    <div>
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
                    <div class="text-center py-8 text-gray-500">
                        <x-heroicon-o-clock class="w-12 h-12 mx-auto mb-2 opacity-50" />
                        <p>{{ __('booking::config.no_slots_available') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800">
                                    <th class="px-4 py-2 text-left">{{ __('booking::config.time') }}</th>
                                    <th class="px-4 py-2 text-left">{{ __('booking::config.status') }}</th>
                                    <th class="px-4 py-2 text-left">{{ __('booking::config.practitioners') }}</th>
                                    <th class="px-4 py-2 text-left">{{ __('booking::config.room') }}</th>
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
                    <div class="mt-2 text-xs text-gray-500 text-right">
                        {{ __('booking::config.showing_first_20') }}
                    </div>
                @endif
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
