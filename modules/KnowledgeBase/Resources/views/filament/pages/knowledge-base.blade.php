<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Search Section --}}
        <div class="bg-gradient-to-r from-primary-500 to-primary-600 rounded-xl p-8 text-white">
            <h1 class="text-2xl font-bold mb-2">{{ __('knowledgebase::knowledgebase.how_can_we_help') }}</h1>
            <p class="text-primary-100 mb-6">{{ __('knowledgebase::knowledgebase.search_description') }}</p>

            <livewire:knowledgebase::article-search />
        </div>

        @if($selectedCategory)
            {{-- Category Articles --}}
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <button
                        wire:click="clearCategory"
                        class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <x-heroicon-o-arrow-left class="h-4 w-4" />
                        {{ __('knowledgebase::knowledgebase.back_to_categories') }}
                    </button>
                </div>

                @if(count($categoryArticles) > 0)
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($categoryArticles as $article)
                            <a
                                href="{{ route('filament.tenant.pages.article-view', ['slug' => $article['slug']]) }}"
                                class="block p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-md transition-all"
                                wire:navigate
                            >
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-2">
                                    {{ $article['title'] }}
                                </h3>
                                @if($article['excerpt'])
                                    <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                        {{ $article['excerpt'] }}
                                    </p>
                                @endif
                                @if($article['is_featured'])
                                    <span class="inline-flex items-center gap-1 mt-2 px-2 py-0.5 rounded bg-yellow-100 dark:bg-yellow-900/20 text-xs font-medium text-yellow-700 dark:text-yellow-400">
                                        <x-heroicon-o-star class="h-3 w-3" />
                                        {{ __('knowledgebase::knowledgebase.featured') }}
                                    </span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <x-heroicon-o-document-text class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                            {{ __('knowledgebase::knowledgebase.no_articles_in_category') }}
                        </h3>
                    </div>
                @endif
            </div>
        @else
            {{-- Featured Articles --}}
            @if(count($featuredArticles) > 0)
                <div class="space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ __('knowledgebase::knowledgebase.featured_articles') }}
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($featuredArticles as $article)
                            <a
                                href="{{ route('filament.tenant.pages.article-view', ['slug' => $article['slug']]) }}"
                                class="group block p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-md transition-all"
                                wire:navigate
                            >
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 p-2 bg-primary-50 dark:bg-primary-900/20 rounded-lg">
                                        <x-heroicon-o-star class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 mb-1">
                                            {{ $article['title'] }}
                                        </h3>
                                        @if($article['excerpt'])
                                            <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                                {{ $article['excerpt'] }}
                                            </p>
                                        @endif
                                        @if($article['category'])
                                            <span class="inline-block mt-2 px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-xs text-gray-600 dark:text-gray-300">
                                                {{ $article['category'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Browse by Category --}}
            @if(count($categories) > 0)
                <div class="space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ __('knowledgebase::knowledgebase.browse_by_category') }}
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($categories as $category)
                            <button
                                wire:click="selectCategory('{{ $category['slug'] }}')"
                                class="group block p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-md transition-all text-left"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 p-2 bg-gray-100 dark:bg-gray-700 rounded-lg group-hover:bg-primary-50 dark:group-hover:bg-primary-900/20 transition-colors">
                                        @if($category['icon'])
                                            <x-dynamic-component :component="$category['icon']" class="h-5 w-5 text-gray-600 dark:text-gray-400 group-hover:text-primary-600 dark:group-hover:text-primary-400" />
                                        @else
                                            <x-heroicon-o-folder class="h-5 w-5 text-gray-600 dark:text-gray-400 group-hover:text-primary-600 dark:group-hover:text-primary-400" />
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 mb-1">
                                            {{ $category['name'] }}
                                        </h3>
                                        @if($category['description'])
                                            <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                                {{ $category['description'] }}
                                            </p>
                                        @endif
                                        <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            {{ trans_choice('knowledgebase::knowledgebase.articles_count', $category['articles_count'], ['count' => $category['articles_count']]) }}
                                        </span>
                                    </div>
                                    <x-heroicon-o-chevron-right class="h-5 w-5 text-gray-400 group-hover:text-primary-500 transition-colors" />
                                </div>

                                {{-- Subcategories --}}
                                @if(count($category['children']) > 0)
                                    <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach(array_slice($category['children'], 0, 3) as $child)
                                                <span class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-xs text-gray-600 dark:text-gray-300">
                                                    {{ $child['name'] }}
                                                </span>
                                            @endforeach
                                            @if(count($category['children']) > 3)
                                                <span class="px-2 py-0.5 text-xs text-gray-500">
                                                    +{{ count($category['children']) - 3 }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Empty State --}}
            @if(count($categories) === 0 && count($featuredArticles) === 0)
                <div class="text-center py-12">
                    <x-heroicon-o-book-open class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                        {{ __('knowledgebase::knowledgebase.no_content_yet') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('knowledgebase::knowledgebase.no_content_description') }}
                    </p>
                </div>
            @endif
        @endif
    </div>

    {{-- Screen Guide Component (global) --}}
    <livewire:knowledgebase::screen-guide />

    {{-- Contextual Help Panel (global) --}}
    <livewire:knowledgebase::contextual-help />
</x-filament-panels::page>
