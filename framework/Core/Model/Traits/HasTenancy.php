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

            if ($currentTenant && in_array('tenant_id', (new static())->getFillable())) {
                $builder->where('tenant_id', $currentTenant->id);
            }
        });

        // Auto-set tenant_id on creating
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
            return $query->where('tenant_id', $currentTenant->id);
        }

        return $query;
    }

    /**
     * Scope to specific tenant.
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Check if this model belongs to the given tenant.
     */
    public function belongsToTenant(string $tenantId): bool
    {
        return $this->tenant_id === $tenantId;
    }
}