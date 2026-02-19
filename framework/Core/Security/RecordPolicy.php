<?php

namespace XLinic\Framework\Core\Security;

use XLinic\Framework\Core\Model\BaseModel;

class RecordPolicy extends BaseModel
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'model',
        'name',
        'domain_filter',
        'role_id',
        'perm_read',
        'perm_write',
        'perm_create',
        'perm_delete',
        'active',
        'priority',
        'description',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'domain_filter' => 'array',
        'perm_read' => 'boolean',
        'perm_write' => 'boolean',
        'perm_create' => 'boolean',
        'perm_delete' => 'boolean',
        'active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Get the role this policy belongs to.
     */
    public function role()
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class);
    }

    /**
     * Evaluate the policy for a user and model instance.
     */
    public function evaluate($user, BaseModel $model): bool
    {
        if (!$this->active) {
            return false;
        }

        // Check if user has the role
        if ($this->role_id && !$user->hasRole($this->role->name)) {
            return false;
        }

        // Apply domain filter
        return $this->applyDomainFilter($model, $user);
    }

    /**
     * Apply domain filter to check if record matches policy conditions.
     */
    protected function applyDomainFilter(BaseModel $model, $user): bool
    {
        if (empty($this->domain_filter)) {
            return true; // No filter means all records match
        }

        foreach ($this->domain_filter as $condition) {
            if (!$this->evaluateCondition($condition, $model, $user)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition.
     */
    protected function evaluateCondition(array $condition, BaseModel $model, $user): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? null;

        if (!$field) {
            return true;
        }

        // Resolve dynamic values
        $resolvedValue = $this->resolveDynamicValue($value, $user);
        $modelValue = $this->getModelValue($model, $field);

        return $this->compareValues($modelValue, $operator, $resolvedValue);
    }

    /**
     * Resolve dynamic values like {user.id}, {user.branch_id}, etc.
     */
    protected function resolveDynamicValue($value, $user)
    {
        if (!is_string($value) || !str_contains($value, '{')) {
            return $value;
        }

        // Replace {user.field} placeholders
        $value = preg_replace_callback('/\{user\.(\w+)\}/', function ($matches) use ($user) {
            $field = $matches[1];
            return $user->$field ?? null;
        }, $value);

        // Replace {tenant.field} placeholders
        $value = preg_replace_callback('/\{tenant\.(\w+)\}/', function ($matches) {
            $field = $matches[1];
            $tenant = app(\XLinic\Framework\Core\Tenancy\TenantManager::class)->current();
            return $tenant ? $tenant->$field : null;
        }, $value);

        // Replace {today}, {now}, etc.
        $value = str_replace([
            '{today}',
            '{now}',
            '{this_month_start}',
            '{this_month_end}',
            '{this_year_start}',
            '{this_year_end}',
        ], [
            now()->format('Y-m-d'),
            now()->format('Y-m-d H:i:s'),
            now()->startOfMonth()->format('Y-m-d'),
            now()->endOfMonth()->format('Y-m-d'),
            now()->startOfYear()->format('Y-m-d'),
            now()->endOfYear()->format('Y-m-d'),
        ], $value);

        return $value;
    }

    /**
     * Get value from model, supporting dot notation for relationships.
     */
    protected function getModelValue(BaseModel $model, string $field)
    {
        if (!str_contains($field, '.')) {
            return $model->getAttribute($field);
        }

        $parts = explode('.', $field);
        $current = $model;

        foreach ($parts as $part) {
            if ($current === null) {
                return null;
            }

            if (is_object($current)) {
                $current = $current->getAttribute($part) ?? $current->$part ?? null;
            } elseif (is_array($current)) {
                $current = $current[$part] ?? null;
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Compare values using different operators.
     */
    protected function compareValues($modelValue, string $operator, $expectedValue): bool
    {
        return match ($operator) {
            '=' => $modelValue == $expectedValue,
            '!=' => $modelValue != $expectedValue,
            '>' => $modelValue > $expectedValue,
            '>=' => $modelValue >= $expectedValue,
            '<' => $modelValue < $expectedValue,
            '<=' => $modelValue <= $expectedValue,
            'in' => is_array($expectedValue) && in_array($modelValue, $expectedValue),
            'not_in' => is_array($expectedValue) && !in_array($modelValue, $expectedValue),
            'like' => str_contains(strtolower($modelValue), strtolower($expectedValue)),
            'not_like' => !str_contains(strtolower($modelValue), strtolower($expectedValue)),
            'is_null' => $modelValue === null,
            'is_not_null' => $modelValue !== null,
            'between' => is_array($expectedValue) && count($expectedValue) === 2 &&
                         $modelValue >= $expectedValue[0] && $modelValue <= $expectedValue[1],
            default => false,
        };
    }

    /**
     * Check if policy grants read permission.
     */
    public function canRead($user, BaseModel $model): bool
    {
        return $this->perm_read && $this->evaluate($user, $model);
    }

    /**
     * Check if policy grants write permission.
     */
    public function canWrite($user, BaseModel $model): bool
    {
        return $this->perm_write && $this->evaluate($user, $model);
    }

    /**
     * Check if policy grants create permission.
     */
    public function canCreate($user, string $modelClass): bool
    {
        return $this->perm_create && $this->model === $modelClass;
    }

    /**
     * Check if policy grants delete permission.
     */
    public function canDelete($user, BaseModel $model): bool
    {
        return $this->perm_delete && $this->evaluate($user, $model);
    }

    /**
     * Apply policy as query scope.
     */
    public function applyToQuery($query, $user)
    {
        if (empty($this->domain_filter)) {
            return $query; // No filter means no restriction
        }

        foreach ($this->domain_filter as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? null;

            if (!$field) {
                continue;
            }

            $resolvedValue = $this->resolveDynamicValue($value, $user);

            match ($operator) {
                '=' => $query->where($field, $resolvedValue),
                '!=' => $query->where($field, '!=', $resolvedValue),
                '>' => $query->where($field, '>', $resolvedValue),
                '>=' => $query->where($field, '>=', $resolvedValue),
                '<' => $query->where($field, '<', $resolvedValue),
                '<=' => $query->where($field, '<=', $resolvedValue),
                'in' => $query->whereIn($field, $resolvedValue),
                'not_in' => $query->whereNotIn($field, $resolvedValue),
                'like' => $query->where($field, 'like', "%{$resolvedValue}%"),
                'not_like' => $query->where($field, 'not like', "%{$resolvedValue}%"),
                'is_null' => $query->whereNull($field),
                'is_not_null' => $query->whereNotNull($field),
                'between' => is_array($resolvedValue) && count($resolvedValue) === 2 ?
                    $query->whereBetween($field, $resolvedValue) : $query,
                default => $query,
            };
        }

        return $query;
    }

    /**
     * Get human-readable description of the policy.
     */
    public function getHumanDescription(): string
    {
        if ($this->description) {
            return $this->description;
        }

        $permissions = collect([
            $this->perm_read ? 'read' : null,
            $this->perm_write ? 'write' : null,
            $this->perm_create ? 'create' : null,
            $this->perm_delete ? 'delete' : null,
        ])->filter()->implode(', ');

        $model = class_basename($this->model);

        return "Allow {$permissions} access to {$model} records";
    }

    /**
     * Scope to active policies.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to policies for a specific model.
     */
    public function scopeForModel($query, string $modelClass)
    {
        return $query->where('model', $modelClass);
    }

    /**
     * Scope to policies for a specific role.
     */
    public function scopeForRole($query, $role)
    {
        $roleId = is_object($role) ? $role->id : $role;
        return $query->where('role_id', $roleId);
    }

    /**
     * Order by priority.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('priority', 'asc')->orderBy('name', 'asc');
    }
}