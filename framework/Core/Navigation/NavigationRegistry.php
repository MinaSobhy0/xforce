<?php

namespace XLinic\Framework\Core\Navigation;

use Illuminate\Support\Collection;
use XLinic\Framework\Core\Module\ModuleManifest;
use XLinic\Framework\Core\Security\PermissionRegistry;
use XLinic\Framework\Core\Tenancy\TenantManager;

class NavigationRegistry
{
    protected array $items = [];
    protected array $groups = [];

    public function __construct(
        protected PermissionRegistry $permissionRegistry,
        protected TenantManager $tenantManager
    ) {
    }

    /**
     * Register navigation items from a module manifest.
     */
    public function registerFromManifest(ModuleManifest $manifest): void
    {
        foreach ($manifest->navigation as $item) {
            $this->registerItem($item);
        }
    }

    /**
     * Register a navigation item.
     */
    public function registerItem(array $item): void
    {
        $item['id'] = $item['id'] ?? $this->generateId($item);
        $item['order'] = $item['order'] ?? 100;
        $item['visible'] = $item['visible'] ?? true;

        $this->items[$item['id']] = $item;

        // Register group if specified
        if (isset($item['group'])) {
            $this->registerGroup($item['group']);
        }
    }

    /**
     * Register a navigation group.
     */
    public function registerGroup(array $group): void
    {
        $group['id'] = $group['id'] ?? $group['name'];
        $group['order'] = $group['order'] ?? 100;
        $group['collapsible'] = $group['collapsible'] ?? true;
        $group['collapsed'] = $group['collapsed'] ?? false;

        $this->groups[$group['id']] = $group;
    }

    /**
     * Get navigation items for current user.
     */
    public function getItems($user = null): Collection
    {
        $user = $user ?? auth()->user();

        return collect($this->items)
            ->filter(fn($item) => $this->canUserSeeItem($item, $user))
            ->map(fn($item) => $this->processItem($item, $user))
            ->sortBy('order')
            ->values();
    }

    /**
     * Get navigation tree (grouped).
     */
    public function getTree($user = null): Collection
    {
        $user = $user ?? auth()->user();
        $items = $this->getItems($user);
        $groups = collect($this->groups)
            ->filter(fn($group) => $this->hasVisibleItemsInGroup($group['id'], $items))
            ->sortBy('order');

        // Group items by their group
        $grouped = $items->groupBy(fn($item) => $item['group']['id'] ?? '_ungrouped');

        return $groups->map(function ($group) use ($grouped) {
            return [
                'group' => $group,
                'items' => $grouped->get($group['id'], collect()),
            ];
        })->concat([
            // Add ungrouped items
            [
                'group' => null,
                'items' => $grouped->get('_ungrouped', collect()),
            ]
        ])->filter(fn($section) => $section['items']->isNotEmpty());
    }

    /**
     * Get breadcrumbs for a route.
     */
    public function getBreadcrumbs(string $routeName): Collection
    {
        $breadcrumbs = collect();
        $item = $this->findItemByRoute($routeName);

        if (!$item) {
            return $breadcrumbs;
        }

        // Build breadcrumb trail
        $this->buildBreadcrumbTrail($item, $breadcrumbs);

        return $breadcrumbs->reverse()->values();
    }

    /**
     * Find navigation item by route name.
     */
    public function findItemByRoute(string $routeName): ?array
    {
        return collect($this->items)->first(fn($item) =>
            ($item['route'] ?? null) === $routeName ||
            in_array($routeName, $item['active_routes'] ?? [])
        );
    }

    /**
     * Find navigation item by URL.
     */
    public function findItemByUrl(string $url): ?array
    {
        return collect($this->items)->first(fn($item) =>
            ($item['url'] ?? null) === $url ||
            str_starts_with($url, $item['url'] ?? '')
        );
    }

    /**
     * Get active navigation item.
     */
    public function getActiveItem(): ?array
    {
        $currentRoute = request()->route()?->getName();
        $currentUrl = request()->url();

        if ($currentRoute) {
            $item = $this->findItemByRoute($currentRoute);
            if ($item) {
                return $item;
            }
        }

        return $this->findItemByUrl($currentUrl);
    }

    /**
     * Check if user can see navigation item.
     */
    protected function canUserSeeItem(array $item, $user): bool
    {
        // Check visibility
        if (!($item['visible'] ?? true)) {
            return false;
        }

        // Check permissions
        if (isset($item['permission']) && $user) {
            return $this->permissionRegistry->userCan($user, $item['permission']);
        }

        // Check roles
        if (isset($item['role']) && $user) {
            return $this->permissionRegistry->userHasRole($user, $item['role']);
        }

        // Check custom visibility callback
        if (isset($item['visible_when']) && is_callable($item['visible_when'])) {
            return call_user_func($item['visible_when'], $user, $item);
        }

        return true;
    }

