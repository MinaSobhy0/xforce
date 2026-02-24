<div class="fi-language-switcher grid grid-flow-col gap-x-1">
    @foreach($this->getLocales() as $code => $localeData)
        <button
            wire:click="setLocale('{{ $code }}')"
            type="button"
            title="{{ $localeData['label'] }}"
            @class([
                'fi-language-switcher-btn flex justify-center items-center rounded-md p-2 outline-none transition duration-75 hover:bg-gray-50 focus-visible:bg-gray-50 dark:hover:bg-white/5 dark:focus-visible:bg-white/5',
                'fi-active bg-gray-50 text-primary-500 dark:bg-white/5 dark:text-primary-400' => $locale === $code,
                'text-gray-400 hover:text-gray-500 focus-visible:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400 dark:focus-visible:text-gray-400' => $locale !== $code,
            ])
        >
            <span class="text-sm font-medium">{{ $localeData['code'] }}</span>
        </button>
    @endforeach
</div>
