<div class="flex items-center">
    @if($branches->count() > 0)
        {{-- If only one branch, show it without dropdown --}}
        @if($branches->count() === 1)
            <div class="flex items-center gap-1 sm:gap-2 rounded-lg px-2 sm:px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                <x-heroicon-o-building-office-2 class="h-5 w-5 text-primary-500 shrink-0" />
                <span class="hidden sm:inline max-w-[120px] truncate">{{ $branches->first()->name }}</span>
            </div>
        @else
            {{-- Multiple branches - show dropdown --}}
            <x-filament::dropdown placement="bottom-start" width="xs">
                <x-slot name="trigger">
                    <button type="button" class="flex items-center gap-1 sm:gap-2 rounded-lg px-2 sm:px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5 transition">
                        <x-heroicon-o-building-office-2 class="h-5 w-5 text-primary-500 shrink-0" />
                        <span class="hidden sm:inline max-w-[100px] truncate">{{ $displayLabel }}</span>
                        <x-heroicon-m-chevron-down class="h-4 w-4 text-gray-400 shrink-0" />
                    </button>
                </x-slot>

                <div class="p-2">
                    {{-- All Branches Option (only if multiple branches allowed) --}}
                    @if($showAllBranches)
                        <button
                            wire:click="selectAll"
                            class="flex items-center gap-2 w-full px-3 py-2 text-sm rounded-lg transition {{ $isAllSelected ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' : 'hover:bg-gray-100 dark:hover:bg-white/5 text-gray-700 dark:text-gray-200' }}"
                        >
                            @if($isAllSelected)
                                <x-heroicon-s-check-circle class="h-5 w-5 text-primary-500" />
                            @else
                                <x-heroicon-o-squares-2x2 class="h-5 w-5 text-gray-400" />
                            @endif
                            <span>{{ __('All Branches') }}</span>
                        </button>

                        <div class="my-2 border-t border-gray-200 dark:border-white/10"></div>

                        {{-- Multi-select hint --}}
                        <div class="px-3 py-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Click to select, Ctrl+Click for multiple') }}
                        </div>
                    @endif

                    {{-- Individual Branches --}}
                    <div class="space-y-1 {{ $showAllBranches ? 'mt-1' : '' }}">
                        @foreach($branches as $branch)
                            <button
                                wire:click="toggleBranch('{{ $branch->id }}')"
                                @click="if (!$event.ctrlKey && !$event.metaKey) $wire.selectBranch('{{ $branch->id }}')"
                                class="flex items-center gap-2 w-full px-3 py-2 text-sm rounded-lg transition {{ in_array($branch->id, $selectedBranchIds) ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' : 'hover:bg-gray-100 dark:hover:bg-white/5 text-gray-700 dark:text-gray-200' }}"
                            >
                                @if(in_array($branch->id, $selectedBranchIds))
                                    <x-heroicon-s-check-circle class="h-5 w-5 text-primary-500" />
                                @elseif($branch->is_main)
                                    <x-heroicon-o-star class="h-5 w-5 text-warning-500" />
                                @else
                                    <x-heroicon-o-building-office class="h-5 w-5 text-gray-400" />
                                @endif
                                <span class="flex-1 text-left truncate">{{ $branch->name }}</span>
                                @if($branch->is_main)
                                    <span class="text-xs text-warning-500 dark:text-warning-400">({{ __('Main') }})</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            </x-filament::dropdown>
        @endif
    @endif
</div>
