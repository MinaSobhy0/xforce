<?php

namespace XLinic\Framework\Core\Tenancy;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use XLinic\Framework\Core\Tenancy\TenantManager;

/**
 * Tenant Aware Job
 *
 * Base class for queue jobs that need tenant context.
 * Handles tenant serialization/deserialization, database
 * connection switching, and tenant-specific configuration.
 *
 * @package XLinic\Framework\Core\Tenancy
 */
abstract class TenantAwareJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The tenant instance
     */
    protected ?object $tenant = null;

    /**
     * The tenant ID for serialization
     */
    protected ?int $tenantId = null;

    /**
     * The original database connection
     */
    protected ?string $originalConnection = null;

    /**
     * Tenant-specific configuration backup
     */
    protected array $originalConfig = [];

    /**
     * Whether to switch database connection
     */
    protected bool $switchDatabase = true;

    /**
     * Whether to apply tenant configuration
     */
    protected bool $applyTenantConfig = true;

    /**
     * Create a new job instance
     */
    public function __construct(?object $tenant = null)
    {
        if ($tenant) {
            $this->setTenant($tenant);
        } else {
            // Try to get current tenant from context
            $currentTenant = app(TenantManager::class)->getCurrentTenant();
            if ($currentTenant) {
                $this->setTenant($currentTenant);
            }
        }
    }

    /**
     * Set the tenant for this job
     */
    public function setTenant(object $tenant): self
    {
        $this->tenant = $tenant;
        $this->tenantId = $tenant->id;

        return $this;
    }

    /**
     * Get the tenant for this job
     */
    public function getTenant(): ?object
    {
        return $this->tenant;
    }

    /**
     * Get the tenant ID
     */
    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    /**
     * Handle the job execution with tenant context
     */
    public function handle(): void
    {
        if (!$this->tenant) {
            $this->resolvetenant();
        }

        if (!$this->tenant) {
            $this->handleMissingTenant();
            return;
        }

        try {
            // Set up tenant context
            $this->setupTenantContext();

            // Execute the actual job logic
            $this->handleTenantJob();

        } catch (\Exception $e) {
            $this->handleTenantJobException($e);
            throw $e;
        } finally {
            // Clean up tenant context
            $this->cleanupTenantContext();
        }
    }

    /**
     * Handle the job logic with tenant context
     */
    abstract protected function handleTenantJob(): void;

    /**
     * Handle job failure with tenant context
     */
    public function failed(?\Throwable $exception = null): void
    {
        $this->logJobFailure($exception);

        // Call tenant-specific failure handling
        if (method_exists($this, 'handleTenantJobFailure')) {
            try {
                $this->setupTenantContext();
                $this->handleTenantJobFailure($exception);
            } catch (\Exception $e) {
                logger()->error('Error in tenant job failure handler', [
                    'tenant_id' => $this->tenantId,
                    'job_class' => static::class,
                    'original_exception' => $exception?->getMessage(),
                    'handler_exception' => $e->getMessage(),
                ]);
            } finally {
                $this->cleanupTenantContext();
            }
        }
    }

    /**
     * Resolve tenant from stored ID
     */
    protected function resolveTenan(): void
    {
        if ($this->tenantId) {
            $tenantManager = app(TenantManager::class);
            $this->tenant = $tenantManager->findTenant($this->tenantId);
        }
    }

    /**
     * Set up tenant context for job execution
     */
    protected function setupTenantContext(): void
    {
        if (!$this->tenant) {
            return;
        }

        // Set current tenant in manager
        app(TenantManager::class)->setCurrentTenant($this->tenant);

        // Switch database connection if needed
        if ($this->switchDatabase) {
            $this->switchDatabaseConnection();
        }

        // Apply tenant configuration if needed
        if ($this->applyTenantConfig) {
            $this->applyTenantConfiguration();
        }

        // Log context setup
        $this->logContextSetup();
    }

    /**
     * Clean up tenant context after job execution
     */
    protected function cleanupTenantContext(): void
    {
        // Clear current tenant
        app(TenantManager::class)->clearCurrentTenant();

        // Restore original database connection
        if ($this->originalConnection) {
            DB::setDefaultConnection($this->originalConnection);
            $this->originalConnection = null;
        }

        // Restore original configuration
        if (!empty($this->originalConfig)) {
            foreach ($this->originalConfig as $key => $value) {
                Config::set($key, $value);
            }
            $this->originalConfig = [];
        }
    }

    /**
     * Switch to tenant-specific database connection
     */
    protected function switchDatabaseConnection(): void
    {
        // Store original connection
        $this->originalConnection = DB::getDefaultConnection();

        // Get tenant database configuration
        $connectionConfig = $this->getTenantDatabaseConfig();

        if ($connectionConfig) {
            $connectionName = 'tenant_job_' . $this->tenantId;

            // Set up tenant connection
            Config::set("database.connections.{$connectionName}", $connectionConfig);
            DB::purge($connectionName);
            DB::setDefaultConnection($connectionName);
        }
    }

    /**
     * Get tenant database configuration
     */
    protected function getTenantDatabaseConfig(): ?array
    {
        if (!$this->tenant) {
            return null;
        }

        // Check if tenant has custom database configuration
        if (isset($this->tenant->database_config)) {
            return is_array($this->tenant->database_config)
                ? $this->tenant->database_config
                : json_decode($this->tenant->database_config, true);
        }

        // Use pattern-based database naming
        $pattern = config('tenant.database_pattern', 'tenant_{id}');
        $databaseName = str_replace('{id}', $this->tenant->id, $pattern);

        $baseConfig = config('database.connections.' . config('database.default'));

        return array_merge($baseConfig, [
            'database' => $databaseName,
            // PgBouncer compatibility: emulate prepares to avoid "prepared statement does not exist" errors
            'options' => [
                \PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', false),
                \PDO::ATTR_EMULATE_PREPARES => env('DB_PGBOUNCER', true),
            ],
        ]);
    }

    /**
     * Apply tenant-specific configuration
     */
    protected function applyTenantConfiguration(): void
    {
        if (!$this->tenant) {
            return;
        }

        // Apply tenant configuration
        if (isset($this->tenant->config)) {
            $tenantConfig = is_array($this->tenant->config)
                ? $this->tenant->config
                : json_decode($this->tenant->config, true);

            foreach ($tenantConfig as $key => $value) {
                // Backup original value
                $this->originalConfig[$key] = Config::get($key);
                Config::set($key, $value);
            }
        }

        // Set tenant timezone
        if (isset($this->tenant->timezone)) {
            $this->originalConfig['app.timezone'] = Config::get('app.timezone');
            Config::set('app.timezone', $this->tenant->timezone);
            date_default_timezone_set($this->tenant->timezone);
        }

        // Set tenant locale
        if (isset($this->tenant->locale)) {
            $this->originalConfig['app.locale'] = Config::get('app.locale');
            Config::set('app.locale', $this->tenant->locale);
            app()->setLocale($this->tenant->locale);
        }

        // Set tenant currency
        if (isset($this->tenant->currency)) {
            $this->originalConfig['app.currency'] = Config::get('app.currency');
            Config::set('app.currency', $this->tenant->currency);
        }
    }

    /**
     * Handle missing tenant scenario
     */
    protected function handleMissingTenant(): void
    {
        logger()->error('Job executed without tenant context', [
            'job_class' => static::class,
            'tenant_id' => $this->tenantId,
            'queue' => $this->queue,
        ]);

        // Fail the job if tenant is required
        $this->fail(new \RuntimeException('Tenant context required but not available'));
    }

    /**
     * Handle tenant job exceptions
     */
    protected function handleTenantJobException(\Exception $exception): void
    {
        logger()->error('Tenant job exception', [
            'tenant_id' => $this->tenantId,
            'job_class' => static::class,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Log context setup
     */
    protected function logContextSetup(): void
    {
        logger()->debug('Tenant job context setup', [
            'tenant_id' => $this->tenantId,
            'job_class' => static::class,
            'switch_database' => $this->switchDatabase,
            'apply_config' => $this->applyTenantConfig,
        ]);
    }

    /**
     * Log job failure
     */
    protected function logJobFailure(?\Throwable $exception): void
    {
        logger()->error('Tenant job failed', [
            'tenant_id' => $this->tenantId,
            'job_class' => static::class,
            'exception' => $exception?->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }

    /**
     * Get the tags for the job
     */
    public function tags(): array
    {
        $tags = ['tenant-aware'];

        if ($this->tenantId) {
            $tags[] = "tenant:{$this->tenantId}";
        }

        if ($this->tenant && isset($this->tenant->slug)) {
            $tags[] = "tenant-slug:{$this->tenant->slug}";
        }

        return array_merge($tags, $this->getJobTags());
    }

    /**
     * Get job-specific tags
     */
    protected function getJobTags(): array
    {
        return [];
    }

    /**
     * Prepare job for serialization
     */
    public function __sleep(): array
    {
        $properties = array_keys(get_object_vars($this));

        // Remove the tenant object from serialization
        $properties = array_filter($properties, fn($prop) => $prop !== 'tenant');

        return $properties;
    }

    /**
     * Handle job after deserialization
     */
    public function __wakeup(): void
    {
        // Tenant will be resolved when handle() is called
        $this->tenant = null;
    }

    /**
     * Create a tenant-aware job instance
     */
    public static function withTenant(object $tenant, ...$args): static
    {
        $job = new static(...$args);
        return $job->setTenant($tenant);
    }

    /**
     * Create a job for current tenant
     */
    public static function forCurrentTenant(...$args): static
    {
        $tenant = app(TenantManager::class)->getCurrentTenant();

        if (!$tenant) {
            throw new \RuntimeException('No current tenant available');
        }

        return static::withTenant($tenant, ...$args);
    }

    /**
     * Dispatch job with tenant context
     */
    public static function dispatchForTenant(object $tenant, ...$args): mixed
    {
        return static::withTenant($tenant, ...$args)->dispatch();
    }

    /**
     * Dispatch job for current tenant
     */
    public static function dispatchForCurrentTenant(...$args): mixed
    {
        return static::forCurrentTenant(...$args)->dispatch();
    }

    /**
     * Set whether to switch database connection
     */
    public function setSwitchDatabase(bool $switch): self
    {
        $this->switchDatabase = $switch;
        return $this;
    }

    /**
     * Set whether to apply tenant configuration
     */
    public function setApplyTenantConfig(bool $apply): self
    {
        $this->applyTenantConfig = $apply;
        return $this;
    }

    /**
     * Get job display name with tenant info
     */
    public function displayName(): string
    {
        $baseName = class_basename(static::class);

        if ($this->tenantId) {
            return "{$baseName} (Tenant: {$this->tenantId})";
        }

        return $baseName;
    }

    /**
     * Get the number of seconds the job can run before timing out
     */
    public function timeout(): int
    {
        return config('queue.tenant_job_timeout', 300); // 5 minutes default
    }

    /**
     * Get the number of times the job may be attempted
     */
    public function tries(): int
    {
        return config('queue.tenant_job_tries', 3);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job
     */
    public function backoff(): array
    {
        return [1, 5, 15]; // Progressive backoff
    }
}