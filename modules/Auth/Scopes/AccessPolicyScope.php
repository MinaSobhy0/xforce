<?php

namespace Modules\Auth\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Modules\Auth\Services\AccessPolicyService;

/**
 * AccessPolicyScope - Global scope that applies access policies to queries.
 *
 * This scope automatically filters records based on the AccessPolicy rules
 * defined for the current user's roles.
 */
class AccessPolicyScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Skip if no user is authenticated
        if (!auth()->check()) {
            return;
        }

        // Skip if running in console (migrations, seeders, etc.)
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            return;
        }

        // Apply access policies
        try {
            $service = app(AccessPolicyService::class);
            $service->applyPolicies($builder, 'read');
        } catch (\Exception $e) {
            // Log error but don't break the query
            \Log::warning('Failed to apply access policies: ' . $e->getMessage(), [
                'model' => get_class($model),
                'user_id' => auth()->id(),
            ]);
        }
    }

    /**
     * Extend the query builder with the scope's methods.
     */
    public function extend(Builder $builder): void
    {
        // Add method to apply policies for specific permissions
        $builder->macro('withAccessPolicy', function (Builder $builder, string $permission = 'read') {
            $service = app(AccessPolicyService::class);
            return $service->applyPolicies($builder, $permission);
        });

        // Add method to check if current user can access filtered results
        $builder->macro('accessibleBy', function (Builder $builder, $user = null) {
            $user = $user ?? auth()->user();
            if (!$user) {
                return $builder->whereRaw('1 = 0'); // No user = no access
            }

            $service = app(AccessPolicyService::class);
            return $service->applyPolicies($builder, 'read', $user);
        });
    }
}
