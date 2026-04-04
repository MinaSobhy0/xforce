<x-filament-panels::page>
    @if($article)
        <div class="max-w-4xl mx-auto space-y-6">
            {{-- Article Header --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-6">
                    {{-- Category Badge --}}
                    @if($article['category'])
                        <div class="mb-4">
                            <span class="inline-flex items-center px-3 py-1 rounded-full bg-primary-100 dark:bg-primary-900/20 text-sm font-medium text-primary-700 dark:text-primary-300">
                                {{ $article['category']['name'] }}
                            </span>
                        </div>
                    @endif

                    {{-- Title --}}
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">
                        {{ $article['title'] }}
                    </h1>

                    {{-- Excerpt --}}
                    @if($article['excerpt'])
                        <p class="text-lg text-gray-600 dark:text-gray-400">
                            {{ $article['excerpt'] }}
                        </p>
                    @endif

                    {{-- Meta --}}
                    <div class="flex items-center gap-4 mt-4 text-sm text-gray-500 dark:text-gray-400">
                        <span class="flex items-center gap-1">
                            <x-heroicon-o-eye class="h-4 w-4" />
                            {{ number_format($article['view_count']) }} {{ __('knowledgebase::knowledgebase.views') }}
                        </span>
                        @if($article['helpfulness_percentage'] !== null)
                            <span class="flex items-center gap-1">
                                <x-heroicon-o-hand-thumb-up class="h-4 w-4" />
                                {{ $article['helpfulness_percentage'] }}% {{ __('knowledgebase::knowledgebase.found_helpful') }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Article Content --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-6">
                    <div class="prose prose-lg dark:prose-invert max-w-none prose-headings:text-gray-900 dark:prose-headings:text-white prose-p:text-gray-600 dark:prose-p:text-gray-300 prose-a:text-primary-600 dark:prose-a:text-primary-400">
                        {!! $article['content'] !!}
                    </div>
                </div>
            </div>

            {{-- Tags --}}
            @if(!empty($article['tags']))
                <div class="flex flex-wrap gap-2">
                    @foreach($article['tags'] as $tag)
                        <span class="px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-sm text-gray-600 dark:text-gray-300">
                            {{ $tag }}
                        </span>
                    @endforeach
                </div>
            @endif

            {{-- Feedback Section --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-6 text-center">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                        {{ __('knowledgebase::knowledgebase.was_this_helpful') }}
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        {{ __('knowledgebase::knowledgebase.feedback_description') }}
                    </p>

                    <div class="flex justify-center gap-4">
                        <button
                            wire:click="submitFeedback(true)"
                            class="flex items-center gap-2 px-6 py-3 rounded-lg border-2 transition-all {{ $userFeedback === true ? 'border-green-500 bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400' : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-green-500 hover:text-green-600' }}"
                        >
                            <x-heroicon-o-hand-thumb-up class="h-5 w-5" />
                            <span class="font-medium">{{ __('knowledgebase::knowledgebase.yes_helpful') }}</span>
                            @if($article['helpful_count'] > 0)
                                <span class="text-sm">({{ $article['helpful_count'] }})</span>
                            @endif
                        </button>
                        <button
                            wire:click="submitFeedback(false)"
                            class="flex items-center gap-2 px-6 py-3 rounded-lg border-2 transition-all {{ $userFeedback === false ? 'border-red-500 bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400' : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-red-500 hover:text-red-600' }}"
                        >
                            <x-heroicon-o-hand-thumb-down class="h-5 w-5" />
                            <span class="font-medium">{{ __('knowledgebase::knowledgebase.not_helpful') }}</span>
                            @if($article['not_helpful_count'] > 0)
                                <span class="text-sm">({{ $article['not_helpful_count'] }})</span>
                            @endif
                        </button>
                    </div>
                </div>
            </div>

            {{-- Related Articles --}}
            @if(!empty($article['related']))
                <div class="space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ __('knowledgebase::knowledgebase.related_articles') }}
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($article['related'] as $related)
                            <a
                                href="{{ route('filament.tenant.pages.article-view', ['slug' => $related['slug']]) }}"
                                class="block p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-md transition-all"
                                wire:navigate
                            >
                                <h3 class="font-semibold text-gray-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400">
                                    {{ $related['title'] }}
                                </h3>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @else
        <div class="text-center py-12">
            <x-heroicon-o-document-text class="mx-auto h-12 w-12 text-gray-400" />
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                {{ __('knowledgebase::knowledgebase.article_not_found') }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('knowledgebase::knowledgebase.article_not_found_description') }}
            </p>
            <div class="mt-6">
                <a
                    href="{{ route('filament.tenant.pages.knowledge-base') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors"
                >
                    <x-heroicon-o-arrow-left class="h-4 w-4" />
                    {{ __('knowledgebase::knowledgebase.back_to_knowledge_base') }}
                </a>
            </div>
        </div>
    @endif
</x-filament-panels::page>
