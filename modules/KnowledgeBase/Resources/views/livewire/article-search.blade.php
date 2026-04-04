<div class="relative">
    {{-- Search Input --}}
    <div class="relative">
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
            <x-heroicon-o-magnifying-glass class="h-5 w-5 text-gray-400" />
        </div>
        <input
            type="search"
            wire:model.live.debounce.300ms="query"
            wire:keydown.enter="search"
            placeholder="{{ __('knowledgebase::knowledgebase.search_placeholder') }}"
            class="block w-full rounded-lg border-gray-300 py-3 pl-11 pr-10 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-400"
        />
        @if($query)
            <button
                wire:click="clear"
                class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
            >
                <x-heroicon-o-x-circle class="h-5 w-5" />
            </button>
        @endif
    </div>

    {{-- Suggestions Dropdown --}}
    @if($showSuggestions && count($suggestions) > 0)
        <div class="absolute z-10 mt-1 w-full rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 dark:bg-gray-800">
            <ul class="py-1">
                @foreach($suggestions as $suggestion)
                    <li>
                        <button
                            wire:click="selectSuggestion('{{ $suggestion }}')"
                            class="flex w-full items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-400" />
                            <span>{{ $suggestion }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Error Message --}}
    @if($error)
        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
    @endif

    {{-- Loading State --}}
    @if($loading)
        <div class="mt-6 flex justify-center">
            <x-filament::loading-indicator class="h-6 w-6" />
        </div>
    @endif

    {{-- Search Results --}}
    @if(!$loading && count($results) > 0)
        <div class="mt-6">
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                {{ __('knowledgebase::knowledgebase.showing_results', ['count' => count($results), 'total' => $total, 'query' => $query]) }}
            </p>

            <div class="space-y-4">
                @foreach($results as $article)
                    <a
                        href="{{ route('filament.tenant.pages.article-view', ['slug' => $article['slug']]) }}"
                        class="block p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-md transition-all"
                        wire:navigate
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white truncate">
                                    {{ $article['title'] }}
                                </h3>
                                @if($article['excerpt'])
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                        {{ $article['excerpt'] }}
                                    </p>
                                @endif
                                <div class="mt-2 flex items-center gap-2">
                                    @if($article['category'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-xs font-medium text-gray-600 dark:text-gray-300">
                                            {{ $article['category'] }}
                                        </span>
                                    @endif
                                    @if($article['is_featured'])
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-yellow-100 dark:bg-yellow-900/20 text-xs font-medium text-yellow-700 dark:text-yellow-400">
                                            <x-heroicon-o-star class="h-3 w-3" />
                                            {{ __('knowledgebase::knowledgebase.featured') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <x-heroicon-o-chevron-right class="h-5 w-5 text-gray-400 flex-shrink-0" />
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @elseif(!$loading && $query && count($results) === 0 && !$error)
        {{-- No Results --}}
        <div class="mt-6 text-center py-8">
            <x-heroicon-o-magnifying-glass class="mx-auto h-12 w-12 text-gray-400" />
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                {{ __('knowledgebase::knowledgebase.no_results') }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('knowledgebase::knowledgebase.no_results_description', ['query' => $query]) }}
            </p>
        </div>
    @endif
</div>
