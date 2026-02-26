<?php

namespace XLinic\Framework\Core\Model\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasTenancy
{
    /**
     * Boot the HasTenancy trait.
     */
    protected static function bootHasTenancy(): void
    {
        // Apply tenant scoping automatically
        static::addGlobalScope('tenant', function (Builder $builder) {
            // Skip tenant scoping for platform/super-admin panel
            if (request()->is('platform*') || request()->is('platform/*')) {
                return;
            }

            $tenantManager = app(\XLinic\Framework\Core\Tenancy\TenantManager::class);
            $currentTenant = $tenantManager->current();

            // Check if we're on a tenant schema (not public)
            // In schema-per-tenant architecture, tenant tables don't need tenant_id filter
            $searchPath = 'public';
            try {
                $result = \DB::select('SHOW search_path');
                $searchPath = $result[0]->search_path ?? 'public';
            } catch (\Exception $e) {}

            // If we're on a tenant schema (contains tenant_), skip tenant_id filtering
            // The data is already isolated by schema
            if (str_contains($searchPath, 'tenant_')) {
                return;
            }

            // Only apply tenant_id filter if:
            // 1. We're on public schema
            // 2. We have a current tenant
            // 3. The model has tenant_id in fillable
            $hasTenantIdField = in_array('tenant_id', (new static())->getFillable());

            if ($currentTenant && $hasTenantIdField) {
                // Qualify tenant_id with table name to avoid ambiguity in joins
                $table = (new static())->getTable();
                $builder->where("{$table}.tenant_id", $currentTenant->id);
            }
        });

        // Auto-set tenant_id on creating
        // Always set tenant_id if available, even on tenant schemas
        // This maintains data consistency for cross-schema queries
        static::creating(function ($model) {
            if (in_array('tenant_id', $model->getFillable()) && !$model->tenant_id) {
                $tenantManager = app(\XLinic\Framework\Core\Tenancy\TenantManager::class);
                $currentTenant = $tenantManager->current();

                if ($currentTenant) {
                    $model->tenant_id = $currentTenant->id;
                }
            }
        });
    }

    /**
     * Scope to current tenant.
     */
    public function scopeCurrentTenant(Builder $query): Builder
    {
        $tenantManager = app(\XLinic\Framework\Core\Tenancy\TenantManager::class);
        $currentTenant = $tenantManager->current();

        if ($currentTenant) {
            $table = $this->getTable();
            return $query->where("{$table}.tenant_id", $currentTenant->id);
        }

        return $query;
    }

    /**
     * Scope to specific tenant.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        $table = $this->getTable();
        return $query->where("{$table}.tenant_id", $tenantId);
    }

    /**
     * Check if this model belongs to the given tenant.
     */
    public function belongsToTenant(int $tenantId): bool
    {
        return $this->tenant_id === $tenantId;
    }
}