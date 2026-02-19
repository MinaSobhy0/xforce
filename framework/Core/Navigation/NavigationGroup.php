<?php

namespace XLinic\Framework\Core\Navigation;

class NavigationGroup
{
    /**
     * Create a new navigation group.
     */
    public function __construct(
        public string $id,
        public array $label = [],
        public ?string $icon = null,
        public int $order = 100,
        public bool $collapsible = true,
        public bool $collapsed = false,
        public ?string $permission = null,
        public ?string $role = null
    ) {
    }

    /**
     * Create a navigation group from array.
     */
    public static function make(array $data): static
    {
        return new static(
            id: $data['id'],
            label: $data['label'] ?? [],
            icon: $data['icon'] ?? null,
            order: $data['order'] ?? 100,
            collapsible: $data['collapsible'] ?? true,
            collapsed: $data['collapsed'] ?? false,
            permission: $data['permission'] ?? null,
            role: $data['role'] ?? null
        );
    }

    /**
     * Set the group ID.
     */
    public function id(string $id): static
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Set the group label (translatable).
     */
    public function label(array|string $label): static
    {
        $this->label = is_string($label) ? ['en' => $label] : $label;
        return $this;
    }

    /**
     * Set the group icon.
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * Set the group order.
     */
    public function order(int $order): static
    {
        $this->order = $order;
        return $this;
    }

    /**
     * Set if the group is collapsible.
     */
    public function collapsible(bool $collapsible = true): static
    {
        $this->collapsible = $collapsible;
        return $this;
    }

    /**
     * Set if the group is collapsed by default.
     */
    public function collapsed(bool $collapsed = true): static
    {
        $this->collapsed = $collapsed;
        return $this;
    }

    /**
     * Set the permission required to see this group.
     */
    public function permission(string $permission): static
    {
        $this->permission = $permission;
        return $this;
    }

    /**
     * Set the role required to see this group.
     */
    public function role(string $role): static
    {
        $this->role = $role;
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
        return str_replace('_', ' ', ucwords($this->id, '_'));
    }

    /**
     * Check if user can see this group.
     */
    public function canAccess($user = null): bool
    {
        $user = $user ?? auth()->user();

        if (!$user) {
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
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'translated_label' => $this->getLabel(),
            'icon' => $this->icon,
            'order' => $this->order,
            'collapsible' => $this->collapsible,
            'collapsed' => $this->collapsed,
            'permission' => $this->permission,
            'role' => $this->role,
        ];
    }

    /**
     * Convert to JSON representation.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Create predefined dashboard group.
     */
    public static function dashboard(): static
    {
        return new static(
            id: 'dashboard',
            label: ['en' => 'Dashboard', 'ar' => 'لوحة التحكم'],
            icon: 'heroicon-o-home',
            order: 10
        );
    }

    /**
     * Create predefined CRM group.
     */
    public static function crm(): static
    {
        return new static(
            id: 'crm',
            label: ['en' => 'CRM', 'ar' => 'إدارة العملاء'],
            icon: 'heroicon-o-users',
            order: 20
        );
    }

    /**
     * Create predefined operations group.
     */
    public static function operations(): static
    {
        return new static(
            id: 'operations',
            label: ['en' => 'Operations', 'ar' => 'العمليات'],
            icon: 'heroicon-o-calendar-days',
            order: 30
        );
    }

    /**
     * Create predefined sales group.
     */
    public static function sales(): static
    {
        return new static(
            id: 'sales',
            label: ['en' => 'Sales', 'ar' => 'المبيعات'],
            icon: 'heroicon-o-currency-dollar',
            order: 40
        );
    }

    /**
     * Create predefined financial group.
     */
    public static function financial(): static
    {
        return new static(
            id: 'financial',
            label: ['en' => 'Financial', 'ar' => 'المالية'],
            icon: 'heroicon-o-banknotes',
            order: 50
        );
    }

    /**
     * Create predefined inventory group.
     */
    public static function inventory(): static
    {
        return new static(
            id: 'inventory',
            label: ['en' => 'Inventory', 'ar' => 'المخزون'],
            icon: 'heroicon-o-cube',
            order: 60
        );
    }

    /**
     * Create predefined marketing group.
     */
    public static function marketing(): static
    {
        return new static(
            id: 'marketing',
            label: ['en' => 'Marketing', 'ar' => 'التسويق'],
            icon: 'heroicon-o-megaphone',
            order: 70
        );
    }

    /**
     * Create predefined HR group.
     */
    public static function hr(): static
    {
        return new static(
            id: 'hr',
            label: ['en' => 'Human Resources', 'ar' => 'الموارد البشرية'],
            icon: 'heroicon-o-user-group',
            order: 80
        );
    }

    /**
     * Create predefined reports group.
     */
    public static function reports(): static
    {
        return new static(
            id: 'reports',
            label: ['en' => 'Reports', 'ar' => 'التقارير'],
            icon: 'heroicon-o-chart-bar',
            order: 90
        );
    }

    /**
     * Create predefined settings group.
     */
    public static function settings(): static
    {
        return new static(
            id: 'settings',
            label: ['en' => 'Settings', 'ar' => 'الإعدادات'],
            icon: 'heroicon-o-cog-6-tooth',
            order: 100
        );
    }
}