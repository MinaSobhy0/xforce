<?php

namespace Modules\Auth\Traits;

use Modules\Auth\Scopes\AccessPolicyScope;

/**
 * Trait HasAccessPolicyScope
 *
 * Add this trait to models that should have access policies applied automatically.
 * The access policy will filter records based on the current user's roles and
 * the AccessPolicy domain_filter rules defined for this model.
 *
 * Usage:
 *   class Patient extends BaseModel
 *   {
 *       use HasAccessPolicyScope;
 *   }
 *
 * This will automatically apply read policies to all queries on this model.
 * To disable temporarily: Patient::withoutAccessPolicies()->get();
 */
trait HasAccessPolicyScope
{
    /**
     * Boot the trait.
     */
    public static function bootHasAccessPolicyScope(): void
    {
        static::addGlobalScope(new AccessPolicyScope());
    }

    /**
     * Get a query builder without access policy scope.
     */
    public static function withoutAccessPolicies(): \Illuminate\Database\Eloquent\Builder
    {
        return static::withoutGlobalScope(AccessPolicyScope::class);
    }

    /**
     * Check if the current user can perform an action on this record.
     */
    public function userCan(string $permission, $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return false;
        }

        $service = app(\Modules\Auth\Services\AccessPolicyService::class);
        return $service->canAccess($user, $this, $permission);
    }

    /**
     * Check if the current user can read this record.
     */
    public function userCanRead($user = null): bool
    {
        return $this->userCan('read', $user);
    }

    /**
     * Check if the current user can update this record.
     */
    public function userCanUpdate($user = null): bool
    {
        return $this->userCan('update', $user);
    }

    /**
     * Check if the current user can delete this record.
     */
    public function userCanDelete($user = null): bool
    {
        return $this->userCan('delete', $user);
    }
}
