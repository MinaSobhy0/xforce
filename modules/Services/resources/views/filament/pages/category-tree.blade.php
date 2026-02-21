<x-filament-panels::page>
    <div
        x-data="{
            categories: @js($this->categories->toArray()),
            expanded: {},
            dragging: false,

            init() {
                // Expand all by default
                this.expandAll(this.categories);
                this.initSortable();
            },

            expandAll(categories) {
                categories.forEach(cat => {
                    this.expanded[cat.id] = true;
                    if (cat.children && cat.children.length) {
                        this.expandAll(cat.children);
                    }
                });
            },

            toggle(id) {
                this.expanded[id] = !this.expanded[id];
            },

            isExpanded(id) {
                return this.expanded[id] ?? false;
            },

            initSortable() {
                // Initialize sortable on all nested lists
                this.$nextTick(() => {
                    this.makeSortable(this.$refs.rootList);
                });
            },

            makeSortable(el) {
                if (!el || typeof Sortable === 'undefined') return;

                new Sortable(el, {
                    group: 'categories',
                    animation: 150,
                    fallbackOnBody: true,
                    swapThreshold: 0.65,
                    handle: '.drag-handle',
                    ghostClass: 'bg-primary-50 dark:bg-primary-900/20',
                    dragClass: 'opacity-50',
                    onStart: () => this.dragging = true,
                    onEnd: (evt) => {
                        this.dragging = false;
                        this.saveOrder();
                    }
                });

                // Initialize nested sortable lists
                el.querySelectorAll('.category-children').forEach(childList => {
                    this.makeSortable(childList);
                });
            },

            saveOrder() {
                const items = this.buildOrderTree(this.$refs.rootList);
                $wire.reorderCategories(items, null);
            },

            buildOrderTree(el) {
                if (!el) return [];
                const items = [];
                el.querySelectorAll(':scope > .category-item').forEach(item => {
                    const id = item.dataset.id;
                    const childList = item.querySelector(':scope > .category-children');
                    items.push({
                        id: id,
                        children: childList ? this.buildOrderTree(childList) : []
                    });
                });
                return items;
            },

            toggleActive(id) {
                $wire.toggleActive(id);
            }
        }"
        x-init="init()"
        class="space-y-4"
    >
        {{-- Load SortableJS --}}
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

        {{-- Category Tree --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            {{-- Header --}}
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ __('services::services.category_tree.tree_view') }}
                    </h3>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('services::services.category_tree.drag_hint') }}
                    </span>
                </div>
            </div>

            {{-- Tree Container --}}
            <div class="p-4">
                <ul x-ref="rootList" class="space-y-2">
                    @foreach ($this->categories as $category)
                        <x-services::category-tree-item :category="$category" />
                    @endforeach
                </ul>

                @if ($this->categories->isEmpty())
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                            {{ __('services::services.category_tree.no_categories') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('services::services.category_tree.create_first') }}
                        </p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Legend --}}
        <div class="flex items-center gap-6 text-sm text-gray-500 dark:text-gray-400">
            <div class="flex items-center gap-2">
                <div class="w-3 h-3 rounded-full bg-green-500"></div>
                <span>{{ __('services::services.category_tree.active') }}</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-3 h-3 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                <span>{{ __('services::services.category_tree.inactive') }}</span>
            </div>
        </div>
    </div>
</x-filament-panels::page>
