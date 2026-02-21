<?php

namespace Modules\Treatments\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Modules\Treatments\Models\TreatmentCategory;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class CategoryTreePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $navigationGroup = 'Treatments';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'treatments::filament.pages.category-tree';

    public function getTitle(): string|Htmlable
    {
        return __('treatments::treatments.category_tree.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('treatments::treatments.category_tree.navigation');
    }

    public function getBreadcrumbs(): array
    {
        return [
            route('filament.admin.pages.dashboard') => __('filament-panels::pages/dashboard.title'),
            '#' => static::getNavigationLabel(),
        ];
    }

    /**
     * Get categories as nested tree structure.
     */
    public function getCategoriesProperty(): Collection
    {
        return TreatmentCategory::with(['children' => function ($query) {
            $query->with(['children' => function ($q) {
                $q->with('children')->orderBy('sort_order');
            }])->orderBy('sort_order');
        }])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Reorder categories via drag-and-drop.
     */
    public function reorderCategories(array $items, ?string $parentId = null): void
    {
        foreach ($items as $index => $item) {
            $category = TreatmentCategory::find($item['id']);

            if ($category) {
                $category->update([
                    'parent_id' => $parentId,
                    'sort_order' => $index,
                ]);

                // Handle children recursively
                if (!empty($item['children'])) {
                    $this->reorderCategories($item['children'], $item['id']);
                }
            }
        }

        Notification::make()
            ->title(__('treatments::treatments.category_tree.reordered'))
            ->success()
            ->send();
    }

    /**
     * Toggle category active state.
     */
    public function toggleActive(string $categoryId): void
    {
        $category = TreatmentCategory::find($categoryId);

        if ($category) {
            $category->update(['is_active' => !$category->is_active]);

            Notification::make()
                ->title($category->is_active
                    ? __('treatments::treatments.category_tree.activated')
                    : __('treatments::treatments.category_tree.deactivated'))
                ->success()
                ->send();
        }
    }

    /**
     * Get header actions.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label(__('treatments::treatments.category_tree.create_category'))
                ->icon('heroicon-o-plus')
                ->url(route('filament.admin.resources.treatment-categories.create')),

            Action::make('list_view')
                ->label(__('treatments::treatments.category_tree.list_view'))
                ->icon('heroicon-o-list-bullet')
                ->url(route('filament.admin.resources.treatment-categories.index'))
                ->color('gray'),
        ];
    }
}
