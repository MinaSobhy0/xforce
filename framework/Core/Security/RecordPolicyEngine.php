<?php

namespace XLinic\Framework\Core\Security;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use XLinic\Framework\Core\Tenancy\TenantManager;
use XLinic\Framework\Core\Security\SecurityManager;

/**
 * Record Policy Engine
 *
 * Applies record-level security policies to database queries and model instances.
 * Handles tenant isolation, user access controls, and data filtering based on
 * security policies defined for each model and user role.
 *
 * @package XLinic\Framework\Core\Security
 */
class RecordPolicyEngine
{
    /**
     * The security manager instance
     */
    protected SecurityManager $securityManager;

    /**
     * The tenant manager instance
     */
    protected TenantManager $tenantManager;

    /**
     * Cache of applied policies
     */
    protected array $policyCache = [];

    /**
     * Create a new record policy engine instance
     */
    public function __construct(
        SecurityManager $securityManager,
        TenantManager $tenantManager
    ) {
        $this->securityManager = $securityManager;
        $this->tenantManager = $tenantManager;
    }

    /**
     * Apply record policies to a query builder
     */
    public function applyToQuery(Builder $query, ?Model $model = null): Builder
    {
        $model = $model ?: $query->getModel();
        $user = Auth::user();

        if (!$user) {
            return $query->whereRaw('1 = 0'); // No access for unauthenticated users
        }

        // Apply tenant isolation
        $query = $this->applyTenantIsolation($query, $model);

        // Apply user-based record policies
        $query = $this->applyUserPolicies($query, $model, $user);

        // Apply role-based record policies
        $query = $this->applyRolePolicies($query, $model, $user);

        // Apply department/organizational policies
        $query = $this->applyOrganizationalPolicies($query, $model, $user);

        // Apply custom model policies
        $query = $this->applyModelPolicies($query, $model, $user);

        return $query;
    }

    /**
     * Check if a user can access a specific model instance
     */
    public function canAccess(Model $model, ?Model $user = null): bool
    {
        $user = $user ?: Auth::user();

        if (!$user) {
            return false;
        }

        // Check tenant isolation
        if (!$this->checkTenantAccess($model, $user)) {
            return false;
        }

        // Check user-specific policies
        if (!$this->checkUserAccess($model, $user)) {
            return false;
        }

        // Check role-based policies
        if (!$this->checkRoleAccess($model, $user)) {
            return false;
        }

        // Check organizational policies
        if (!$this->checkOrganizationalAccess($model, $user)) {
            return false;
        }

        // Check custom model policies
        if (!$this->checkModelPolicies($model, $user)) {
            return false;
        }

        return true;
    }