    /**
     * Process navigation item (resolve URLs, add metadata).
     */
    protected function processItem(array $item, $user): array
    {
        // Resolve URL
        if (isset($item['route'])) {
            $item['url'] = route($item['route'], $item['route_params'] ?? []);
        }

        // Add active state
        $item['active'] = $this->isItemActive($item);

        // Process children recursively
        if (isset($item['children'])) {
            $item['children'] = collect($item['children'])
                ->filter(fn($child) => $this->canUserSeeItem($child, $user))
                ->map(fn($child) => $this->processItem($child, $user))
                ->values();
        }

        // Add badge/count if callback provided
        if (isset($item['badge_callback']) && is_callable($item['badge_callback'])) {
            $item['badge'] = call_user_func($item['badge_callback'], $user, $item);
        }

        return $item;
    }

    /**
     * Check if navigation item is active.
     */
    protected function isItemActive(array $item): bool
    {
        $currentRoute = request()->route()?->getName();
        $currentUrl = request()->url();

        // Check exact route match
        if (isset($item['route']) && $item['route'] === $currentRoute) {
            return true;
        }

        // Check active routes
        if (isset($item['active_routes']) && in_array($currentRoute, $item['active_routes'])) {
            return true;
        }

        // Check URL match
        if (isset($item['url']) && str_starts_with($currentUrl, $item['url'])) {
            return true;
        }

        // Check children
        if (isset($item['children'])) {
            foreach ($item['children'] as $child) {
                if ($this->isItemActive($child)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if group has visible items.
     */
    protected function hasVisibleItemsInGroup(string $groupId, Collection $items): bool
    {
        return $items->some(fn($item) => ($item['group']['id'] ?? null) === $groupId);
    }

    /**
     * Build breadcrumb trail.
     */
    protected function buildBreadcrumbTrail(array $item, Collection $breadcrumbs): void
    {
        $breadcrumbs->push([
            'label' => $item['label'],
            'url' => $item['url'] ?? null,
            'active' => $this->isItemActive($item),
        ]);

        // Add parent breadcrumbs
        if (isset($item['parent'])) {
            $parent = $this->items[$item['parent']] ?? null;
            if ($parent) {
                $this->buildBreadcrumbTrail($parent, $breadcrumbs);
            }
        }
    }

    /**
     * Generate unique ID for navigation item.
     */
    protected function generateId(array $item): string
    {
        if (isset($item['route'])) {
            return 'nav_' . str_replace('.', '_', $item['route']);
        }

        if (isset($item['url'])) {
            return 'nav_' . str_replace(['/', '.'], '_', trim($item['url'], '/'));
        }

        return 'nav_' . uniqid();
    }

    /**
     * Update navigation item.
     */
    public function updateItem(string $id, array $updates): bool
    {
        if (!isset($this->items[$id])) {
            return false;
        }

        $this->items[$id] = array_merge($this->items[$id], $updates);
        return true;
    }

    /**
     * Remove navigation item.
     */
    public function removeItem(string $id): bool
    {
        if (!isset($this->items[$id])) {
            return false;
        }

        unset($this->items[$id]);
        return true;
    }

    /**
     * Get navigation statistics.
     */
    public function getStats(): array
    {
        $items = collect($this->items);

        return [
            'total_items' => $items->count(),
            'total_groups' => count($this->groups),
            'items_with_permissions' => $items->filter(fn($item) => isset($item['permission']))->count(),
            'items_with_roles' => $items->filter(fn($item) => isset($item['role']))->count(),
            'visible_items' => $items->filter(fn($item) => $item['visible'] ?? true)->count(),
            'items_by_group' => $items->groupBy(fn($item) => $item['group']['id'] ?? '_ungrouped')
                ->map->count(),
        ];
    }

    /**
     * Export navigation structure.
     */
    public function export(): array
    {
        return [
            'items' => $this->items,
            'groups' => $this->groups,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Import navigation structure.
     */
    public function import(array $data): void
    {
        if (isset($data['items'])) {
            foreach ($data['items'] as $item) {
                $this->registerItem($item);
            }
        }

        if (isset($data['groups'])) {
            foreach ($data['groups'] as $group) {
                $this->registerGroup($group);
            }
        }
    }

    /**
     * Clear all navigation items and groups.
     */
    public function clear(): void
    {
        $this->items = [];
        $this->groups = [];
    }

    /**
     * Get navigation as JSON for frontend.
     */
    public function toJson($user = null): string
    {
        return json_encode([
            'tree' => $this->getTree($user),
            'breadcrumbs' => $this->getBreadcrumbs(request()->route()?->getName() ?? ''),
            'active' => $this->getActiveItem(),
        ]);
    }
}