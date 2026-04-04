<div
    x-data="{ open: $wire.entangle('showDropdown') }"
    @click.outside="open = false"
    class="relative"
>
    {{-- Help Button --}}
    <button
        type="button"
        @click="open = !open"
        class="fi-topbar-item-button flex items-center justify-center rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200"
        :class="{ 'bg-gray-100 dark:bg-white/5': open }"
        title="{{ __('knowledgebase::knowledgebase.help') }}"
    >
        <x-heroicon-o-question-mark-circle class="h-5 w-5" />

        @if($articlesCount > 0 || $hasGuide)
            <span class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-primary-500 text-[10px] font-medium text-white">
                {{ $articlesCount > 9 ? '9+' : ($hasGuide ? '!' : $articlesCount) }}
            </span>
        @endif
    </button>

    {{-- Dropdown Menu --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute right-0 z-50 mt-2 w-64 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-gray-900/5 dark:bg-gray-900 dark:ring-white/10"
        style="display: none;"
    >
        <div class="p-2">
            {{-- Header --}}
            <div class="px-3 py-2 border-b border-gray-100 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                    {{ __('knowledgebase::knowledgebase.help_center') }}
                </h3>
                @if($screenKey)
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ __('knowledgebase::knowledgebase.help_for_screen', ['screen' => $screenKey]) }}
                    </p>
                @endif
            </div>

            {{-- Menu Items --}}
            <div class="py-1">
                @if($hasGuide)
                    <button
                        wire:click="startGuide"
                        class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5"
                    >
                        <x-heroicon-o-play-circle class="h-5 w-5 text-primary-500" />
                        <span>{{ __('knowledgebase::knowledgebase.start_guide') }}</span>
                    </button>
                @endif

                @if($articlesCount > 0)
                    <button
                        wire:click="openHelp"
                        class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5"
                    >
                        <x-heroicon-o-document-text class="h-5 w-5 text-gray-400" />
                        <span>{{ __('knowledgebase::knowledgebase.view_help_articles') }}</span>
                        <span class="ml-auto text-xs text-gray-400">{{ $articlesCount }}</span>
                    </button>
                @endif

                <button
                    wire:click="openKnowledgeBase"
                    class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5"
                >
                    <x-heroicon-o-book-open class="h-5 w-5 text-gray-400" />
                    <span>{{ __('knowledgebase::knowledgebase.browse_knowledge_base') }}</span>
                </button>
            </div>

            {{-- Footer --}}
            @if(!$hasGuide && $articlesCount === 0)
                <div class="px-3 py-2 border-t border-gray-100 dark:border-gray-800">
                    <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
                        {{ __('knowledgebase::knowledgebase.no_help_for_screen') }}
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>
