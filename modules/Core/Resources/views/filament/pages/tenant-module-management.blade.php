<x-filament-panels::page>
    {{-- Summary Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <x-filament::section>
            <div class="flex items-center gap-4">
                <div class="p-3 rounded-lg bg-primary-100 dark:bg-primary-900/20">
                    <x-heroicon-o-puzzle-piece class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('core::core.total_modules') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->getTotalModulesCount() }}</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center gap-4">
                <div class="p-3 rounded-lg bg-success-100 dark:bg-success-900/20">
                    <x-heroicon-o-check-circle class="w-6 h-6 text-success-600 dark:text-success-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('core::core.active_modules') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->getActiveModulesCount() }}</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center gap-4">
                <div class="p-3 rounded-lg bg-warning-100 dark:bg-warning-900/20">
                    <x-heroicon-o-information-circle class="w-6 h-6 text-warning-600 dark:text-warning-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('core::core.subscription_plan') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        @if(tenant()?->subscription?->plan)
                            {{ tenant()->subscription->plan->name }}
                        @else
                            {{ __('core::core.no_plan') }}
                        @endif
                    </p>
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Module Categories --}}
    @foreach($this->getModules() as $category => $modules)
        <x-filament::section class="mb-6">
            <x-slot name="heading">
                {{ $category }}
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($modules as $module)
                    <div class="relative p-4 rounded-xl border
                        @if($module['is_core'])
                            bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700
                        @elseif($module['is_active'])
                            bg-success-50 dark:bg-success-900/10 border-success-200 dark:border-success-800
                        @elseif($module['is_included_in_plan'])
                            bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700
                        @else
                            bg-gray-100 dark:bg-gray-900 border-gray-200 dark:border-gray-700 opacity-60
                        @endif
                    ">
                        {{-- Status Badge --}}
                        <div class="absolute top-2 {{ app()->getLocale() === 'ar' ? 'left-2' : 'right-2' }}">
                            @if($module['is_core'])
                                <x-filament::badge color="gray">
                                    {{ __('core::core.core_module') }}
                                </x-filament::badge>
                            @elseif($module['is_active'])
                                <x-filament::badge color="success">
                                    {{ __('core::core.active') }}
                                </x-filament::badge>
                            @elseif($module['is_included_in_plan'])
                                <x-filament::badge color="warning">
                                    {{ __('core::core.inactive') }}
                                </x-filament::badge>
                            @else
                                <x-filament::badge color="danger">
                                    {{ __('core::core.not_in_plan') }}
                                </x-filament::badge>
                            @endif
                        </div>

                        {{-- Module Info --}}
                        <div class="flex items-start gap-3 mt-6">
                            <div class="flex-shrink-0 p-2 rounded-lg
                                @if($module['is_active'])
                                    bg-success-100 dark:bg-success-900/20
                                @else
                                    bg-gray-100 dark:bg-gray-800
                                @endif
                            ">
                                <x-dynamic-component
                                    :component="$module['icon']"
                                    class="w-6 h-6
                                        @if($module['is_active'])
                                            text-success-600 dark:text-success-400
                                        @else
                                            text-gray-500 dark:text-gray-400
                                        @endif
                                    "
                                />
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                    {{ $module['name'] }}
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    {{ $module['description'] }}
                                </p>

                                @if(!empty($module['dependencies']))
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        <span class="text-xs text-gray-400">{{ __('core::core.requires') }}:</span>
                                        @foreach($module['dependencies'] as $dep)
                                            <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                                                {{ $dep }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endforeach

    {{-- Info Note --}}
    <x-filament::section class="mt-6">
        <div class="flex items-start gap-4">
            <x-heroicon-o-information-circle class="w-6 h-6 text-primary-500 flex-shrink-0 mt-0.5" />
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('core::core.module_management_info') }}
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-500 mt-1">
                    {{ __('core::core.contact_support_for_modules') }}
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
