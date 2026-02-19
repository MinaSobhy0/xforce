<?php

namespace XLinic\Framework\Core\View;

abstract class TableExtension
{
    /**
     * Extend the table.
     */
    abstract public function extend($table): void;

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
    public function shouldApply($table): bool
    {
        return true;
    }

    /**
     * Add column to table.
     */
    protected function addColumn($table, $column): void
    {
        if (method_exists($table, 'columns')) {
            $columns = $table->getColumns();
            $columns[] = $column;
            $table->columns($columns);
        }
    }

    /**
     * Add columns at specific position.
     */
    protected function addColumnsAt($table, int $position, array $columns): void
    {
        if (method_exists($table, 'columns')) {
            $existingColumns = $table->getColumns();
            array_splice($existingColumns, $position, 0, $columns);
            $table->columns($existingColumns);
        }
    }

    /**
     * Add columns before a specific column.
     */
    protected function addColumnsBefore($table, string $columnName, array $columns): void
    {
        if (method_exists($table, 'columns')) {
            $existingColumns = $table->getColumns();
            $position = $this->findColumnPosition($existingColumns, $columnName);

            if ($position !== -1) {
                array_splice($existingColumns, $position, 0, $columns);
                $table->columns($existingColumns);
            }
        }
    }

    /**
     * Add columns after a specific column.
     */
    protected function addColumnsAfter($table, string $columnName, array $columns): void
    {
        if (method_exists($table, 'columns')) {
            $existingColumns = $table->getColumns();
            $position = $this->findColumnPosition($existingColumns, $columnName);

            if ($position !== -1) {
                array_splice($existingColumns, $position + 1, 0, $columns);
                $table->columns($existingColumns);
            }
        }
    }

    /**
     * Remove column from table.
     */
    protected function removeColumn($table, string $columnName): void
    {
        if (method_exists($table, 'columns')) {
            $columns = $table->getColumns();
            $position = $this->findColumnPosition($columns, $columnName);

            if ($position !== -1) {
                array_splice($columns, $position, 1);
                $table->columns($columns);
            }
        }
    }

    /**
     * Modify existing column.
     */
    protected function modifyColumn($table, string $columnName, callable $callback): void
    {
        if (method_exists($table, 'columns')) {
            $columns = $table->getColumns();
            $position = $this->findColumnPosition($columns, $columnName);

            if ($position !== -1 && isset($columns[$position])) {
                $columns[$position] = $callback($columns[$position]);
                $table->columns($columns);
            }
        }
    }

    /**
     * Find column position in table.
     */
    protected function findColumnPosition(array $columns, string $columnName): int
    {
        foreach ($columns as $index => $column) {
            if (method_exists($column, 'getName') && $column->getName() === $columnName) {
                return $index;
            }
        }

        return -1;
    }

    /**
     * Add filters to table.
     */
    protected function addFilters($table, array $filters): void
    {
        if (method_exists($table, 'filters')) {
            $existingFilters = $table->getFilters() ?? [];
            $table->filters(array_merge($existingFilters, $filters));
        }
    }

    /**
     * Add actions to table.
     */
    protected function addActions($table, array $actions): void
    {
        if (method_exists($table, 'actions')) {
            $existingActions = $table->getActions() ?? [];
            $table->actions(array_merge($existingActions, $actions));
        }
    }

    /**
     * Add bulk actions to table.
     */
    protected function addBulkActions($table, array $actions): void
    {
        if (method_exists($table, 'bulkActions')) {
            $existingActions = $table->getBulkActions() ?? [];
            $table->bulkActions(array_merge($existingActions, $actions));
        }
    }

    /**
     * Add header actions to table.
     */
    protected function addHeaderActions($table, array $actions): void
    {
        if (method_exists($table, 'headerActions')) {
            $existingActions = $table->getHeaderActions() ?? [];
            $table->headerActions(array_merge($existingActions, $actions));
        }
    }

    /**
     * Modify query for the table.
     */
    protected function modifyQuery($table, callable $callback): void
    {
        if (method_exists($table, 'modifyQueryUsing')) {
            $table->modifyQueryUsing($callback);
        }
    }

    /**
     * Add global search columns.
     */
    protected function addSearchableColumns($table, array $columns): void
    {
        if (method_exists($table, 'searchableColumns')) {
            $existing = $table->getSearchableColumns() ?? [];
            $table->searchableColumns(array_merge($existing, $columns));
        }
    }

    /**
     * Configure default sorting.
     */
    protected function setDefaultSort($table, string $column, string $direction = 'asc'): void
    {
        if (method_exists($table, 'defaultSort')) {
            $table->defaultSort($column, $direction);
        }
    }

    /**
     * Set records per page options.
     */
    protected function setRecordsPerPageOptions($table, array $options): void
    {
        if (method_exists($table, 'recordsPerPageSelectOptions')) {
            $table->recordsPerPageSelectOptions($options);
        }
    }

    /**
     * Enable/disable features.
     */
    protected function toggleFeature($table, string $feature, bool $enabled = true): void
    {
        $method = $enabled ? $feature : "disable" . ucfirst($feature);

        if (method_exists($table, $method)) {
            $table->{$method}();
        }
    }

    /**
     * Add custom view for empty state.
     */
    protected function setEmptyState($table, string $view, array $data = []): void
    {
        if (method_exists($table, 'emptyStateView')) {
            $table->emptyStateView($view, $data);
        }
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
     * Apply tenant scoping to query.
     */
    protected function applyTenantScoping($table): void
    {
        $this->modifyQuery($table, function ($query) {
            $tenant = $this->getCurrentTenant();
            if ($tenant && method_exists($query->getModel(), 'isTenantScoped')) {
                if ($query->getModel()->isTenantScoped()) {
                    $query->where('tenant_id', $tenant->id);
                }
            }
            return $query;
        });
    }
}