<?php

namespace XLinic\Framework\Core\Security;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use XLinic\Framework\Core\Security\SecurityManager;
use XLinic\Framework\Core\Tenancy\TenantManager;

/**
 * Field Access Control System
 *
 * Provides field-level access control for models, allowing fine-grained
 * permissions on individual fields based on user roles, permissions,
 * and business rules. Supports read, write, and visibility controls.
 *
 * @package XLinic\Framework\Core\Security
 */
class FieldAccess
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
     * Cache of field access rules
     */
    protected array $accessCache = [];

    /**
     * Field access levels
     */
    public const ACCESS_NONE = 'none';
    public const ACCESS_READ = 'read';
    public const ACCESS_WRITE = 'write';
    public const ACCESS_FULL = 'full';

    /**
     * Field visibility levels
     */
    public const VISIBILITY_HIDDEN = 'hidden';
    public const VISIBILITY_MASKED = 'masked';
    public const VISIBILITY_VISIBLE = 'visible';

    /**
     * Create a new field access instance
     */
    public function __construct(
        SecurityManager $securityManager,
        TenantManager $tenantManager
    ) {
        $this->securityManager = $securityManager;
        $this->tenantManager = $tenantManager;
    }

    /**
     * Check if a user can read a specific field
     */
    public function canRead(Model $model, string $field, ?Model $user = null): bool
    {
        $user = $user ?: Auth::user();

        if (!$user) {
            return false;
        }

        $accessLevel = $this->getFieldAccess($model, $field, $user);

        return in_array($accessLevel, [
            self::ACCESS_READ,
            self::ACCESS_WRITE,
            self::ACCESS_FULL
        ]);
    }

    /**
     * Check if a user can write to a specific field
     */
    public function canWrite(Model $model, string $field, ?Model $user = null): bool
    {
        $user = $user ?: Auth::user();

        if (!$user) {
            return false;
        }

        $accessLevel = $this->getFieldAccess($model, $field, $user);

        return in_array($accessLevel, [
            self::ACCESS_WRITE,
            self::ACCESS_FULL
        ]);
    }

    /**
     * Get field visibility level
     */
    public function getVisibility(Model $model, string $field, ?Model $user = null): string
    {
        $user = $user ?: Auth::user();

        if (!$user) {
            return self::VISIBILITY_HIDDEN;
        }

        return $this->getFieldVisibility($model, $field, $user);
    }

    /**
     * Filter model attributes based on field access
     */
    public function filterAttributes(Model $model, array $attributes, ?Model $user = null): array
    {
        $user = $user ?: Auth::user();

        if (!$user) {
            return [];
        }

        $filtered = [];

        foreach ($attributes as $key => $value) {
            if ($this->canRead($model, $key, $user)) {
                $visibility = $this->getVisibility($model, $key, $user);

                switch ($visibility) {
                    case self::VISIBILITY_VISIBLE:
                        $filtered[$key] = $value;
                        break;
                    case self::VISIBILITY_MASKED:
                        $filtered[$key] = $this->maskFieldValue($key, $value);
                        break;
                    case self::VISIBILITY_HIDDEN:
                        // Field is omitted
                        break;
                }
            }
        }

        return $filtered;
    }

    /**
     * Filter fields for forms based on write access
     */
    public function filterFormFields(Model $model, array $fields, ?Model $user = null): array
    {
        $user = $user ?: Auth::user();

        if (!$user) {
            return [];
        }

        return array_filter($fields, function ($field) use ($model, $user) {
            return $this->canWrite($model, $field, $user);
        });
    }

    /**
     * Get accessible fields for a model
     */
    public function getAccessibleFields(Model $model, string $accessType = 'read', ?Model $user = null): array
    {
        $user = $user ?: Auth::user();

        if (!$user) {
            return [];
        }

        $modelFields = $this->getModelFields($model);
        $accessible = [];

        foreach ($modelFields as $field) {
            $canAccess = match ($accessType) {
                'read' => $this->canRead($model, $field, $user),
                'write' => $this->canWrite($model, $field, $user),
                default => false
            };

            if ($canAccess) {
                $accessible[] = $field;
            }
        }

        return $accessible;
    }

    /**
     * Validate field access for updates
     */
    public function validateFieldUpdates(Model $model, array $updates, ?Model $user = null): array
    {
        $user = $user ?: Auth::user();
        $violations = [];

        foreach ($updates as $field => $value) {
            if (!$this->canWrite($model, $field, $user)) {
                $violations[] = [
                    'field' => $field,
                    'reason' => 'No write access to field',
                    'access_level' => $this->getFieldAccess($model, $field, $user)
                ];
            }
        }

        return $violations;
    }

    /**
     * Get field access level
     */
    protected function getFieldAccess(Model $model, string $field, Model $user): string
    {
        $cacheKey = $this->generateCacheKey($model, $field, $user);

        if (isset($this->accessCache[$cacheKey])) {
            return $this->accessCache[$cacheKey];
        }

        $access = $this->calculateFieldAccess($model, $field, $user);
        $this->accessCache[$cacheKey] = $access;

        return $access;
    }

    /**
     * Calculate field access level
     */
    protected function calculateFieldAccess(Model $model, string $field, Model $user): string
    {
        // Check model-specific field rules first
        $modelAccess = $this->getModelFieldAccess($model, $field, $user);
        if ($modelAccess !== null) {
            return $modelAccess;
        }

        // Check role-based field rules
        $roleAccess = $this->getRoleFieldAccess($model, $field, $user);
        if ($roleAccess !== null) {
            return $roleAccess;
        }

        // Check user-specific field rules
        $userAccess = $this->getUserFieldAccess($model, $field, $user);
        if ($userAccess !== null) {
            return $userAccess;
        }

        // Check tenant-level field rules
        $tenantAccess = $this->getTenantFieldAccess($model, $field, $user);
        if ($tenantAccess !== null) {
            return $tenantAccess;
        }

        // Check if field is sensitive by default
        if ($this->isSensitiveField($field)) {
            return self::ACCESS_NONE;
        }

        // Default access level
        return $this->getDefaultFieldAccess($model, $field);
    }

    /**
     * Get field visibility level
     */
    protected function getFieldVisibility(Model $model, string $field, Model $user): string
    {
        // Check if field should be completely hidden
        if (!$this->canRead($model, $field, $user)) {
            return self::VISIBILITY_HIDDEN;
        }

        // Check for sensitive data masking rules
        if ($this->shouldMaskField($model, $field, $user)) {
            return self::VISIBILITY_MASKED;
        }

        return self::VISIBILITY_VISIBLE;
    }

    /**
     * Get model-specific field access
     */
    protected function getModelFieldAccess(Model $model, string $field, Model $user): ?string
    {
        if (method_exists($model, 'getFieldAccess')) {
            return $model->getFieldAccess($field, $user);
        }

        return null;
    }

    /**
     * Get role-based field access
     */
    protected function getRoleFieldAccess(Model $model, string $field, Model $user): ?string
    {
        $roles = $this->getUserRoles($user);
        $modelClass = get_class($model);

        foreach ($roles as $role) {
            $access = $this->securityManager->getRoleFieldAccess($modelClass, $field, $role);
            if ($access !== null) {
                return $access;
            }
        }

        return null;
    }

    /**
     * Get user-specific field access
     */
    protected function getUserFieldAccess(Model $model, string $field, Model $user): ?string
    {
        $modelClass = get_class($model);
        return $this->securityManager->getUserFieldAccess($modelClass, $field, $user->id);
    }

    /**
     * Get tenant-level field access
     */
    protected function getTenantFieldAccess(Model $model, string $field, Model $user): ?string
    {
        $tenant = $this->tenantManager->getCurrentTenant();

        if (!$tenant) {
            return null;
        }

        $modelClass = get_class($model);
        return $this->securityManager->getTenantFieldAccess($modelClass, $field, $tenant->id);
    }

    /**
     * Check if field is sensitive by default
     */
    protected function isSensitiveField(string $field): bool
    {
        $sensitiveFields = [
            'password',
            'password_hash',
            'remember_token',
            'api_token',
            'secret',
            'private_key',
            'ssn',
            'social_security_number',
            'tax_id',
            'credit_card',
            'bank_account',
            'salary',
        ];

        return in_array(strtolower($field), $sensitiveFields) ||
               str_contains(strtolower($field), 'password') ||
               str_contains(strtolower($field), 'secret') ||
               str_contains(strtolower($field), 'token');
    }

    /**
     * Get default field access level
     */
    protected function getDefaultFieldAccess(Model $model, string $field): string
    {
        // System fields typically have restricted access
        if (in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
            return self::ACCESS_READ;
        }

        // Default to read access for most fields
        return self::ACCESS_READ;
    }

    /**
     * Check if field should be masked
     */
    protected function shouldMaskField(Model $model, string $field, Model $user): bool
    {
        $maskingRules = $this->getMaskingRules($model, $field, $user);

        foreach ($maskingRules as $rule) {
            if ($this->evaluateMaskingRule($rule, $model, $field, $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get masking rules for a field
     */
    protected function getMaskingRules(Model $model, string $field, Model $user): array
    {
        $modelClass = get_class($model);
        $roles = $this->getUserRoles($user);

        $rules = [];

        // Get global masking rules
        $rules = array_merge($rules, $this->securityManager->getGlobalMaskingRules($field));

        // Get model-specific masking rules
        $rules = array_merge($rules, $this->securityManager->getModelMaskingRules($modelClass, $field));

        // Get role-based masking rules
        foreach ($roles as $role) {
            $rules = array_merge($rules, $this->securityManager->getRoleMaskingRules($modelClass, $field, $role));
        }

        return $rules;
    }

    /**
     * Evaluate a masking rule
     */
    protected function evaluateMaskingRule(array $rule, Model $model, string $field, Model $user): bool
    {
        $condition = $rule['condition'] ?? 'always';

        switch ($condition) {
            case 'always':
                return true;
            case 'role':
                $roles = $this->getUserRoles($user);
                $requiredRoles = $rule['roles'] ?? [];
                return !empty(array_intersect($roles, $requiredRoles));
            case 'ownership':
                return $this->checkOwnership($model, $user);
            case 'custom':
                $callback = $rule['callback'] ?? null;
                return $callback ? $callback($model, $field, $user) : false;
            default:
                return false;
        }
    }

    /**
     * Mask field value
     */
    protected function maskFieldValue(string $field, $value): string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = (string) $value;

        // Email masking
        if (filter_var($stringValue, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $stringValue);
            $username = $parts[0];
            $domain = $parts[1] ?? '';

            if (strlen($username) > 2) {
                $maskedUsername = substr($username, 0, 2) . str_repeat('*', strlen($username) - 2);
                return $maskedUsername . '@' . $domain;
            }
        }

        // Phone number masking
        if (preg_match('/^\+?[\d\s\-\(\)]+$/', $stringValue)) {
            $digits = preg_replace('/\D/', '', $stringValue);
            if (strlen($digits) >= 7) {
                return substr($stringValue, 0, 3) . str_repeat('*', strlen($digits) - 6) . substr($stringValue, -3);
            }
        }

        // Credit card masking
        if (preg_match('/^\d{13,19}$/', $stringValue)) {
            return str_repeat('*', strlen($stringValue) - 4) . substr($stringValue, -4);
        }

        // General string masking
        if (strlen($stringValue) > 4) {
            return substr($stringValue, 0, 2) . str_repeat('*', strlen($stringValue) - 4) . substr($stringValue, -2);
        }

        return str_repeat('*', strlen($stringValue));
    }

    /**
     * Check ownership of a model
     */
    protected function checkOwnership(Model $model, Model $user): bool
    {
        if (isset($model->user_id)) {
            return $model->user_id == $user->id;
        }

        if (isset($model->owner_id)) {
            return $model->owner_id == $user->id;
        }

        if (isset($model->created_by)) {
            return $model->created_by == $user->id;
        }

        return false;
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
     * Get model fields
     */
    protected function getModelFields(Model $model): array
    {
        if (method_exists($model, 'getFieldAccessList')) {
            return $model->getFieldAccessList();
        }

        // Get fillable fields as default
        $fillable = $model->getFillable();
        $guarded = $model->getGuarded();

        if (empty($fillable) && $guarded === ['*']) {
            // No fillable fields defined, use table columns
            return $this->getTableColumns($model);
        }

        return $fillable;
    }

    /**
     * Get table columns for a model
     */
    protected function getTableColumns(Model $model): array
    {
        $table = $model->getTable();
        $connection = $model->getConnection();

        return $connection->getSchemaBuilder()->getColumnListing($table);
    }

    /**
     * Generate cache key
     */
    protected function generateCacheKey(Model $model, string $field, Model $user): string
    {
        $modelClass = get_class($model);
        $tenantId = $this->tenantManager->getCurrentTenant()?->id ?? 'global';

        return "field_access_{$modelClass}_{$field}_{$user->id}_{$tenantId}";
    }

    /**
     * Clear access cache
     */
    public function clearCache(): void
    {
        $this->accessCache = [];
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return [
            'size' => count($this->accessCache),
            'keys' => array_keys($this->accessCache),
        ];
    }
}