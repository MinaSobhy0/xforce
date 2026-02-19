<?php

namespace XLinic\Framework\Core\Navigation;

class NavigationItem
{
    /**
     * Create a new navigation item.
     */
    public function __construct(
        public string $id,
        public array $label = [],
        public ?string $icon = null,
        public ?string $route = null,
        public ?string $url = null,
        public array $routeParams = [],
        public array $activeRoutes = [],
        public ?NavigationGroup $group = null,
        public int $order = 100,
        public bool $visible = true,
        public ?string $permission = null,
        public ?string $role = null,
        public ?string $badge = null,
        public ?callable $badgeCallback = null,
        public ?string $badgeColor = null,
        public array $children = [],
        public ?string $parent = null,
        public ?callable $visibleWhen = null
    ) {
    }

    /**
     * Create a navigation item from array.
     */
    public static function make(array $data): static
    {
        return new static(
            id: $data['id'],
            label: $data['label'] ?? [],
            icon: $data['icon'] ?? null,
            route: $data['route'] ?? null,
            url: $data['url'] ?? null,
            routeParams: $data['route_params'] ?? [],
            activeRoutes: $data['active_routes'] ?? [],
            group: isset($data['group']) ? (is_array($data['group']) ? NavigationGroup::make($data['group']) : $data['group']) : null,
            order: $data['order'] ?? 100,
            visible: $data['visible'] ?? true,
            permission: $data['permission'] ?? null,
            role: $data['role'] ?? null,
            badge: $data['badge'] ?? null,
            badgeCallback: $data['badge_callback'] ?? null,
            badgeColor: $data['badge_color'] ?? null,
            children: $data['children'] ?? [],
            parent: $data['parent'] ?? null,
            visibleWhen: $data['visible_when'] ?? null
        );
    }

    /**
     * Set the item ID.
     */
    public function id(string $id): static
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Set the item label (translatable).
     */
    public function label(array|string $label): static
    {
        $this->label = is_string($label) ? ['en' => $label] : $label;
        return $this;
    }

    /**
     * Set the item icon.
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * Set the item route.
     */
    public function route(string $route, array $params = []): static
    {
        $this->route = $route;
        $this->routeParams = $params;
        return $this;
    }

    /**
     * Set the item URL.
     */
    public function url(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Set the routes that make this item active.
     */
    public function activeRoutes(array $routes): static
    {
        $this->activeRoutes = $routes;
        return $this;
    }

    /**
     * Set the navigation group.
     */
    public function group(NavigationGroup|array|string $group): static
    {
        if (is_string($group)) {
            $this->group = new NavigationGroup($group);
        } elseif (is_array($group)) {
            $this->group = NavigationGroup::make($group);
        } else {
            $this->group = $group;
        }
        return $this;
    }

    /**
     * Set the item order.
     */
    public function order(int $order): static
    {
        $this->order = $order;
        return $this;
    }

    /**
     * Set the item visibility.
     */
    public function visible(bool $visible = true): static
    {
        $this->visible = $visible;
        return $this;
    }

    /**
     * Set the permission required to see this item.
     */
    public function permission(string $permission): static
    {
        $this->permission = $permission;
        return $this;
    }

    /**
     * Set the role required to see this item.
     */
    public function role(string $role): static
    {
        $this->role = $role;
        return $this;
    }

    /**
     * Set a static badge.
     */
    public function badge(string $badge, string $color = 'primary'): static
    {
        $this->badge = $badge;
        $this->badgeColor = $color;
        return $this;
    }

    /**
     * Set a dynamic badge callback.
     */
    public function badgeCallback(callable $callback, string $color = 'primary'): static
    {
        $this->badgeCallback = $callback;
        $this->badgeColor = $color;
        return $this;
    }

    /**
     * Set child navigation items.
     */
    public function children(array $children): static
    {
        $this->children = $children;
        return $this;
    }

    /**
     * Set parent item ID.
     */
    public function parent(string $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * Set visibility condition.
     */
    public function visibleWhen(callable $callback): static
    {
        $this->visibleWhen = $callback;
        return $this;
    }

    /**
     * Get the translated label for current locale.
     */
    public function getLabel(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        if (isset($this->label[$locale])) {
            return $this->label[$locale];
        }

        // Fallback to English
        if (isset($this->label['en'])) {
            return $this->label['en'];
        }

        // Fallback to any available language
        if (!empty($this->label)) {
            return array_values($this->label)[0];
        }

        // Fallback to ID
        return str_replace(['_', '-'], ' ', ucwords($this->id, '_-'));
    }

    /**
     * Get the resolved URL for this item.
     */
    public function getUrl(): ?string
    {
        if ($this->url) {
            return $this->url;
        }

        if ($this->route) {
            try {
                return route($this->route, $this->routeParams);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Get the badge value (static or dynamic).
     */
    public function getBadge($user = null, array $context = []): ?string
    {
        if ($this->badgeCallback) {
            return call_user_func($this->badgeCallback, $user ?? auth()->user(), $context);
        }

        return $this->badge;
    }

    /**
     * Check if this item is currently active.
     */
    public function isActive(): bool
    {
        $currentRoute = request()->route()?->getName();
        $currentUrl = request()->url();

        // Check exact route match
        if ($this->route && $this->route === $currentRoute) {
            return true;
        }

        // Check active routes
        if (in_array($currentRoute, $this->activeRoutes)) {
            return true;
        }

        // Check URL match
        if ($this->url && str_starts_with($currentUrl, $this->url)) {
            return true;
        }

        // Check children
        foreach ($this->children as $child) {
            if ($child instanceof self && $child->isActive()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user can see this item.
     */
    public function canAccess($user = null, array $context = []): bool
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return false;
        }

        // Check visibility
        if (!$this->visible) {
            return false;
        }

        // Check custom visibility callback
        if ($this->visibleWhen && !call_user_func($this->visibleWhen, $user, $context)) {
            return false;
        }

        $permissionRegistry = app(\XLinic\Framework\Core\Security\PermissionRegistry::class);

        // Check permission if specified
        if ($this->permission) {
            return $permissionRegistry->userCan($user, $this->permission);
        }

        // Check role if specified
        if ($this->role) {
            return $permissionRegistry->userHasRole($user, $this->role);
        }

        return true;
    }

    /**
     * Convert to array representation.
     */
    public function toArray($user = null, array $context = []): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'translated_label' => $this->getLabel(),
            'icon' => $this->icon,
            'route' => $this->route,
            'url' => $this->getUrl(),
            'route_params' => $this->routeParams,
            'active_routes' => $this->activeRoutes,
            'group' => $this->group?->toArray(),
            'order' => $this->order,
            'visible' => $this->visible,
            'permission' => $this->permission,
            'role' => $this->role,
            'badge' => $this->getBadge($user, $context),
            'badge_color' => $this->badgeColor,
            'parent' => $this->parent,
            'is_active' => $this->isActive(),
            'can_access' => $this->canAccess($user, $context),
            'children' => array_map(fn($child) => $child instanceof self ? $child->toArray($user, $context) : $child, $this->children),
        ];
    }

    /**
     * Convert to JSON representation.
     */
    public function toJson($user = null, array $context = []): string
    {
        return json_encode($this->toArray($user, $context));
    }
}