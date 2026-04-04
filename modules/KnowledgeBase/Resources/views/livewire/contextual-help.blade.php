<div>
    {{-- Slide-out Panel --}}
    <div
        x-data="{ open: $wire.entangle('isOpen') }"
        x-show="open"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50"
        style="display: none;"
    >
        {{-- Backdrop --}}
        <div
            class="absolute inset-0 bg-gray-900/50"
            @click="$wire.close()"
        ></div>

        {{-- Panel --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-gray-900 shadow-xl flex flex-col"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                @if($selectedArticle)
                    <button
                        wire:click="backToList"
                        class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <x-heroicon-o-arrow-left class="h-4 w-4" />
                        {{ __('knowledgebase::knowledgebase.back') }}
                    </button>
                @else
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ __('knowledgebase::knowledgebase.help') }}
                    </h2>
                @endif

                <button
                    wire:click="close"
                    class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                >
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>

            {{-- Content --}}
            <div class="flex-1 overflow-y-auto">
                @if($loading)
                    <div class="flex items-center justify-center h-32">
                        <x-filament::loading-indicator class="h-6 w-6" />
                    </div>
                @elseif($selectedArticle)
                    {{-- Article View --}}
                    <div class="p-4">
                        @if($selectedArticle['category'])
                            <span class="inline-flex items-center px-2 py-1 rounded-md bg-gray-100 dark:bg-gray-800 text-xs font-medium text-gray-600 dark:text-gray-300 mb-2">
                                {{ $selectedArticle['category']['name'] }}
                            </span>
                        @endif

                        <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-4">
                            {{ $selectedArticle['title'] }}
                        </h1>

                        <div class="prose prose-sm dark:prose-invert max-w-none">
                            {!! $selectedArticle['content'] !!}
                        </div>

                        {{-- Tags --}}
                        @if(!empty($selectedArticle['tags']))
                            <div class="flex flex-wrap gap-2 mt-6">
                                @foreach($selectedArticle['tags'] as $tag)
                                    <span class="px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-xs text-gray-600 dark:text-gray-400">
                                        {{ $tag }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        {{-- Feedback --}}
                        <div class="mt-8 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                {{ __('knowledgebase::knowledgebase.was_this_helpful') }}
                            </p>
                            @php
                                $userFeedback = $this->getUserFeedback($selectedArticle['id']);
                            @endphp
                            <div class="flex gap-3">
                                <button
                                    wire:click="submitFeedback({{ $selectedArticle['id'] }}, true)"
                                    class="flex items-center gap-2 px-4 py-2 rounded-lg border transition-colors {{ $userFeedback === true ? 'border-green-500 bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400' : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-green-500 hover:text-green-600' }}"
                                >
                                    <x-heroicon-o-hand-thumb-up class="h-4 w-4" />
                                    <span>{{ __('knowledgebase::knowledgebase.yes') }}</span>
                                    @if($selectedArticle['helpful_count'] > 0)
                                        <span class="text-xs">({{ $selectedArticle['helpful_count'] }})</span>
                                    @endif
                                </button>
                                <button
                                    wire:click="submitFeedback({{ $selectedArticle['id'] }}, false)"
                                    class="flex items-center gap-2 px-4 py-2 rounded-lg border transition-colors {{ $userFeedback === false ? 'border-red-500 bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400' : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-red-500 hover:text-red-600' }}"
                                >
                                    <x-heroicon-o-hand-thumb-down class="h-4 w-4" />
                                    <span>{{ __('knowledgebase::knowledgebase.no') }}</span>
                                    @if($selectedArticle['not_helpful_count'] > 0)
                                        <span class="text-xs">({{ $selectedArticle['not_helpful_count'] }})</span>
                                    @endif
                                </button>
                            </div>
                        </div>

                        {{-- Related Articles --}}
                        @if(!empty($selectedArticle['related']))
                            <div class="mt-6">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">
                                    {{ __('knowledgebase::knowledgebase.related_articles') }}
                                </h3>
                                <div class="space-y-2">
                                    @foreach($selectedArticle['related'] as $related)
                                        <button
                                            wire:click="viewArticle({{ $related['id'] }})"
                                            class="block w-full text-left px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                        >
                                            <span class="text-sm text-gray-900 dark:text-white">
                                                {{ $related['title'] }}
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    {{-- Articles List --}}
                    <div class="p-4">
                        @if(count($articles) > 0)
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
                                {{ __('knowledgebase::knowledgebase.help_articles') }}
                            </h3>
                            <div class="space-y-2">
                                @foreach($articles as $article)
                                    <button
                                        wire:click="viewArticle({{ $article['id'] }})"
                                        class="block w-full text-left p-3 rounded-lg bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                    >
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $article['title'] }}
                                        </h4>
                                        @if($article['excerpt'])
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">
                                                {{ $article['excerpt'] }}
                                            </p>
                                        @endif
                                        @if($article['category'])
                                            <span class="inline-block mt-2 px-2 py-0.5 rounded bg-gray-200 dark:bg-gray-700 text-xs text-gray-600 dark:text-gray-300">
                                                {{ $article['category'] }}
                                            </span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8">
                                <x-heroicon-o-document-text class="mx-auto h-12 w-12 text-gray-400" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                                    {{ __('knowledgebase::knowledgebase.no_articles') }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('knowledgebase::knowledgebase.no_articles_description') }}
                                </p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Footer --}}
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                <a
                    href="{{ route('filament.tenant.pages.knowledge-base') }}"
                    class="flex items-center justify-center gap-2 w-full px-4 py-2 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                >
                    <x-heroicon-o-book-open class="h-4 w-4" />
                    {{ __('knowledgebase::knowledgebase.browse_all_articles') }}
                </a>
            </div>
        </div>
    </div>
</div>