    /**
     * Apply tenant isolation to query
     */
    protected function applyTenantIsolation(Builder $query, Model $model): Builder
    {
        $currentTenant = $this->tenantManager->getCurrentTenant();

        if (!$currentTenant) {
            return $query->whereRaw('1 = 0'); // No access without tenant
        }

        // Check if model is tenant-aware
        if (method_exists($model, 'getTenantColumn')) {
            $tenantColumn = $model->getTenantColumn();
            return $query->where($tenantColumn, $currentTenant->id);
        }

        // Check for standard tenant column
        if ($model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), 'tenant_id')) {
            return $query->where('tenant_id', $currentTenant->id);
        }

        return $query;
    }

    /**
     * Apply user-specific record policies
     */
    protected function applyUserPolicies(Builder $query, Model $model, Model $user): Builder
    {
        $modelClass = get_class($model);
        $cacheKey = "user_policies_{$modelClass}_{$user->id}";

        if (isset($this->policyCache[$cacheKey])) {
            $policies = $this->policyCache[$cacheKey];
        } else {
            $policies = $this->getUserRecordPolicies($model, $user);
            $this->policyCache[$cacheKey] = $policies;
        }

        foreach ($policies as $policy) {
            $query = $this->applyPolicy($query, $policy, $user);
        }

        return $query;
    }

    /**
     * Apply role-based record policies
     */
    protected function applyRolePolicies(Builder $query, Model $model, Model $user): Builder
    {
        $roles = $this->getUserRoles($user);

        foreach ($roles as $role) {
            $policies = $this->getRoleRecordPolicies($model, $role);

            foreach ($policies as $policy) {
                $query = $this->applyPolicy($query, $policy, $user);
            }
        }

        return $query;
    }

    /**
     * Apply organizational policies (department, division, etc.)
     */
    protected function applyOrganizationalPolicies(Builder $query, Model $model, Model $user): Builder
    {
        // Apply department-based filtering
        if ($this->hasOrganizationalContext($user)) {
            $orgContext = $this->getOrganizationalContext($user);

            if (method_exists($model, 'getOrganizationalColumn')) {
                $orgColumn = $model->getOrganizationalColumn();
                $query->where($orgColumn, $orgContext['department_id'] ?? null);
            }
        }

        return $query;
    }

    /**
     * Apply custom model-specific policies
     */
    protected function applyModelPolicies(Builder $query, Model $model, Model $user): Builder
    {
        if (method_exists($model, 'applyRecordPolicies')) {
            return $model->applyRecordPolicies($query, $user);
        }

        return $query;
    }

    /**
     * Check tenant access for a model instance
     */
    protected function checkTenantAccess(Model $model, Model $user): bool
    {
        $currentTenant = $this->tenantManager->getCurrentTenant();

        if (!$currentTenant) {
            return false;
        }

        if (method_exists($model, 'getTenantId')) {
            return $model->getTenantId() === $currentTenant->id;
        }

        if (isset($model->tenant_id)) {
            return $model->tenant_id === $currentTenant->id;
        }

        return true; // Allow if no tenant column exists
    }

    /**
     * Check user-specific access
     */
    protected function checkUserAccess(Model $model, Model $user): bool
    {
        $policies = $this->getUserRecordPolicies($model, $user);

        foreach ($policies as $policy) {
            if (!$this->evaluatePolicy($policy, $model, $user)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check role-based access
     */
    protected function checkRoleAccess(Model $model, Model $user): bool
    {
        $roles = $this->getUserRoles($user);

        foreach ($roles as $role) {
            $policies = $this->getRoleRecordPolicies($model, $role);

            foreach ($policies as $policy) {
                if (!$this->evaluatePolicy($policy, $model, $user)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check organizational access
     */
    protected function checkOrganizationalAccess(Model $model, Model $user): bool
    {
        if (!$this->hasOrganizationalContext($user)) {
            return true; // No restrictions if no organizational context
        }

        $orgContext = $this->getOrganizationalContext($user);

        if (method_exists($model, 'getOrganizationalId')) {
            $modelOrgId = $model->getOrganizationalId();
            return $modelOrgId === ($orgContext['department_id'] ?? null);
        }

        return true;
    }

    /**
     * Check custom model policies
     */
    protected function checkModelPolicies(Model $model, Model $user): bool
    {
        if (method_exists($model, 'checkRecordAccess')) {
            return $model->checkRecordAccess($user);
        }

        return true;
    }

    /**
     * Apply a single policy to the query
     */
    protected function applyPolicy(Builder $query, array $policy, Model $user): Builder
    {
        switch ($policy['type']) {
            case 'owner':
                return $this->applyOwnerPolicy($query, $policy, $user);
            case 'department':
                return $this->applyDepartmentPolicy($query, $policy, $user);
            case 'role':
                return $this->applyRolePolicy($query, $policy, $user);
            case 'custom':
                return $this->applyCustomPolicy($query, $policy, $user);
            default:
                return $query;
        }
    }

    /**
     * Apply owner-based policy
     */
    protected function applyOwnerPolicy(Builder $query, array $policy, Model $user): Builder
    {
        $column = $policy['column'] ?? 'user_id';
        return $query->where($column, $user->id);
    }

    /**
     * Apply department-based policy
     */
    protected function applyDepartmentPolicy(Builder $query, array $policy, Model $user): Builder
    {
        $userDepartment = $user->department_id ?? null;
        $column = $policy['column'] ?? 'department_id';

        if ($userDepartment) {
            return $query->where($column, $userDepartment);
        }

        return $query->whereRaw('1 = 0'); // No access if no department
    }

    /**
     * Apply role-based policy
     */
    protected function applyRolePolicy(Builder $query, array $policy, Model $user): Builder
    {
        $roles = $this->getUserRoles($user);
        $allowedRoles = $policy['roles'] ?? [];

        $hasAccess = !empty(array_intersect($roles, $allowedRoles));

        if (!$hasAccess) {
            return $query->whereRaw('1 = 0'); // No access for this role
        }

        return $query;
    }

    /**
     * Apply custom policy
     */
    protected function applyCustomPolicy(Builder $query, array $policy, Model $user): Builder
    {
        $callback = $policy['callback'] ?? null;

        if (is_callable($callback)) {
            return $callback($query, $user);
        }

        return $query;
    }

    /**
     * Evaluate a policy against a model instance
     */
    protected function evaluatePolicy(array $policy, Model $model, Model $user): bool
    {
        switch ($policy['type']) {
            case 'owner':
                return $this->evaluateOwnerPolicy($policy, $model, $user);
            case 'department':
                return $this->evaluateDepartmentPolicy($policy, $model, $user);
            case 'role':
                return $this->evaluateRolePolicy($policy, $model, $user);
            case 'custom':
                return $this->evaluateCustomPolicy($policy, $model, $user);
            default:
                return true;
        }
    }

    /**
     * Evaluate owner policy
     */
    protected function evaluateOwnerPolicy(array $policy, Model $model, Model $user): bool
    {
        $column = $policy['column'] ?? 'user_id';
        return $model->$column == $user->id;
    }

    /**
     * Evaluate department policy
     */
    protected function evaluateDepartmentPolicy(array $policy, Model $model, Model $user): bool
    {
        $userDepartment = $user->department_id ?? null;
        $column = $policy['column'] ?? 'department_id';

        return $userDepartment && $model->$column == $userDepartment;
    }

    /**
     * Evaluate role policy
     */
    protected function evaluateRolePolicy(array $policy, Model $model, Model $user): bool
    {
        $roles = $this->getUserRoles($user);
        $allowedRoles = $policy['roles'] ?? [];

        return !empty(array_intersect($roles, $allowedRoles));
    }

    /**
     * Evaluate custom policy
     */
    protected function evaluateCustomPolicy(array $policy, Model $model, Model $user): bool
    {
        $callback = $policy['callback'] ?? null;

        if (is_callable($callback)) {
            return $callback($model, $user);
        }

        return true;
    }

    /**
     * Get user record policies for a model
     */
    protected function getUserRecordPolicies(Model $model, Model $user): array
    {
        return $this->securityManager->getUserRecordPolicies(get_class($model), $user->id);
    }

    /**
     * Get role record policies for a model
     */
    protected function getRoleRecordPolicies(Model $model, string $role): array
    {
        return $this->securityManager->getRoleRecordPolicies(get_class($model), $role);
    }

    /**
     * Get user roles
     */
    protected function getUserRoles(Model $user): array
    {
        if (method_exists($user, 'getRoles')) {
            return $user->getRoles();
        }

        if (method_exists($user, 'roles')) {
            return $user->roles()->pluck('name')->toArray();
        }

        return [$user->role ?? 'user'];
    }

    /**
     * Check if user has organizational context
     */
    protected function hasOrganizationalContext(Model $user): bool
    {
        return !empty($user->department_id) || !empty($user->division_id);
    }

    /**
     * Get user's organizational context
     */
    protected function getOrganizationalContext(Model $user): array
    {
        return [
            'department_id' => $user->department_id ?? null,
            'division_id' => $user->division_id ?? null,
            'branch_id' => $user->branch_id ?? null,
        ];
    }

    /**
     * Clear policy cache
     */
    public function clearCache(): void
    {
        $this->policyCache = [];
    }

    /**
     * Get policy cache stats
     */
    public function getCacheStats(): array
    {
        return [
            'size' => count($this->policyCache),
            'keys' => array_keys($this->policyCache),
        ];
    }
}