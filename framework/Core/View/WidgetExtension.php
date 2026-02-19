<?php

namespace XLinic\Framework\Core\View;

abstract class WidgetExtension
{
    /**
     * Extend the widget.
     */
    abstract public function extend($widget): void;

    /**
     * Get the priority for this extension.
     */
    public function getPriority(): int
    {
        return 0;
    }

    /**
     * Check if this extension should be applied.
     */
    public function shouldApply($widget): bool
    {
        return true;
    }

    /**
     * Add data to widget.
     */
    protected function addData($widget, array $data): void
    {
        if (method_exists($widget, 'data')) {
            $existingData = $widget->getData() ?? [];
            $widget->data(array_merge($existingData, $data));
        }
    }

    /**
     * Modify widget data.
     */
    protected function modifyData($widget, callable $callback): void
    {
        if (method_exists($widget, 'data')) {
            $data = $widget->getData() ?? [];
            $widget->data($callback($data));
        }
    }

    /**
     * Set widget polling interval.
     */
    protected function setPolling($widget, string $interval): void
    {
        if (method_exists($widget, 'pollingInterval')) {
            $widget->pollingInterval($interval);
        }
    }

    /**
     * Add actions to widget.
     */
    protected function addActions($widget, array $actions): void
    {
        if (method_exists($widget, 'actions')) {
            $existingActions = $widget->getActions() ?? [];
            $widget->actions(array_merge($existingActions, $actions));
        }
    }

    /**
     * Add header actions to widget.
     */
    protected function addHeaderActions($widget, array $actions): void
    {
        if (method_exists($widget, 'headerActions')) {
            $existingActions = $widget->getHeaderActions() ?? [];
            $widget->headerActions(array_merge($existingActions, $actions));
        }
    }

    /**
     * Set widget height.
     */
    protected function setHeight($widget, string $height): void
    {
        if (method_exists($widget, 'height')) {
            $widget->height($height);
        }
    }

    /**
     * Set widget width/span.
     */
    protected function setSpan($widget, int $span): void
    {
        if (method_exists($widget, 'columnSpan')) {
            $widget->columnSpan($span);
        }
    }

    /**
     * Set widget description.
     */
    protected function setDescription($widget, string $description): void
    {
        if (method_exists($widget, 'description')) {
            $widget->description($description);
        }
    }

    /**
     * Make widget collapsible.
     */
    protected function makeCollapsible($widget, bool $collapsed = false): void
    {
        if (method_exists($widget, 'collapsible')) {
            $widget->collapsible($collapsed);
        }
    }

    /**
     * Set widget color.
     */
    protected function setColor($widget, string $color): void
    {
        if (method_exists($widget, 'color')) {
            $widget->color($color);
        }
    }

    /**
     * Add refresh action to widget.
     */
    protected function addRefreshAction($widget): void
    {
        $this->addHeaderActions($widget, [
            \Filament\Actions\Action::make('refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn() => $widget->refresh())
        ]);
    }

    /**
     * Add export action to widget.
     */
    protected function addExportAction($widget, callable $exportCallback): void
    {
        $this->addHeaderActions($widget, [
            \Filament\Actions\Action::make('export')
                ->icon('heroicon-o-arrow-down-tray')
                ->action($exportCallback)
        ]);
    }

    /**
     * Add full-screen action to widget.
     */
    protected function addFullScreenAction($widget): void
    {
        $this->addHeaderActions($widget, [
            \Filament\Actions\Action::make('fullscreen')
                ->icon('heroicon-o-arrows-pointing-out')
                ->action('toggleFullscreen')
        ]);
    }

    /**
     * Get tenant-scoped data for widget.
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
     * Get stats data for widget.
     */
    protected function getStatsData(string $model, array $options = []): array
    {
        $data = $this->getTenantData($model, $options);
        $query = $data['query'];

        return [
            'total' => $data['total'],
            'today' => (clone $query)->whereDate('created_at', today())->count(),
            'yesterday' => (clone $query)->whereDate('created_at', yesterday())->count(),
            'this_week' => (clone $query)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'last_week' => (clone $query)->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])->count(),
            'this_month' => (clone $query)->whereMonth('created_at', now()->month)->count(),
            'last_month' => (clone $query)->whereMonth('created_at', now()->subMonth()->month)->count(),
            'this_year' => (clone $query)->whereYear('created_at', now()->year)->count(),
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
     * Get chart data for widget.
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
     * Get current user for context.
     */
    protected function getCurrentUser()
    {
        return auth()->user();
    }

    /**
     * Get current tenant for context.
     */
    protected function getCurrentTenant()
    {
        return app(\XLinic\Framework\Core\Tenancy\TenantManager::class)->current();
    }

    /**
     * Check user permission.
     */
    protected function can(string $permission): bool
    {
        $user = $this->getCurrentUser();
        return $user && app(\XLinic\Framework\Core\Security\PermissionRegistry::class)->userCan($user, $permission);
    }

    /**
     * Check user role.
     */
    protected function hasRole(string $role): bool
    {
        $user = $this->getCurrentUser();
        return $user && app(\XLinic\Framework\Core\Security\PermissionRegistry::class)->userHasRole($user, $role);
    }

    /**
     * Apply conditional logic based on user permissions.
     */
    protected function ifCan(string $permission, callable $callback): void
    {
        if ($this->can($permission)) {
            $callback();
        }
    }

    /**
     * Apply conditional logic based on user roles.
     */
    protected function ifHasRole(string $role, callable $callback): void
    {
        if ($this->hasRole($role)) {
            $callback();
        }
    }
}