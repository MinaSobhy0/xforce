@props([
    'navigation',
])

@php
    $openSidebarClasses = 'fi-sidebar-open translate-x-0 shadow-xl ring-1 ring-gray-950/5 dark:ring-white/10 rtl:-translate-x-0';
    $isRtl = __('filament-panels::layout.direction') === 'rtl';
    $currentPanel = filament()->getCurrentPanel()?->getId();
    $isAdminPanel = $currentPanel === 'admin';
@endphp

{{-- format-ignore-start --}}
@if($isAdminPanel)
{{-- ADMIN PANEL: Simple single sidebar --}}
<aside
    x-cloak="-lg"
    x-bind:class="$store.sidebar.isOpen ? @js($openSidebarClasses) : '-translate-x-full rtl:translate-x-full lg:translate-x-0'"
    {{ $attributes->class([
        'fi-sidebar fixed inset-y-0 start-0 z-30 flex h-screen w-64 flex-col bg-white shadow-xl ring-1 ring-gray-950/5 transition-all dark:bg-gray-900 dark:ring-white/10 lg:z-0 lg:sticky lg:shadow-none lg:ring-0',
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
                                <x-filament::icon :icon="$groupIcon" class="h-4 w-4 text-gray-400" />
                            @endif
                            <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                {{ $group->getLabel() }}
                            </span>
                        </div>
                    @endif

                    {{-- Group Items --}}
                    <ul class="space-y-1">
                        @foreach ($group->getItems() as $item)
                            @php $isActive = $item->isActive(); @endphp
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
                                        <x-filament::icon :icon="$icon" @class(['h-5 w-5', 'text-primary-600 dark:text-primary-400' => $isActive, 'text-gray-400' => !$isActive]) />
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
            <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white transition-colors">
                <x-filament::icon icon="heroicon-o-arrow-right-on-rectangle" class="h-5 w-5 text-gray-400" />
                <span>{{ __('filament-panels::layout.actions.logout.label') }}</span>
            </button>
        </form>
    </div>
</aside>

@else
{{-- SUPER ADMIN PANEL: Double sidebar --}}
<aside
    x-data="{
        activeGroup: null,
        setActiveGroup(group) {
            if (this.activeGroup === group) {
                this.activeGroup = null;
            } else {
                this.activeGroup = group;
            }
        },
        isGroupActive(group) {
            return this.activeGroup === group;
        }
    }"
    x-cloak="-lg"
    x-bind:class="$store.sidebar.isOpen ? @js($openSidebarClasses) : '-translate-x-full rtl:translate-x-full lg:translate-x-0'"
    {{
        $attributes->class([
            'fi-sidebar fixed inset-y-0 start-0 z-30 flex h-screen content-start bg-white transition-all dark:bg-gray-900 lg:z-0 lg:sticky lg:bg-transparent lg:shadow-none lg:ring-0 lg:transition-none dark:lg:bg-transparent',
        ])
    }}
>
    <div class="flex h-full">
        {{-- First Sidebar: Icons with labels --}}
        <div class="fi-sidebar-icons flex flex-col w-24 bg-gray-50 dark:bg-gray-950 border-r border-gray-200 dark:border-gray-800">
            {{-- Logo Area --}}
            <header class="flex h-16 items-center justify-center bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                @if ($homeUrl = filament()->getHomeUrl())
                    <a {{ \Filament\Support\generate_href_html($homeUrl) }} class="flex items-center justify-center">
                        @php $logo = \App\Models\PlatformSetting::get('platform_logo'); @endphp
                        @if($logo)
                            <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="h-10 w-auto object-contain" />
                        @else
                            <x-filament::icon icon="heroicon-o-squares-2x2" class="w-8 h-8 text-primary-600" />
                        @endif
                    </a>
                @endif
            </header>

            {{-- Navigation Icons --}}
            <nav class="flex-1 overflow-y-auto py-8 px-2">
                <ul class="flex flex-col items-center gap-4">
                    @foreach ($navigation as $group)
                        @php
                            $groupLabel = $group->getLabel();
                            $groupIcon = $group->getIcon();
                        @endphp
                        <li class="w-full">
                            <button
                                type="button"
                                x-on:click="setActiveGroup('{{ $groupLabel }}')"
                                x-bind:class="isGroupActive('{{ $groupLabel }}') ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-800'"
                                class="flex flex-col items-center justify-center w-full py-3 px-2 rounded-xl transition-colors duration-200 group"
                                title="{{ $groupLabel }}"
                            >
                                <x-filament::icon :icon="$groupIcon ?? 'heroicon-o-folder'" class="w-5 h-5 mb-1" />
                                <span class="text-[9px] font-normal text-center leading-tight whitespace-normal break-words w-full">
                                    {{ $groupLabel }}
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </nav>

            {{-- Logout --}}
            <div class="border-t border-gray-200 dark:border-gray-800 p-2">
                <form action="{{ filament()->getLogoutUrl() }}" method="post" class="w-full">
                    @csrf
                    <x-filament::icon-button color="gray" icon="heroicon-o-arrow-right-on-rectangle" :label="__('filament-panels::layout.actions.logout.label')" tag="button" type="submit" class="w-full" />
                </form>
            </div>
        </div>

        {{-- Second Sidebar: Sub-items --}}
        <div
            x-show="activeGroup !== null"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 w-0"
            x-transition:enter-end="opacity-100 w-56"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 w-56"
            x-transition:leave-end="opacity-0 w-0"
            class="fi-sidebar-items w-56 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 overflow-hidden flex flex-col shadow-xl ring-1 ring-gray-950/5 dark:ring-white/10"
        >
            <header class="flex h-16 items-center gap-x-4 px-4 bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 shrink-0">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white truncate flex-1" x-text="activeGroup"></h2>
                <button type="button" x-on:click="activeGroup = null" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 hover:text-gray-500">
                    <x-filament::icon icon="heroicon-o-x-mark" class="w-5 h-5" />
                </button>
            </header>

            <nav class="flex-1 overflow-y-auto py-4 px-3">
                @foreach ($navigation as $group)
                    <ul x-show="activeGroup === '{{ $group->getLabel() }}'" x-transition class="space-y-1">
                        @foreach ($group->getItems() as $item)
                            @php $itemIsActive = $item->isActive(); @endphp
                            <li>
                                <a
                                    href="{{ $item->getUrl() }}"
                                    @if ($item->shouldOpenUrlInNewTab()) target="_blank" @endif
                                    @class([
                                        'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-200',
                                        'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' => $itemIsActive,
                                        'text-gray-700 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-300 dark:hover:text-white dark:hover:bg-gray-800' => !$itemIsActive,
                                    ])
                                >
                                    @if($icon = $item->getIcon())
                                        <x-filament::icon :icon="$icon" @class(['w-5 h-5', 'text-primary-600 dark:text-primary-400' => $itemIsActive, 'text-gray-400' => !$itemIsActive]) />
                                    @endif
                                    <span class="truncate">{{ $item->getLabel() }}</span>
                                    @if($badge = $item->getBadge())
                                        <span class="ml-auto inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-400">
                                            {{ $badge }}
                                        </span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </nav>
        </div>
    </div>
</aside>
@endif
{{-- format-ignore-end --}}
