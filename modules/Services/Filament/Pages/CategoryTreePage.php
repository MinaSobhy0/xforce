<?php

namespace Modules\Services\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Modules\Services\Models\ServiceCategory;
use Modules\Services\Filament\Resources\ServiceCategoryResource;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class CategoryTreePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'services::filament.pages.category-tree';

    public function getTitle(): string|Htmlable
    {
        return __('services::services.category_tree.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('services::services.category_tree.navigation');
    }

    public function getBreadcrumbs(): array
    {
        return [
            '/' => __('filament-panels::pages/dashboard.title'),
            '#' => static::getNavigationLabel(),
        ];
    }

    /**
     * Get categories as nested tree structure.
     */
    public function getCategoriesProperty(): Collection
    {
        return ServiceCategory::with(['children' => function ($query) {
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
            $category = ServiceCategory::find($item['id']);

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
            ->title(__('services::services.category_tree.reordered'))
            ->success()
            ->send();
    }

    /**
     * Toggle category active state.
     */
    public function toggleActive(string $categoryId): void
    {
        $category = ServiceCategory::find($categoryId);

        if ($category) {
            $category->update(['is_active' => !$category->is_active]);

            Notification::make()
                ->title($category->is_active
                    ? __('services::services.category_tree.activated')
                    : __('services::services.category_tree.deactivated'))
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
                ->label(__('services::services.category_tree.create_category'))
                ->icon('heroicon-o-plus')
                ->url(ServiceCategoryResource::getUrl('create')),

            Action::make('list_view')
                ->label(__('services::services.category_tree.list_view'))
                ->icon('heroicon-o-list-bullet')
                ->url(ServiceCategoryResource::getUrl('index'))
                ->color('gray'),
        ];
    }
}
