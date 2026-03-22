<?php

namespace XLinic\Framework\Core\Model\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait EnforcesTenantLimits
 *
 * SECURITY: Enforces tenant resource limits at the model level.
 * This prevents bypassing HTTP middleware when creating records programmatically.
 *
 * Usage: Add this trait to models that have tenant limits (User, Branch, Patient)
 * and define the $tenantLimitField property.
 */
trait EnforcesTenantLimits
{
    /**
     * Boot the trait - register creating event.
     */
    public static function bootEnforcesTenantLimits(): void
    {
        static::creating(function (Model $model) {
            $model->enforceLimit();
        });
    }

    /**
     * Get the tenant limit field name for this model.
     * Override in model if needed.
     */
    protected function getTenantLimitField(): ?string
    {
        return $this->tenantLimitField ?? null;
    }

    /**
     * Get the extra limit field name for this model.
     */
    protected function getTenantExtraField(): ?string
    {
        $limitField = $this->getTenantLimitField();
        if (!$limitField) {
            return null;
        }

        // Convert max_users -> extra_users, max_branches -> extra_branches
        return str_replace('max_', 'extra_', $limitField);
    }

    /**
     * Enforce the tenant limit.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \RuntimeException
     */
    protected function enforceLimit(): void
    {
        $limitField = $this->getTenantLimitField();

        if (!$limitField) {
            return; // No limit defined for this model
        }

        $tenant = $this->getTenantForLimitCheck();

        if (!$tenant) {
            return; // No tenant context
        }

        $baseLimit = $tenant->{$limitField} ?? 0;
        $extraField = $this->getTenantExtraField();
        $extraLimit = $extraField ? ($tenant->{$extraField} ?? 0) : 0;
        $maxAllowed = $baseLimit + $extraLimit;

        // If limit is 0 or not set, allow unlimited
        if ($maxAllowed <= 0) {
            return;
        }

        $currentCount = $this->countExistingRecords();

        if ($currentCount >= $maxAllowed) {
            $resourceName = $this->getResourceNameForLimit();

            \Log::warning('Tenant limit exceeded at model level', [
                'tenant_id' => $tenant->id,
                'model' => static::class,
                'resource' => $resourceName,
                'current' => $currentCount,
                'max' => $maxAllowed,
            ]);

            throw new \RuntimeException(
                __("Cannot create :resource. You have reached the maximum of :max allowed for your plan (currently: :current).", [
                    'resource' => $resourceName,
                    'max' => $maxAllowed,
                    'current' => $currentCount,
                ])
            );
        }
    }

    /**
     * Get the tenant for limit checking.
     */
    protected function getTenantForLimitCheck(): ?object
    {
        if (app()->bound('currentTenant')) {
            return app('currentTenant');
        }

        return null;
    }

    /**
     * Count existing records for this model type.
     */
    protected function countExistingRecords(): int
    {
        return static::count();
    }

    /**
     * Get human-readable resource name for error messages.
     */
    protected function getResourceNameForLimit(): string
    {
        return $this->tenantLimitResourceName ?? strtolower(class_basename(static::class));
    }

    /**
     * Check if creating a new record would exceed the limit.
     * Useful for checking before attempting to create.
     */
    public static function wouldExceedLimit(): bool
    {
        $instance = new static();

        try {
            $instance->enforceLimit();
            return false;
        } catch (\RuntimeException $e) {
            return true;
        }
    }

    /**
     * Get remaining capacity for this resource type.
     */
    public static function getRemainingCapacity(): int
    {
        $instance = new static();
        $limitField = $instance->getTenantLimitField();

        if (!$limitField) {
            return PHP_INT_MAX;
        }

        $tenant = $instance->getTenantForLimitCheck();

        if (!$tenant) {
            return PHP_INT_MAX;
        }

        $baseLimit = $tenant->{$limitField} ?? 0;
        $extraField = $instance->getTenantExtraField();
        $extraLimit = $extraField ? ($tenant->{$extraField} ?? 0) : 0;
        $maxAllowed = $baseLimit + $extraLimit;

        if ($maxAllowed <= 0) {
            return PHP_INT_MAX;
        }

        $currentCount = static::count();

        return max(0, $maxAllowed - $currentCount);
    }
}
