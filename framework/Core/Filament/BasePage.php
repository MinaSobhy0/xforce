<?php

namespace XLinic\Framework\Core\Filament;

use Filament\Pages\Page;
use XLinic\Framework\Core\View\ViewExtensionManager;
use XLinic\Framework\Core\Security\PermissionRegistry;
use XLinic\Framework\Core\Tenancy\TenantManager;

abstract class BasePage extends Page
{
    /**
     * The page's navigation icon.
     */
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    /**
     * The page's navigation group.
     */
    protected static ?string $navigationGroup = null;

    /**
     * The page's navigation sort order.
     */
    protected static ?int $navigationSort = null;

    /**
     * The permission required to access this page.
     */
    protected static ?string $permission = null;

    /**
     * The role required to access this page.
     */
    protected static ?string $role = null;

    /**
     * Whether to apply extensions to this page.
     */
    protected static bool $applyExtensions = true;

    /**
     * Check if the current user can access this page.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        $permissionRegistry = app(PermissionRegistry::class);

        // Check permission if specified
        if (static::$permission) {
            return $permissionRegistry->userCan($user, static::$permission);
        }

        // Check role if specified
        if (static::$role) {
            return $permissionRegistry->userHasRole($user, static::$role);
        }

        // Default permission check based on page name
        $pageName = strtolower(str_replace('page', '', class_basename(static::class)));
        $defaultPermission = str_replace('-', '.', $pageName) . '.view';

        return $permissionRegistry->userCan($user, $defaultPermission);
    }

    /**
     * Get the page's navigation badge.
     */
    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    /**
     * Get the page's navigation badge color.
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    /**
     * Mount the page.
     */
    public function mount(): void
    {
        // Apply page extensions
        if (static::$applyExtensions) {
            $this->applyExtensions();
        }

        parent::mount();
    }

    /**
     * Apply extensions to the page.
     */
    protected function applyExtensions(): void
    {
        $extensionManager = app(ViewExtensionManager::class);
        $target = static::class;

        if ($extensionManager->hasExtensions('dashboard', $target)) {
            $extensionManager->applyDashboardExtensions($target, $this);
        }
    }

    /**
     * Get the current tenant.
     */
    protected function getCurrentTenant()
    {
        return app(TenantManager::class)->current();
    }

    /**
     * Get the current user.
     */
    protected function getCurrentUser()
    {
        return auth()->user();
    }

    /**
     * Check if user has permission.
     */
    protected function can(string $permission): bool
    {
        $user = $this->getCurrentUser();
        return $user && app(PermissionRegistry::class)->userCan($user, $permission);
    }

    /**
     * Check if user has role.
     */
    protected function hasRole(string $role): bool
    {
        $user = $this->getCurrentUser();
        return $user && app(PermissionRegistry::class)->userHasRole($user, $role);
    }

    /**
     * Get tenant-scoped data.
     */
    protected function getTenantData(string $model, array $options = []): array
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            return [];
        }

        $query = app($model)->newQuery();

        // Apply tenant scoping if model supports it
        if (method_exists(app($model), 'isTenantScoped') && app($model)->isTenantScoped()) {
            $query->where('tenant_id', $tenant->id);
        }

        // Apply filters if provided
        if (isset($options['filters'])) {
            foreach ($options['filters'] as $field => $value) {
                $query->where($field, $value);
            }
        }

        // Apply date range if provided
        if (isset($options['date_range'])) {
            $query->whereBetween('created_at', $options['date_range']);
        }

        // Apply custom query modifications
        if (isset($options['query']) && is_callable($options['query'])) {
            $query = $options['query']($query);
        }

        return [
            'query' => $query,
            'total' => $query->count(),
            'records' => $query->limit($options['limit'] ?? 10)->get(),
        ];
    }

    /**
     * Get breadcrumbs for the page.
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = parent::getBreadcrumbs();

        // Add tenant context if applicable
        $tenant = $this->getCurrentTenant();
        if ($tenant) {
            array_unshift($breadcrumbs, [
                'label' => $tenant->name,
                'url' => null,
            ]);
        }

        return $breadcrumbs;
    }

    /**
     * Get the page's header actions.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Get the page's actions.
     */
    protected function getActions(): array
    {
        return [];
    }

    /**
     * Get the page's widgets (for dashboard pages).
     */
    protected function getHeaderWidgets(): array
    {
        return [];
    }

    /**
     * Get the footer widgets (for dashboard pages).
     */
    protected function getFooterWidgets(): array
    {
        return [];
    }

    /**
     * Get the page's view data.
     */
    protected function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'currentTenant' => $this->getCurrentTenant(),
            'currentUser' => $this->getCurrentUser(),
        ]);
    }

    /**
     * Handle page refresh.
     */
    public function refresh(): void
    {
        $this->redirect(request()->url());
    }

    /**
     * Set page title with tenant context.
     */
    protected function getTitle(): string
    {
        $title = parent::getTitle();
        $tenant = $this->getCurrentTenant();

        if ($tenant && $this->shouldShowTenantInTitle()) {
            $title .= " - {$tenant->name}";
        }

        return $title;
    }

    /**
     * Determine if tenant name should be shown in title.
     */
    protected function shouldShowTenantInTitle(): bool
    {
        return true;
    }

    /**
     * Get the page's heading.
     */
    protected function getHeading(): string
    {
        return parent::getHeading();
    }

    /**
     * Get the page's subheading.
     */
    protected function getSubheading(): ?string
    {
        $tenant = $this->getCurrentTenant();

        if ($tenant && $this->shouldShowTenantInSubheading()) {
            return "Current workspace: {$tenant->name}";
        }

        return parent::getSubheading();
    }

    /**
     * Determine if tenant info should be shown in subheading.
     */
    protected function shouldShowTenantInSubheading(): bool
    {
        return false;
    }

    /**
     * Get common stats for dashboard pages.
     */
    protected function getStatsOverview(): array
    {
        return [];
    }

    /**
     * Get chart data for dashboard pages.
     */
    protected function getChartData(): array
    {
        return [];
    }

    /**
     * Get recent activities for dashboard pages.
     */
    protected function getRecentActivities(int $limit = 10): array
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            return [];
        }

        return \Spatie\Activitylog\Models\Activity::query()
            ->where('properties->tenant_id', $tenant->id)
            ->latest()
            ->limit($limit)
            ->with('causer')
            ->get()
            ->toArray();
    }

    /**
     * Handle notification sending.
     */
    protected function sendNotification(string $title, string $body = '', string $type = 'info'): void
    {
        $notification = \Filament\Notifications\Notification::make()
            ->title($title)
            ->body($body);

        match ($type) {
            'success' => $notification->success(),
            'warning' => $notification->warning(),
            'danger' => $notification->danger(),
            default => $notification->info(),
        };

        $notification->send();
    }

    /**
     * Export data (common functionality).
     */
    protected function exportData(array $data, string $filename, string $format = 'xlsx'): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        // This would integrate with your preferred export library
        // For now, this is a placeholder
        throw new \Exception('Export functionality not implemented');
    }

    /**
     * Apply filters to query.
     */
    protected function applyFilters($query, array $filters): \Illuminate\Database\Eloquent\Builder
    {
        foreach ($filters as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        return $query;
    }

    /**
     * Get filtered records count.
     */
    protected function getFilteredCount($query): int
    {
        return $query->count();
    }
}