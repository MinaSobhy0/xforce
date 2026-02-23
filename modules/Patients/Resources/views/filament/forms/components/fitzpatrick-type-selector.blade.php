<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}'),
            types: @js($getTypes()),
            selectType(type) {
                this.state = type;
            },
            isSelected(type) {
                return this.state === type;
            }
        }"
        class="fitzpatrick-selector"
    >
        <div class="flex justify-between gap-2">
            @foreach ($getTypes() as $typeKey => $type)
                <button
                    type="button"
                    x-on:click="selectType('{{ $typeKey }}')"
                    :class="{
                        'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-gray-900': isSelected('{{ $typeKey }}'),
                        'hover:ring-1 hover:ring-gray-300 dark:hover:ring-gray-600': !isSelected('{{ $typeKey }}')
                    }"
                    class="relative flex flex-col items-center p-2 rounded-lg border border-gray-200 dark:border-gray-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 flex-1"
                    title="{{ $type['description'] }}"
                >
                    {{-- Skin tone circle --}}
                    <div
                        class="w-8 h-8 sm:w-10 sm:h-10 rounded-full shadow-inner mb-1 border-2 border-white dark:border-gray-800"
                        style="background-color: {{ $type['color'] }};"
                    ></div>

                    {{-- Type label --}}
                    <span class="text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ $typeKey }}
                    </span>

                    {{-- Selected checkmark --}}
                    <div
                        x-show="isSelected('{{ $typeKey }}')"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-75"
                        x-transition:enter-end="opacity-100 scale-100"
                        class="absolute -top-1 -right-1 w-5 h-5 bg-primary-500 rounded-full flex items-center justify-center"
                    >
                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                </button>
            @endforeach
        </div>

        {{-- Selected type details --}}
        <div
            x-show="state"
            x-transition
            class="mt-4 p-4 rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700"
        >
            <template x-for="(type, key) in types" :key="key">
                <div x-show="state === key" class="space-y-2">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-8 h-8 rounded-full shadow-inner border border-white dark:border-gray-800"
                            :style="`background-color: ${type.color};`"
                        ></div>
                        <div>
                            <h4 class="font-semibold text-gray-900 dark:text-white" x-text="type.label"></h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400" x-text="type.characteristics"></p>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        <span class="font-medium">{{ __('patients::patients.medical.sun_response') }}:</span>
                        <span x-text="type.sun_response"></span>
                    </p>
                </div>
            </template>
        </div>
    </div>
</x-dynamic-component>
