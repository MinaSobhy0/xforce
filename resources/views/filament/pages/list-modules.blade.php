<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $modules = $this->getModules();
        @endphp

        @if(empty($modules))
            <div class="fi-ta-empty-state flex flex-col items-center justify-center py-12">
                <div class="fi-ta-empty-state-icon">
                    <x-heroicon-o-puzzle-piece class="h-16 w-16 text-gray-400" />
                </div>
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ __('No modules found') }}
                </h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Module registry is not available or no modules are registered.') }}
                </p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach($modules as $module)
                    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="fi-section-content p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-lg font-semibold text-gray-950 dark:text-white">
                                            {{ $module['name'] }}
                                        </h3>
                                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $module['enabled'] ? 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-400 dark:ring-green-500/20' : 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20' }}">
                                            {{ $module['enabled'] ? __('Enabled') : __('Disabled') }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $module['code'] }}
                                    </p>
                                </div>
                                <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/20">
                                    v{{ $module['version'] }}
                                </span>
                            </div>

                            @if($module['description'])
                                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $module['description'] }}
                                </p>
                            @endif

                            @if($module['author'])
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                                    {{ __('By') }} {{ $module['author'] }}
                                </p>
                            @endif

                            <div class="mt-4 flex items-center gap-2">
                                <button
                                    type="button"
                                    wire:click="toggleModule('{{ $module['code'] }}')"
                                    class="inline-flex items-center justify-center gap-1 rounded-lg px-3 py-2 text-sm font-semibold outline-none transition duration-75 focus-visible:ring-2 {{ $module['enabled'] ? 'bg-warning-600 text-white hover:bg-warning-500 focus-visible:ring-warning-500/50 dark:bg-warning-500 dark:hover:bg-warning-400' : 'bg-success-600 text-white hover:bg-success-500 focus-visible:ring-success-500/50 dark:bg-success-500 dark:hover:bg-success-400' }}"
                                >
                                    @if($module['enabled'])
                                        <x-heroicon-m-pause class="h-4 w-4" />
                                        {{ __('Disable') }}
                                    @else
                                        <x-heroicon-m-play class="h-4 w-4" />
                                        {{ __('Enable') }}
                                    @endif
                                </button>

                                <a
                                    href="{{ \Modules\Core\Resources\ModuleManagementResource::getUrl('view', ['record' => $module['code']]) }}"
                                    class="inline-flex items-center justify-center gap-1 rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-700 outline-none transition duration-75 hover:bg-gray-200 focus-visible:ring-2 focus-visible:ring-primary-500/50 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                >
                                    <x-heroicon-m-eye class="h-4 w-4" />
                                    {{ __('View') }}
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
