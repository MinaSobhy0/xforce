@props(['category', 'depth' => 0])

<li
    class="category-item"
    data-id="{{ $category['id'] }}"
>
    <div
        class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
        :class="{ 'ring-2 ring-primary-500': dragging }"
    >
        {{-- Drag Handle --}}
        <button
            type="button"
            class="drag-handle cursor-grab active:cursor-grabbing p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
        >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
            </svg>
        </button>

        {{-- Expand/Collapse Toggle --}}
        @if (!empty($category['children']) && count($category['children']) > 0)
            <button
                type="button"
                x-on:click="toggle('{{ $category['id'] }}')"
                class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
            >
                <svg
                    class="w-4 h-4 transition-transform duration-200"
                    :class="{ 'rotate-90': isExpanded('{{ $category['id'] }}') }"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        @else
            <div class="w-6"></div>
        @endif

        {{-- Color Indicator --}}
        @if (!empty($category['color']))
            <div
                class="w-4 h-4 rounded-full border border-white dark:border-gray-700 shadow-sm"
                style="background-color: {{ $category['color'] }};"
            ></div>
        @endif

        {{-- Category Name --}}
        <div class="flex-1 min-w-0">
            <h4 class="text-sm font-medium text-gray-900 dark:text-white truncate">
                {{ $category['translated_name'] ?? ($category['name']['en'] ?? 'Unnamed') }}
            </h4>
            @if (!empty($category['description']['en']))
                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                    {{ $category['description']['en'] }}
                </p>
            @endif
        </div>

        {{-- Treatments Count Badge --}}
        @if (isset($category['treatments_count']) && $category['treatments_count'] > 0)
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                {{ $category['treatments_count'] }} {{ __('treatments::treatments.labels.treatments') }}
            </span>
        @endif

        {{-- Children Count --}}
        @if (!empty($category['children']) && count($category['children']) > 0)
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                {{ count($category['children']) }} {{ __('treatments::treatments.category_tree.subcategories') }}
            </span>
        @endif

        {{-- Status Indicator --}}
        <button
            type="button"
            x-on:click="toggleActive('{{ $category['id'] }}')"
            class="p-1 rounded-full transition-colors"
            :class="'{{ $category['is_active'] ? 'text-green-500 hover:bg-green-50 dark:hover:bg-green-900/20' : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' }}'"
            title="{{ $category['is_active'] ? __('treatments::treatments.category_tree.click_deactivate') : __('treatments::treatments.category_tree.click_activate') }}"
        >
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <circle cx="10" cy="10" r="6" />
            </svg>
        </button>

        {{-- Actions --}}
        <div class="flex items-center gap-1">
            <a
                href="{{ route('filament.admin.resources.treatment-categories.edit', $category['id']) }}"
                class="p-1.5 text-gray-400 hover:text-primary-500 transition-colors"
                title="{{ __('filament-actions::edit.single.label') }}"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </a>
        </div>
    </div>

    {{-- Children --}}
    @if (!empty($category['children']) && count($category['children']) > 0)
        <ul
            x-show="isExpanded('{{ $category['id'] }}')"
            x-collapse
            class="category-children mt-2 ml-8 space-y-2 border-l-2 border-gray-200 dark:border-gray-700 pl-4"
        >
            @foreach ($category['children'] as $child)
                <x-treatments::category-tree-item :category="$child" :depth="$depth + 1" />
            @endforeach
        </ul>
    @endif
</li>
