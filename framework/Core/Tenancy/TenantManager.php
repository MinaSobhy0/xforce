<?php

namespace XLinic\Framework\Core\Tenancy;

class TenantManager
{
    protected ?object $currentTenant = null;

    /**
     * Get the current tenant.
     */
    public function current(): ?object
    {
        if ($this->currentTenant) {
            return $this->currentTenant;
        }

        // Fallback to app container instance (set by IdentifyTenant middleware)
        if (app()->bound('currentTenant')) {
            return app('currentTenant');
        }

        return null;
    }

    /**
     * Set the current tenant.
     */
    public function setCurrent(?object $tenant): void
    {
        $this->currentTenant = $tenant;
    }

    /**
     * Set the current tenant (alias for setCurrent).
     */
    public function setCurrentTenant(?object $tenant): void
    {
        $this->setCurrent($tenant);
    }

    /**
     * Clear the current tenant context.
     */
    public function clearCurrentTenant(): void
    {
        $this->currentTenant = null;
    }

    /**
     * Get the Tenant model class.
     */
    protected function getTenantModel(): ?string
    {
        // Check multiple possible locations for Tenant model
        $possibleModels = [
            \Modules\Core\Models\Tenant::class,
            \App\Models\Tenant::class,
        ];

        foreach ($possibleModels as $model) {
            if (class_exists($model)) {
                return $model;
            }
        }

        return null;
    }

    /**
     * Find a tenant by ID or identifier.
     */
    public function findTenant(string $identifier): ?object
    {
        $model = $this->getTenantModel();
        if (!$model) {
            return null;
        }

        // Try to find by ID first
        $tenant = $model::find($identifier);

        if (!$tenant) {
            // Try to find by slug or code
            $tenant = $model::where('slug', $identifier)
                ->orWhere('code', $identifier)
                ->first();
        }

        return $tenant;
    }

    /**
     * Find a tenant by slug.
     */
    public function findTenantBySlug(string $slug): ?object
    {
        $model = $this->getTenantModel();
        if (!$model) {
            return null;
        }

        return $model::where('slug', $slug)->first();
    }

    /**
     * Find a tenant by a specific field.
     */
    public function findTenantBy(string $field, mixed $value): ?object
    {
        $model = $this->getTenantModel();
        if (!$model) {
            return null;
        }

        return $model::where($field, $value)->first();
    }

    /**
     * Execute code within a tenant context.
     */
    public function runForTenant(?object $tenant, callable $callback): mixed
    {
        $previous = $this->currentTenant;

        try {
            $this->setCurrent($tenant);
            return $callback();
        } finally {
            $this->setCurrent($previous);
        }
    }

    /**
     * Get all tenants (platform admin only).
     */
    public function getAllTenants(): \Illuminate\Database\Eloquent\Collection
    {
        $model = $this->getTenantModel();
        if (!$model) {
            return collect();
        }

        return $model::all();
    }

    /**
     * Check if we're in tenant context.
     */
    public function inTenantContext(): bool
    {
        return $this->currentTenant !== null;
    }

    /**
     * Get the current tenant ID.
     */
    public function getCurrentTenantId(): ?string
    {
        return $this->currentTenant?->id;
    }
}