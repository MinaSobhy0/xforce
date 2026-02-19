<?php

namespace XLinic\Framework\Core\Filament;

use Filament\Widgets\Widget;
use XLinic\Framework\Core\View\ViewExtensionManager;
use XLinic\Framework\Core\Security\PermissionRegistry;
use XLinic\Framework\Core\Tenancy\TenantManager;

abstract class BaseWidget extends Widget
{
    /**
     * The widget's column span.
     */
    protected int | string | array $columnSpan = 1;

    /**
     * The widget's sort order.
     */
    protected static ?int $sort = null;

    /**
     * The permission required to view this widget.
     */
    protected static ?string $permission = null;

    /**
     * The role required to view this widget.
     */
    protected static ?string $role = null;

    /**
     * Whether to apply extensions to this widget.
     */
    protected static bool $applyExtensions = true;

    /**
     * The widget's polling interval (null to disable).
     */
    protected static ?string $pollingInterval = null;

    /**
     * The widget's maximum height.
     */
    protected static ?string $maxHeight = null;

    /**
     * Whether the widget can be refreshed.
     */
    protected static bool $isRefreshable = true;

    /**
     * Check if the current user can view this widget.
     */
    public static function canView(): bool
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

        // Default to allowing access if no specific permission/role is set
        return true;
    }

    /**
     * Mount the widget.
     */
    public function mount(): void
    {
        // Apply widget extensions
        if (static::$applyExtensions) {
            $this->applyExtensions();
        }

        parent::mount();
    }

    /**
     * Apply extensions to the widget.
     */
    protected function applyExtensions(): void
    {
        $extensionManager = app(ViewExtensionManager::class);
        $target = static::class;

        if ($extensionManager->hasExtensions('widget', $target)) {
            $extensionManager->applyWidgetExtensions($target, $this);
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
     * Get stats data with comparison.
     */
    protected function getStatsData(string $model, array $options = []): array
    {
        $data = $this->getTenantData($model, $options);
        $query = $data['query'];

        $current = [
            'total' => $data['total'],
            'today' => (clone $query)->whereDate('created_at', today())->count(),
            'this_week' => (clone $query)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'this_month' => (clone $query)->whereMonth('created_at', now()->month)->count(),
        ];

        $previous = [
            'yesterday' => (clone $query)->whereDate('created_at', yesterday())->count(),
            'last_week' => (clone $query)->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])->count(),
            'last_month' => (clone $query)->whereMonth('created_at', now()->subMonth()->month)->count(),
        ];

        return [
            'current' => $current,
            'previous' => $previous,
            'changes' => [
                'daily' => $this->calculatePercentageChange($current['today'], $previous['yesterday']),
                'weekly' => $this->calculatePercentageChange($current['this_week'], $previous['last_week']),
                'monthly' => $this->calculatePercentageChange($current['this_month'], $previous['last_month']),
            ],
        ];
    }

    /**
     * Calculate percentage change.
     */
    protected function calculatePercentageChange(int $current, int $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Get chart data for different periods.
     */
    protected function getChartData(string $model, string $period = 'week', array $options = []): array
    {
        $data = $this->getTenantData($model, $options);
        $query = $data['query'];

        return match($period) {
            'day' => $this->getDailyChartData($query),
            'week' => $this->getWeeklyChartData($query),
            'month' => $this->getMonthlyChartData($query),
            'year' => $this->getYearlyChartData($query),
            default => $this->getWeeklyChartData($query),
        };
    }

    /**
     * Get daily chart data.
     */
    protected function getDailyChartData($query): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = (clone $query)->whereDate('created_at', $date)->count();
            $data[] = [
                'label' => $date->format('M d'),
                'value' => $count,
            ];
        }
        return $data;
    }

    /**
     * Get weekly chart data.
     */
    protected function getWeeklyChartData($query): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $startOfWeek = now()->subWeeks($i)->startOfWeek();
            $endOfWeek = now()->subWeeks($i)->endOfWeek();
            $count = (clone $query)->whereBetween('created_at', [$startOfWeek, $endOfWeek])->count();
            $data[] = [
                'label' => $startOfWeek->format('M d'),
                'value' => $count,
            ];
        }
        return $data;
    }

    /**
     * Get monthly chart data.
     */
    protected function getMonthlyChartData($query): array
    {
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $count = (clone $query)->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)->count();
            $data[] = [
                'label' => $month->format('M Y'),
                'value' => $count,
            ];
        }
        return $data;
    }

    /**
     * Get yearly chart data.
     */
    protected function getYearlyChartData($query): array
    {
        $data = [];
        for ($i = 4; $i >= 0; $i--) {
            $year = now()->subYears($i);
            $count = (clone $query)->whereYear('created_at', $year->year)->count();
            $data[] = [
                'label' => $year->format('Y'),
                'value' => $count,
            ];
        }
        return $data;
    }

    /**
     * Get color based on trend.
     */
    protected function getTrendColor(float $changePercentage): string
    {
        if ($changePercentage > 0) {
            return 'success';
        } elseif ($changePercentage < 0) {
            return 'danger';
        }

        return 'gray';
    }

    /**
     * Get trend icon based on percentage change.
     */
    protected function getTrendIcon(float $changePercentage): string
    {
        if ($changePercentage > 0) {
            return 'heroicon-o-arrow-trending-up';
        } elseif ($changePercentage < 0) {
            return 'heroicon-o-arrow-trending-down';
        }

        return 'heroicon-o-minus';
    }

    /**
     * Format number for display.
     */
    protected function formatNumber(int $number): string
    {
        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        }

        return (string) $number;
    }

    /**
     * Format percentage for display.
     */
    protected function formatPercentage(float $percentage): string
    {
        $sign = $percentage > 0 ? '+' : '';
        return $sign . number_format($percentage, 1) . '%';
    }

    /**
     * Get recent records for display.
     */
    protected function getRecentRecords(string $model, int $limit = 5, array $options = []): array
    {
        $data = $this->getTenantData($model, array_merge($options, ['limit' => $limit]));

        return $data['records']->map(function ($record) {
            return [
                'id' => $record->id,
                'title' => method_exists($record, 'getDisplayName') ? $record->getDisplayName() : $record->name ?? $record->title ?? "#{$record->id}",
                'subtitle' => $record->created_at?->format('M j, Y g:i A'),
                'url' => method_exists($record, 'getUrl') ? $record->getUrl() : null,
            ];
        })->toArray();
    }

    /**
     * Cache data for performance.
     */
    protected function getCachedData(string $key, callable $callback, int $minutes = 5): mixed
    {
        $tenant = $this->getCurrentTenant();
        $cacheKey = "widget:{$tenant?->id}:{$key}";

        return cache()->remember($cacheKey, now()->addMinutes($minutes), $callback);
    }

    /**
     * Clear widget cache.
     */
    protected function clearCache(string $key = null): void
    {
        $tenant = $this->getCurrentTenant();

        if ($key) {
            $cacheKey = "widget:{$tenant?->id}:{$key}";
            cache()->forget($cacheKey);
        } else {
            // Clear all widget cache for this tenant
            $pattern = "widget:{$tenant?->id}:*";
            // This would require a cache implementation that supports pattern clearing
            cache()->tags(["widget-{$tenant?->id}"])->flush();
        }
    }

    /**
     * Refresh widget data.
     */
    public function refresh(): void
    {
        $this->clearCache();
        $this->dispatch('$refresh');
    }

    /**
     * Get the widget's data for API endpoints.
     */
    public function getData(): array
    {
        return [];
    }

    /**
     * Handle real-time updates.
     */
    protected function listenToEvents(array $events): void
    {
        foreach ($events as $event => $method) {
            $this->getListeners()[$event] = $method;
        }
    }

    /**
     * Get widget metadata.
     */
    protected function getMetadata(): array
    {
        return [
            'title' => static::class,
            'last_updated' => now()->toISOString(),
            'tenant_id' => $this->getCurrentTenant()?->id,
            'user_id' => $this->getCurrentUser()?->id,
        ];
    }
}