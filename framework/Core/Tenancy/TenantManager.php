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

        // In actual implementation, this would resolve from tenancy context
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
     * Find a tenant by ID or identifier.
     */
    public function findTenant(string $identifier): ?object
    {
        if (!class_exists(\App\Models\Tenant::class)) {
            return null;
        }

        // Try to find by ID first
        $tenant = \App\Models\Tenant::find($identifier);

        if (!$tenant) {
            // Try to find by slug or code
            $tenant = \App\Models\Tenant::where('slug', $identifier)
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
        if (!class_exists(\App\Models\Tenant::class)) {
            return null;
        }

        return \App\Models\Tenant::where('slug', $slug)->first();
    }

    /**
     * Find a tenant by a specific field.
     */
    public function findTenantBy(string $field, mixed $value): ?object
    {
        if (!class_exists(\App\Models\Tenant::class)) {
            return null;
        }

        return \App\Models\Tenant::where($field, $value)->first();
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
        if (!class_exists(\App\Models\Tenant::class)) {
            return collect();
        }

        return \App\Models\Tenant::all();
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