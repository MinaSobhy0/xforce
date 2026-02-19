<?php

namespace XLinic\Framework\Core\Settings;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use XLinic\Framework\Core\Tenancy\TenantManager;
use XLinic\Framework\Core\Module\ModuleManifest;

class SettingsRegistry
{
    protected array $settings = [];
    protected array $defaults = [];

    public function __construct(
        protected TenantManager $tenantManager
    ) {
    }

    /**
     * Register settings from a module manifest.
     */
    public function registerFromManifest(ModuleManifest $manifest): void
    {
        foreach ($manifest->settings as $key => $setting) {
            $this->register($key, $setting);
        }
    }

    /**
     * Register a setting.
     */
    public function register(string $key, array $setting): void
    {
        $this->settings[$key] = $setting;

        if (isset($setting['default'])) {
            $this->defaults[$key] = $setting['default'];
        }
    }

    /**
     * Get a setting value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();
        $cacheKey = "settings:{$tenantId}:{$key}";

        // Try cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Try database
        $value = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->value('value');

        if ($value !== null) {
            $decoded = json_decode($value, true);
            $finalValue = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;

            Cache::put($cacheKey, $finalValue, 3600);
            return $finalValue;
        }

        // Use registered default
        $registeredDefault = $this->defaults[$key] ?? null;
        if ($registeredDefault !== null) {
            return $registeredDefault;
        }

        // Use provided default
        return $default;
    }

    /**
     * Set a setting value.
     */
    public function set(string $key, mixed $value): void
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        $encodedValue = is_array($value) || is_object($value) ? json_encode($value) : $value;

        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $tenantId, 'key' => $key],
            ['value' => $encodedValue, 'updated_at' => now()]
        );

        // Update cache
        $cacheKey = "settings:{$tenantId}:{$key}";
        Cache::put($cacheKey, $value, 3600);

        // Log the change
        activity()
            ->withProperties(['key' => $key, 'value' => $value])
            ->log('Setting updated');
    }

    /**
     * Delete a setting.
     */
    public function delete(string $key): bool
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        $deleted = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->delete() > 0;

        if ($deleted) {
            $cacheKey = "settings:{$tenantId}:{$key}";
            Cache::forget($cacheKey);

            activity()
                ->withProperties(['key' => $key])
                ->log('Setting deleted');
        }

        return $deleted;
    }

    /**
     * Check if a setting exists.
     */
    public function has(string $key): bool
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        return DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->exists();
    }

    /**
     * Get all settings for current tenant.
     */
    public function all(): array
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        $settings = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->pluck('value', 'key')
            ->toArray();

        // Decode JSON values
        foreach ($settings as $key => $value) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $settings[$key] = $decoded;
            }
        }

        return $settings;
    }

    /**
     * Get settings by prefix.
     */
    public function getByPrefix(string $prefix): array
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        $settings = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('key', 'like', $prefix . '%')
            ->pluck('value', 'key')
            ->toArray();

        // Decode JSON values
        foreach ($settings as $key => $value) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $settings[$key] = $decoded;
            }
        }

        return $settings;
    }

    /**
     * Bulk set settings.
     */
    public function setBulk(array $settings): void
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();
        $records = [];

        foreach ($settings as $key => $value) {
            $encodedValue = is_array($value) || is_object($value) ? json_encode($value) : $value;

            $records[] = [
                'tenant_id' => $tenantId,
                'key' => $key,
                'value' => $encodedValue,
                'updated_at' => now(),
            ];

            // Update cache
            $cacheKey = "settings:{$tenantId}:{$key}";
            Cache::put($cacheKey, $value, 3600);
        }

        // Use upsert for better performance
        foreach ($records as $record) {
            DB::table('tenant_settings')->updateOrInsert(
                ['tenant_id' => $record['tenant_id'], 'key' => $record['key']],
                $record
            );
        }

        activity()
            ->withProperties(['keys' => array_keys($settings)])
            ->log('Settings bulk updated');
    }

    /**
     * Get setting definition.
     */
    public function getDefinition(string $key): ?array
    {
        return $this->settings[$key] ?? null;
    }

    /**
     * Get all setting definitions.
     */
    public function getDefinitions(): array
    {
        return $this->settings;
    }

    /**
     * Validate a setting value.
     */
    public function validate(string $key, mixed $value): bool
    {
        $definition = $this->getDefinition($key);

        if (!$definition) {
            return true; // No definition, assume valid
        }

        // Check type
        if (isset($definition['type'])) {
            if (!$this->validateType($value, $definition['type'])) {
                return false;
            }
        }

        // Check validation rules
        if (isset($definition['validation'])) {
            if (!$this->validateRules($value, $definition['validation'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate value type.
     */
    protected function validateType(mixed $value, string $type): bool
    {
        return match($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'float' => is_float($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            'json' => is_array($value) || is_object($value),
            default => true,
        };
    }

    /**
     * Validate against rules.
     */
    protected function validateRules(mixed $value, array $rules): bool
    {
        // Basic validation - in a real implementation you might use Laravel's validator
        foreach ($rules as $rule => $constraint) {
            switch ($rule) {
                case 'min':
                    if (is_numeric($value) && $value < $constraint) {
                        return false;
                    }
                    if (is_string($value) && strlen($value) < $constraint) {
                        return false;
                    }
                    break;
                case 'max':
                    if (is_numeric($value) && $value > $constraint) {
                        return false;
                    }
                    if (is_string($value) && strlen($value) > $constraint) {
                        return false;
                    }
                    break;
                case 'required':
                    if ($constraint && ($value === null || $value === '')) {
                        return false;
                    }
                    break;
                case 'in':
                    if (!in_array($value, $constraint)) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * Clear cache for current tenant.
     */
    public function clearCache(): void
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();
        Cache::tags(["tenant:{$tenantId}", 'settings'])->flush();
    }

    /**
     * Reset settings to defaults.
     */
    public function resetToDefaults(array $keys = []): void
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();
        $keysToReset = empty($keys) ? array_keys($this->defaults) : $keys;

        foreach ($keysToReset as $key) {
            if (isset($this->defaults[$key])) {
                $this->set($key, $this->defaults[$key]);
            }
        }

        activity()
            ->withProperties(['keys' => $keysToReset])
            ->log('Settings reset to defaults');
    }

    /**
     * Export settings.
     */
    public function export(): array
    {
        return [
            'settings' => $this->all(),
            'definitions' => $this->getDefinitions(),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Import settings.
     */
    public function import(array $data, bool $overwrite = false): void
    {
        if (!isset($data['settings'])) {
            throw new \InvalidArgumentException('Invalid settings data format');
        }

        $settingsToImport = $data['settings'];

        if (!$overwrite) {
            // Only import settings that don't exist
            $existing = array_keys($this->all());
            $settingsToImport = array_diff_key($settingsToImport, array_flip($existing));
        }

        $this->setBulk($settingsToImport);

        activity()
            ->withProperties(['count' => count($settingsToImport), 'overwrite' => $overwrite])
            ->log('Settings imported');
    }
}