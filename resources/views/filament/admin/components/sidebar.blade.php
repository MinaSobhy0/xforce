@props([
    'navigation',
])

<aside
    x-cloak="-lg"
    x-bind:class="$store.sidebar.isOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    {{ $attributes->class([
        'fi-sidebar fixed inset-y-0 start-0 z-30 flex h-screen w-64 flex-col bg-white shadow-xl ring-1 ring-gray-950/5 transition-transform dark:bg-gray-900 dark:ring-white/10 lg:z-0 lg:sticky lg:shadow-none lg:ring-0',
    ]) }}
>
    {{-- Logo Header --}}
    <header class="flex h-16 shrink-0 items-center gap-x-4 px-6 border-b border-gray-200 dark:border-gray-700">
        @if ($homeUrl = filament()->getHomeUrl())
            <a {{ \Filament\Support\generate_href_html($homeUrl) }} class="flex items-center gap-3">
                <x-filament::icon
                    icon="heroicon-o-building-office-2"
                    class="h-8 w-8 text-primary-600"
                />
                <span class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ filament()->getBrandName() }}
                </span>
            </a>
        @endif
    </header>

    {{-- Navigation --}}
    <nav class="flex-1 overflow-y-auto px-4 py-6">
        <ul class="space-y-6">
            @foreach ($navigation as $group)
                <li>
                    {{-- Group Label --}}
                    @if ($group->getLabel())
                        <div class="flex items-center gap-2 px-3 mb-2">
                            @if ($groupIcon = $group->getIcon())
                                <x-filament::icon
                                    :icon="$groupIcon"
                                    class="h-4 w-4 text-gray-400"
                                />
                            @endif
                            <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                {{ $group->getLabel() }}
                            </span>
                        </div>
                    @endif

                    {{-- Group Items --}}
                    <ul class="space-y-1">
                        @foreach ($group->getItems() as $item)
                            @php
                                $isActive = $item->isActive();
                            @endphp
                            <li>
                                <a
                                    href="{{ $item->getUrl() }}"
                                    @if ($item->shouldOpenUrlInNewTab()) target="_blank" @endif
                                    @class([
                                        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                        'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' => $isActive,
                                        'text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white' => !$isActive,
                                    ])
                                >
                                    @if ($icon = $item->getIcon())
                                        <x-filament::icon
                                            :icon="$icon"
                                            @class([
                                                'h-5 w-5',
                                                'text-primary-600 dark:text-primary-400' => $isActive,
                                                'text-gray-400' => !$isActive,
                                            ])
                                        />
                                    @endif
                                    <span>{{ $item->getLabel() }}</span>
                                    @if ($badge = $item->getBadge())
                                        <span class="ml-auto rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-500/20 dark:text-primary-400">
                                            {{ $badge }}
                                        </span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>
    </nav>

    {{-- User Section & Logout --}}
    <div class="shrink-0 border-t border-gray-200 p-4 dark:border-gray-700">
        <div class="flex items-center gap-3 mb-3 px-2">
            <x-filament-panels::avatar.user size="sm" :user="filament()->auth()->user()" />
            <div class="flex-1 truncate">
                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                    {{ filament()->getUserName(filament()->auth()->user()) }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                    {{ filament()->auth()->user()->email }}
                </p>
            </div>
        </div>
        <form action="{{ filament()->getLogoutUrl() }}" method="post">
            @csrf
            <button
                type="submit"
                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white transition-colors"
            >
                <x-filament::icon
                    icon="heroicon-o-arrow-right-on-rectangle"
                    class="h-5 w-5 text-gray-400"
                />
                <span>{{ __('filament-panels::layout.actions.logout.label') }}</span>
            </button>
        </form>
    </div>
</aside>
