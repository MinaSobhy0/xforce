<div
    x-data="{
        activeGroup: null,
        setActiveGroup(group) {
            this.activeGroup = this.activeGroup === group ? null : group;
        }
    }"
    class="fi-double-sidebar flex h-full"
>
    {{-- First Sidebar: Icons with labels --}}
    <div class="fi-sidebar-icons flex flex-col items-center bg-gray-950 dark:bg-gray-900 w-20 py-4 gap-2 border-r border-gray-800">
        @foreach($navigation as $group)
            <button
                type="button"
                @click="setActiveGroup('{{ $group->getLabel() }}')"
                :class="activeGroup === '{{ $group->getLabel() }}' ? 'bg-primary-500/20 text-primary-400' : 'text-gray-400 hover:text-white hover:bg-gray-800'"
                class="flex flex-col items-center justify-center w-16 h-16 rounded-lg transition-colors duration-200"
                title="{{ $group->getLabel() }}"
            >
                @if($group->getIcon())
                    <x-filament::icon
                        :icon="$group->getIcon()"
                        class="w-6 h-6 mb-1"
                    />
                @else
                    <x-filament::icon
                        icon="heroicon-o-folder"
                        class="w-6 h-6 mb-1"
                    />
                @endif
                <span class="text-[10px] font-medium text-center leading-tight">
                    {{ Str::limit($group->getLabel(), 10) }}
                </span>
            </button>
        @endforeach
    </div>

    {{-- Second Sidebar: Sub-items --}}
    <div
        x-show="activeGroup !== null"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-x-4"
        x-transition:enter-end="opacity-100 translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-x-0"
        x-transition:leave-end="opacity-0 -translate-x-4"
        class="fi-sidebar-items w-56 bg-gray-900 dark:bg-gray-950 border-r border-gray-800 overflow-y-auto"
    >
        @foreach($navigation as $group)
            <div
                x-show="activeGroup === '{{ $group->getLabel() }}'"
                class="py-4"
            >
                <div class="px-4 mb-3">
                    <h3 class="text-sm font-semibold text-gray-300">
                        {{ $group->getLabel() }}
                    </h3>
                </div>
                <nav class="space-y-1 px-2">
                    @foreach($group->getItems() as $item)
                        <a
                            href="{{ $item->getUrl() }}"
                            @class([
                                'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-200',
                                'bg-primary-500/20 text-primary-400' => $item->isActive(),
                                'text-gray-400 hover:text-white hover:bg-gray-800' => !$item->isActive(),
                            ])
                        >
                            @if($item->getIcon())
                                <x-filament::icon
                                    :icon="$item->getIcon()"
                                    class="w-5 h-5"
                                />
                            @endif
                            <span>{{ $item->getLabel() }}</span>
                            @if($badge = $item->getBadge())
                                <span class="ml-auto inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-500/20 text-primary-400">
                                    {{ $badge }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                </nav>
            </div>
        @endforeach
    </div>
</div>
