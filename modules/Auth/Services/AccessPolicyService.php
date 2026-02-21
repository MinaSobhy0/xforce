<?php

namespace Modules\Auth\Services;

use Modules\Auth\Models\AccessPolicy;
use Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class AccessPolicyService
{
    /**
     * Cache key prefix for user policies.
     */
    protected const CACHE_PREFIX = 'access_policies:user:';

    /**
     * Cache TTL in seconds (5 minutes).
     */
    protected const CACHE_TTL = 300;

    /**
     * Apply access policies to a query for the current user.
     */
    public function applyPolicies(Builder $query, string $permission = 'read', ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return $query;
        }

        // Skip for super admins
        if ($this->isSuperAdmin($user)) {
            return $query;
        }

        // Get the model class
        $modelClass = get_class($query->getModel());

        // Get applicable policies
        $policies = $this->getPoliciesForUser($user, $modelClass, $permission);

        if ($policies->isEmpty()) {
            // No policies defined = allow all (or restrict all depending on config)
            return $query;
        }

        // Apply policies using OR logic (any matching policy grants access)
        $query->where(function (Builder $q) use ($policies, $user) {
            foreach ($policies as $index => $policy) {
                if ($index === 0) {
                    $this->applyPolicyConditions($q, $policy, $user);
                } else {
                    $q->orWhere(function (Builder $subQuery) use ($policy, $user) {
                        $this->applyPolicyConditions($subQuery, $policy, $user);
                    });
                }
            }
        });

        return $query;
    }

    /**
     * Apply a single policy's domain filter conditions.
     */
    protected function applyPolicyConditions(Builder $query, AccessPolicy $policy, User $user): void
    {
        $domainFilter = $policy->domain_filter;

        if (empty($domainFilter)) {
            return;
        }

        foreach ($domainFilter as $condition) {
            if (!is_array($condition) || count($condition) < 3) {
                continue;
            }

            [$field, $operator, $value] = $condition;

            // Resolve dynamic placeholders
            $value = $this->resolveValue($value, $user);

            // Apply condition based on operator
            $this->applyCondition($query, $field, $operator, $value);
        }
    }

    /**
     * Apply a single condition to the query.
     */
    protected function applyCondition(Builder $query, string $field, string $operator, mixed $value): void
    {
        $operator = strtolower(trim($operator));

        match ($operator) {
            '=', '==' => $query->where($field, $value),
            '!=', '<>' => $query->where($field, '!=', $value),
            '>' => $query->where($field, '>', $value),
            '>=' => $query->where($field, '>=', $value),
            '<' => $query->where($field, '<', $value),
            '<=' => $query->where($field, '<=', $value),
            'in' => $query->whereIn($field, (array) $value),
            'not in', 'not_in' => $query->whereNotIn($field, (array) $value),
            'like', 'ilike' => $query->where($field, 'ilike', $value),
            'is null', 'null' => $query->whereNull($field),
            'is not null', 'not null', 'notnull' => $query->whereNotNull($field),
            'between' => is_array($value) && count($value) === 2
                ? $query->whereBetween($field, $value)
                : null,
            default => null,
        };
    }

    /**
     * Resolve dynamic placeholders in values.
     */
    protected function resolveValue(mixed $value, User $user): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        // Replace {user.*} placeholders
        $replacements = [
            '{user.id}' => $user->id,
            '{user.tenant_id}' => $user->tenant_id ?? null,
            '{user.branch_id}' => $user->branch_id ?? null,
            '{user.branch_ids}' => $this->getUserBranchIds($user),
            '{user.department}' => $user->department ?? null,
            '{user.email}' => $user->email ?? null,
            '{today}' => now()->toDateString(),
            '{now}' => now()->toDateTimeString(),
            '{start_of_month}' => now()->startOfMonth()->toDateString(),
            '{end_of_month}' => now()->endOfMonth()->toDateString(),
            '{start_of_year}' => now()->startOfYear()->toDateString(),
        ];

        // Check for exact match
        if (isset($replacements[$value])) {
            return $replacements[$value];
        }

        // Check for dynamic user attribute {user.custom_field}
        if (preg_match('/^\{user\.(\w+)\}$/', $value, $matches)) {
            $attribute = $matches[1];
            return $user->{$attribute} ?? null;
        }

        return $value;
    }

    /**
     * Get all branch IDs the user has access to.
     */
    protected function getUserBranchIds(User $user): array
    {
        // Try to get from UserBranchRole if available
        if (class_exists(\Modules\Auth\Models\UserBranchRole::class)) {
            return \Modules\Auth\Models\UserBranchRole::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->pluck('branch_id')
                ->unique()
                ->toArray();
        }

        // Fall back to user's single branch_id
        return $user->branch_id ? [$user->branch_id] : [];
    }

    /**
     * Get policies applicable to a user for a specific model and permission.
     */
    public function getPoliciesForUser(User $user, string $modelClass, string $permission = 'read'): \Illuminate\Support\Collection
    {
        $cacheKey = self::CACHE_PREFIX . $user->id . ':' . md5($modelClass . ':' . $permission);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $modelClass, $permission) {
            $roleIds = $user->roles?->pluck('id')->toArray() ?? [];

            if (empty($roleIds)) {
                return collect();
            }

            return AccessPolicy::query()
                ->active()
                ->forModel($modelClass)
                ->withPermission($permission)
                ->where(function ($query) use ($roleIds) {
                    $query->whereIn('role_id', $roleIds)
                          ->orWhere('apply_to_all_roles', true);
                })
                ->orderBy('priority')
                ->get();
        });
    }

    /**
     * Check if a user is a super admin (bypasses access policies).
     */
    public function isSuperAdmin(User $user): bool
    {
        // Check for super admin roles
        if ($user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'owner'])) {
            return true;
        }

        // Check for bypass permission
        if ($user->can('bypass-access-policies')) {
            return true;
        }

        return false;
    }

    /**
     * Check if a user can perform an action on a specific record.
     */
    public function canAccess(User $user, $record, string $permission = 'read'): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $modelClass = get_class($record);
        $policies = $this->getPoliciesForUser($user, $modelClass, $permission);

        if ($policies->isEmpty()) {
            return true; // No policies = allow
        }

        foreach ($policies as $policy) {
            if ($this->recordMatchesPolicy($record, $policy, $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a record matches a policy's domain filter.
     */
    protected function recordMatchesPolicy($record, AccessPolicy $policy, User $user): bool
    {
        $domainFilter = $policy->domain_filter;

        if (empty($domainFilter)) {
            return true;
        }

        foreach ($domainFilter as $condition) {
            if (!is_array($condition) || count($condition) < 3) {
                continue;
            }

            [$field, $operator, $value] = $condition;
            $value = $this->resolveValue($value, $user);
            $recordValue = $record->{$field};

            if (!$this->valueMatchesCondition($recordValue, $operator, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a value matches a condition.
     */
    protected function valueMatchesCondition(mixed $recordValue, string $operator, mixed $conditionValue): bool
    {
        $operator = strtolower(trim($operator));

        return match ($operator) {
            '=', '==' => $recordValue == $conditionValue,
            '!=', '<>' => $recordValue != $conditionValue,
            '>' => $recordValue > $conditionValue,
            '>=' => $recordValue >= $conditionValue,
            '<' => $recordValue < $conditionValue,
            '<=' => $recordValue <= $conditionValue,
            'in' => in_array($recordValue, (array) $conditionValue),
            'not in', 'not_in' => !in_array($recordValue, (array) $conditionValue),
            'like', 'ilike' => str_contains(strtolower($recordValue ?? ''), strtolower(str_replace('%', '', $conditionValue))),
            'is null', 'null' => is_null($recordValue),
            'is not null', 'not null', 'notnull' => !is_null($recordValue),
            default => true,
        };
    }

    /**
     * Clear cached policies for a user.
     */
    public function clearCache(?User $user = null): void
    {
        if ($user) {
            Cache::forget(self::CACHE_PREFIX . $user->id . ':*');
        } else {
            // Clear all access policy caches - this is a simplified version
            // In production, you might want to use cache tags
        }
    }
}
