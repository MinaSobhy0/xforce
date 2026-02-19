<?php

namespace Modules\Auth\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * AccessPolicy - Record-level access rules (similar to Odoo ir.rule)
 *
 * Allows fine-grained control over which records a user can see/modify
 * based on dynamic conditions (e.g., only records from their branch).
 */
class AccessPolicy extends BaseModel
{
    use HasTenancy;

    protected $fillable = [
        'tenant_id',
        'name',
        'model_type',
        'domain_filter',
        'role_id',
        'apply_to_all_roles',
        'perm_read',
        'perm_create',
        'perm_update',
        'perm_delete',
        'is_active',
        'priority',
        'description',
    ];

    protected $casts = [
        'domain_filter' => 'array',
        'apply_to_all_roles' => 'boolean',
        'perm_read' => 'boolean',
        'perm_create' => 'boolean',
        'perm_update' => 'boolean',
        'perm_delete' => 'boolean',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Get the role this policy applies to.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the tenant this policy belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\Tenant::class);
    }

    /**
     * Scope to active policies.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to policies for a specific model.
     */
    public function scopeForModel($query, string $modelType)
    {
        return $query->where('model_type', $modelType);
    }

    /**
     * Scope to policies for a specific role.
     */
    public function scopeForRole($query, $roleId)
    {
        return $query->where(function ($q) use ($roleId) {
            $q->where('role_id', $roleId)
              ->orWhere('apply_to_all_roles', true);
        });
    }

    /**
     * Scope to policies with a specific permission.
     */
    public function scopeWithPermission($query, string $permission)
    {
        $column = "perm_{$permission}";
        if (in_array($column, ['perm_read', 'perm_create', 'perm_update', 'perm_delete'])) {
            return $query->where($column, true);
        }
        return $query;
    }

    /**
     * Apply the domain filter to a query builder.
     *
     * Domain filter format: [
     *   ['field', 'operator', 'value'],
     *   ['branch_id', '=', '{user.branch_id}'],
     *   ['created_by', '=', '{user.id}'],
     * ]
     */
    public function applyToQuery(Builder $query, User $user): Builder
    {
        if (empty($this->domain_filter)) {
            return $query;
        }

        foreach ($this->domain_filter as $condition) {
            if (!is_array($condition) || count($condition) < 3) {
                continue;
            }

            [$field, $operator, $value] = $condition;

            // Resolve dynamic placeholders
            $value = $this->resolveValue($value, $user);

            // Apply condition based on operator
            match (strtolower($operator)) {
                '=' => $query->where($field, $value),
                '!=' => $query->where($field, '!=', $value),
                '>' => $query->where($field, '>', $value),
                '>=' => $query->where($field, '>=', $value),
                '<' => $query->where($field, '<', $value),
                '<=' => $query->where($field, '<=', $value),
                'in' => $query->whereIn($field, (array) $value),
                'not in' => $query->whereNotIn($field, (array) $value),
                'like' => $query->where($field, 'like', $value),
                'is null' => $query->whereNull($field),
                'is not null' => $query->whereNotNull($field),
                default => null,
            };
        }

        return $query;
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
        $patterns = [
            '{user.id}' => $user->id,
            '{user.tenant_id}' => $user->tenant_id,
            '{user.branch_id}' => $user->branch_id ?? null,
            '{user.department}' => $user->department ?? null,
            '{today}' => now()->toDateString(),
            '{now}' => now()->toDateTimeString(),
        ];

        foreach ($patterns as $pattern => $replacement) {
            if ($value === $pattern) {
                return $replacement;
            }
        }

        return $value;
    }

    /**
     * Check if this policy grants a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return match ($permission) {
            'read' => $this->perm_read,
            'create' => $this->perm_create,
            'update' => $this->perm_update,
            'delete' => $this->perm_delete,
            default => false,
        };
    }

    /**
     * Get all policies applicable to a user for a model.
     */
    public static function getPoliciesForUser(User $user, string $modelType): \Illuminate\Database\Eloquent\Collection
    {
        $roleIds = $user->roles->pluck('id');

        return static::query()
            ->active()
            ->forModel($modelType)
            ->where(function ($query) use ($roleIds) {
                $query->whereIn('role_id', $roleIds)
                      ->orWhere('apply_to_all_roles', true);
            })
            ->orderBy('priority')
            ->get();
    }
}
