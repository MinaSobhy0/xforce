<div
    x-data="{ theme: @entangle('theme') }"
    x-init="
        $watch('theme', (value) => {
            localStorage.setItem('theme', value);

            if (value === 'dark' || (value === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })
    "
    class="fi-theme-switcher grid grid-flow-col gap-x-1"
>
    <button
        aria-label="{{ __('filament-panels::layout.actions.theme_switcher.light.label') }}"
        type="button"
        wire:click="setTheme('light')"
        x-tooltip="{
            content: '{{ __('filament-panels::layout.actions.theme_switcher.light.label') }}',
            theme: $store.theme,
        }"
        class="fi-theme-switcher-btn flex justify-center rounded-md p-2 outline-none transition duration-75 hover:bg-gray-50 focus-visible:bg-gray-50 dark:hover:bg-white/5 dark:focus-visible:bg-white/5"
        :class="theme === 'light'
            ? 'fi-active bg-gray-50 text-primary-500 dark:bg-white/5 dark:text-primary-400'
            : 'text-gray-400 hover:text-gray-500 focus-visible:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400 dark:focus-visible:text-gray-400'"
    >
        <x-filament::icon
            alias="panels::theme-switcher.light-button"
            icon="heroicon-m-sun"
            class="h-5 w-5"
        />
    </button>

    <button
        aria-label="{{ __('filament-panels::layout.actions.theme_switcher.dark.label') }}"
        type="button"
        wire:click="setTheme('dark')"
        x-tooltip="{
            content: '{{ __('filament-panels::layout.actions.theme_switcher.dark.label') }}',
            theme: $store.theme,
        }"
        class="fi-theme-switcher-btn flex justify-center rounded-md p-2 outline-none transition duration-75 hover:bg-gray-50 focus-visible:bg-gray-50 dark:hover:bg-white/5 dark:focus-visible:bg-white/5"
        :class="theme === 'dark'
            ? 'fi-active bg-gray-50 text-primary-500 dark:bg-white/5 dark:text-primary-400'
            : 'text-gray-400 hover:text-gray-500 focus-visible:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400 dark:focus-visible:text-gray-400'"
    >
        <x-filament::icon
            alias="panels::theme-switcher.dark-button"
            icon="heroicon-m-moon"
            class="h-5 w-5"
        />
    </button>

    <button
        aria-label="{{ __('filament-panels::layout.actions.theme_switcher.system.label') }}"
        type="button"
        wire:click="setTheme('system')"
        x-tooltip="{
            content: '{{ __('filament-panels::layout.actions.theme_switcher.system.label') }}',
            theme: $store.theme,
        }"
        class="fi-theme-switcher-btn flex justify-center rounded-md p-2 outline-none transition duration-75 hover:bg-gray-50 focus-visible:bg-gray-50 dark:hover:bg-white/5 dark:focus-visible:bg-white/5"
        :class="theme === 'system'
            ? 'fi-active bg-gray-50 text-primary-500 dark:bg-white/5 dark:text-primary-400'
            : 'text-gray-400 hover:text-gray-500 focus-visible:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400 dark:focus-visible:text-gray-400'"
    >
        <x-filament::icon
            alias="panels::theme-switcher.system-button"
            icon="heroicon-m-computer-desktop"
            class="h-5 w-5"
        />
    </button>
</div>
