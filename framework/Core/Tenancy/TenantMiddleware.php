<?php

namespace XLinic\Framework\Core\Tenancy;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use XLinic\Framework\Core\Tenancy\TenantManager;

/**
 * Tenant Middleware
 *
 * HTTP middleware for tenant resolution and context switching.
 * Handles automatic tenant detection from various sources,
 * database connection switching, and tenant isolation.
 *
 * @package XLinic\Framework\Core\Tenancy
 */
class TenantMiddleware
{
    /**
     * Tenant resolution strategies
     */
    public const STRATEGY_SUBDOMAIN = 'subdomain';
    public const STRATEGY_DOMAIN = 'domain';
    public const STRATEGY_HEADER = 'header';
    public const STRATEGY_PATH = 'path';
    public const STRATEGY_PARAMETER = 'parameter';

    /**
     * The tenant manager instance
     */
    protected TenantManager $tenantManager;

    /**
     * Create a new middleware instance
     */
    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
    }

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next, ?string $strategy = null, array $options = []): mixed
    {
        // Parse options if passed as string
        if (is_string($options)) {
            parse_str($options, $options);
        }

        $strategy = $strategy ?: $this->getDefaultStrategy();

        try {
            // Resolve tenant from request
            $tenant = $this->resolveTenant($request, $strategy, $options);

            if ($tenant) {
                // Set tenant context
                $this->tenantManager->setCurrentTenant($tenant);

                // Switch database connection if needed
                $this->switchDatabaseConnection($tenant, $options);

                // Set tenant-specific configuration
                $this->setTenantConfig($tenant, $options);

                // Add tenant to request
                $request->attributes->set('tenant', $tenant);

                // Log tenant resolution
                $this->logTenantResolution($request, $tenant, $strategy);
            } else {
                // Handle tenant not found - returns null to continue or Response to abort
                $notFoundResponse = $this->handleTenantNotFound($request, $strategy, $options);
                if ($notFoundResponse !== null) {
                    return $notFoundResponse;
                }
                // Continue without tenant context (for central routes like admin panel)
            }

            // Process the request
            $response = $next($request);

            // Add tenant headers to response if needed (only if tenant was resolved)
            if ($tenant && ($options['include_headers'] ?? true)) {
                $response = $this->addTenantHeaders($response, $tenant);
            }

            return $response;

        } catch (\Exception $e) {
            // Handle tenant resolution errors
            return $this->handleTenantError($request, $e, $options);
        } finally {
            // Clear tenant context after request (only if one was set)
            if (isset($tenant) && $tenant && ($options['clear_after_request'] ?? true)) {
                $this->tenantManager->clearCurrentTenant();
            }
        }
    }

    /**
     * Resolve tenant from request
     */
    protected function resolveTenant(Request $request, string $strategy, array $options): ?object
    {
        $identifier = $this->extractTenantIdentifier($request, $strategy, $options);

        if (!$identifier) {
            return null;
        }

        // Try to resolve tenant
        $tenant = $this->tenantManager->findTenant($identifier);

        if (!$tenant) {
            // Try alternative resolution methods
            $tenant = $this->tryAlternativeResolution($request, $identifier, $options);
        }

        // Validate tenant if found
        if ($tenant && !$this->validateTenant($tenant, $request, $options)) {
            return null;
        }

        return $tenant;
    }

    /**
     * Extract tenant identifier from request
     */
    protected function extractTenantIdentifier(Request $request, string $strategy, array $options): ?string
    {
        return match ($strategy) {
            self::STRATEGY_SUBDOMAIN => $this->extractFromSubdomain($request, $options),
            self::STRATEGY_DOMAIN => $this->extractFromDomain($request, $options),
            self::STRATEGY_HEADER => $this->extractFromHeader($request, $options),
            self::STRATEGY_PATH => $this->extractFromPath($request, $options),
            self::STRATEGY_PARAMETER => $this->extractFromParameter($request, $options),
            default => null
        };
    }

    /**
     * Extract tenant from subdomain
     */
    protected function extractFromSubdomain(Request $request, array $options): ?string
    {
        $host = $request->getHost();
        $parts = explode('.', $host);

        // Skip www
        if ($parts[0] === 'www') {
            array_shift($parts);
        }

        // Need at least 3 parts for subdomain (tenant.domain.com)
        if (count($parts) < 3) {
            return null;
        }

        $subdomain = $parts[0];

        // Validate subdomain format
        if (!preg_match('/^[a-z0-9\-]+$/', $subdomain)) {
            return null;
        }

        // Check excluded subdomains
        $excludedSubdomains = $options['excluded'] ?? ['www', 'api', 'admin', 'app'];
        if (in_array($subdomain, $excludedSubdomains)) {
            return null;
        }

        return $subdomain;
    }

    /**
     * Extract tenant from domain
     */
    protected function extractFromDomain(Request $request, array $options): ?string
    {
        $host = $request->getHost();

        // Remove www prefix if present
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        // Map domain to tenant identifier
        $domainMapping = $options['domain_mapping'] ?? [];

        return $domainMapping[$host] ?? $host;
    }

    /**
     * Extract tenant from header
     */
    protected function extractFromHeader(Request $request, array $options): ?string
    {
        $headerName = $options['header_name'] ?? 'X-Tenant-ID';
        return $request->header($headerName);
    }

    /**
     * Extract tenant from path
     */
    protected function extractFromPath(Request $request, array $options): ?string
    {
        $path = $request->path();
        $segments = explode('/', $path);

        $position = $options['position'] ?? 0; // First segment by default

        return $segments[$position] ?? null;
    }

    /**
     * Extract tenant from parameter
     */
    protected function extractFromParameter(Request $request, array $options): ?string
    {
        $parameterName = $options['parameter_name'] ?? 'tenant';

        // Try route parameter first
        $tenant = $request->route($parameterName);

        // Fall back to query parameter
        if (!$tenant) {
            $tenant = $request->query($parameterName);
        }

        return $tenant;
    }

    /**
     * Try alternative resolution methods
     */
    protected function tryAlternativeResolution(Request $request, string $identifier, array $options): ?object
    {
        // Try resolving by slug if identifier looks like one
        if (preg_match('/^[a-z0-9\-]+$/', $identifier)) {
            return $this->tenantManager->findTenantBySlug($identifier);
        }

        // Try resolving by custom field
        $customField = $options['custom_field'] ?? null;
        if ($customField) {
            return $this->tenantManager->findTenantBy($customField, $identifier);
        }

        return null;
    }

    /**
     * Validate tenant
     */
    protected function validateTenant(object $tenant, Request $request, array $options): bool
    {
        // Check if tenant is active
        if (isset($tenant->status) && $tenant->status !== 'active') {
            return false;
        }

        // Check subscription status
        if (isset($tenant->subscription_status) && $tenant->subscription_status === 'cancelled') {
            return false;
        }

        // Check IP restrictions
        if ($this->hasIpRestrictions($tenant) && !$this->isAllowedIp($tenant, $request->ip())) {
            return false;
        }

        // Custom validation
        $customValidator = $options['validator'] ?? null;
        if (is_callable($customValidator)) {
            return $customValidator($tenant, $request);
        }

        return true;
    }

    /**
     * Switch database connection
     */
    protected function switchDatabaseConnection(object $tenant, array $options): void
    {
        if (!($options['switch_database'] ?? true)) {
            return;
        }

        $connectionName = $options['connection_name'] ?? 'tenant';
        $connectionConfig = $this->getTenantDatabaseConfig($tenant, $options);

        if ($connectionConfig) {
            Config::set("database.connections.{$connectionName}", $connectionConfig);
            DB::purge($connectionName);
            DB::setDefaultConnection($connectionName);
        }
    }

    /**
     * Get tenant database configuration
     */
    protected function getTenantDatabaseConfig(object $tenant, array $options): ?array
    {
        // Check if tenant has custom database configuration
        if (isset($tenant->database_config)) {
            return is_array($tenant->database_config)
                ? $tenant->database_config
                : json_decode($tenant->database_config, true);
        }

        // Use pattern-based database naming
        $pattern = $options['database_pattern'] ?? 'tenant_{id}';
        $databaseName = str_replace('{id}', $tenant->id, $pattern);

        $baseConfig = config('database.connections.' . config('database.default'));

        return array_merge($baseConfig, [
            'database' => $databaseName,
        ]);
    }

    /**
     * Set tenant-specific configuration
     */
    protected function setTenantConfig(object $tenant, array $options): void
    {
        // Set tenant-specific app configuration
        if (isset($tenant->config)) {
            $tenantConfig = is_array($tenant->config)
                ? $tenant->config
                : json_decode($tenant->config, true);

            foreach ($tenantConfig as $key => $value) {
                Config::set($key, $value);
            }
        }

        // Set tenant timezone
        if (isset($tenant->timezone)) {
            Config::set('app.timezone', $tenant->timezone);
            date_default_timezone_set($tenant->timezone);
        }

        // Set tenant locale
        if (isset($tenant->locale)) {
            Config::set('app.locale', $tenant->locale);
            app()->setLocale($tenant->locale);
        }

        // Set tenant currency
        if (isset($tenant->currency)) {
            Config::set('app.currency', $tenant->currency);
        }
    }

    /**
     * Handle tenant not found
     */
    protected function handleTenantNotFound(Request $request, string $strategy, array $options): mixed
    {
        $action = $options['on_not_found'] ?? 'continue';

        return match ($action) {
            'redirect' => $this->redirectToDefaultTenant($request, $options),
            'error' => $this->createTenantNotFoundResponse($request, $strategy),
            default => null, // Continue without tenant context
        };
    }

    /**
     * Handle tenant resolution errors
     */
    protected function handleTenantError(Request $request, \Exception $exception, array $options): Response
    {
        logger()->error('Tenant resolution error', [
            'exception' => $exception->getMessage(),
            'url' => $request->fullUrl(),
            'strategy' => $options['strategy'] ?? 'unknown',
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Tenant resolution failed',
                'message' => 'Unable to determine tenant context',
            ], 500);
        }

        // Return a simple error response instead of trying to render a view
        return response('Unable to determine tenant context', 500);
    }

    /**
     * Create tenant not found response
     */
    protected function createTenantNotFoundResponse(Request $request, string $strategy): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Tenant not found',
                'message' => "Unable to resolve tenant using strategy: {$strategy}",
            ], 404);
        }

        // Return a simple error response instead of trying to render a view
        return response("Tenant not found. Unable to resolve tenant using strategy: {$strategy}", 404);
    }

    /**
     * Redirect to default tenant
     */
    protected function redirectToDefaultTenant(Request $request, array $options): Response
    {
        $defaultTenant = $options['default_tenant'] ?? 'app';
        $currentHost = $request->getHost();

        // Build redirect URL based on strategy
        $redirectUrl = match ($options['strategy'] ?? self::STRATEGY_SUBDOMAIN) {
            self::STRATEGY_SUBDOMAIN => $this->buildSubdomainRedirectUrl($request, $defaultTenant),
            self::STRATEGY_PATH => $this->buildPathRedirectUrl($request, $defaultTenant),
            default => $request->fullUrl()
        };

        return response()->redirectTo($redirectUrl, 302);
    }

    /**
     * Build subdomain redirect URL
     */
    protected function buildSubdomainRedirectUrl(Request $request, string $tenant): string
    {
        $host = $request->getHost();
        $parts = explode('.', $host);

        // Replace first part with tenant
        $parts[0] = $tenant;

        $newHost = implode('.', $parts);
        $scheme = $request->getScheme();
        $port = $request->getPort();
        $path = $request->getRequestUri();

        $url = "{$scheme}://{$newHost}";

        if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
            $url .= ":{$port}";
        }

        return $url . $path;
    }

    /**
     * Build path redirect URL
     */
    protected function buildPathRedirectUrl(Request $request, string $tenant): string
    {
        $path = $request->path();
        $segments = explode('/', $path);
        $segments[0] = $tenant;

        $newPath = implode('/', $segments);
        return $request->url() . '/' . $newPath;
    }

    /**
     * Add tenant headers to response
     */
    protected function addTenantHeaders(Response $response, object $tenant): Response
    {
        $headers = [
            'X-Tenant-ID' => $tenant->id,
            'X-Tenant-Slug' => $tenant->slug ?? null,
            'X-Tenant-Name' => $tenant->name ?? null,
        ];

        foreach ($headers as $key => $value) {
            if ($value !== null) {
                $response->headers->set($key, (string) $value);
            }
        }

        return $response;
    }

    /**
     * Check if tenant has IP restrictions
     */
    protected function hasIpRestrictions(object $tenant): bool
    {
        return !empty($tenant->allowed_ips) || !empty($tenant->blocked_ips);
    }

    /**
     * Check if IP is allowed for tenant
     */
    protected function isAllowedIp(object $tenant, string $ip): bool
    {
        // Check blocked IPs first
        if (!empty($tenant->blocked_ips)) {
            $blockedIps = is_array($tenant->blocked_ips)
                ? $tenant->blocked_ips
                : json_decode($tenant->blocked_ips, true);

            if (in_array($ip, $blockedIps)) {
                return false;
            }
        }

        // Check allowed IPs
        if (!empty($tenant->allowed_ips)) {
            $allowedIps = is_array($tenant->allowed_ips)
                ? $tenant->allowed_ips
                : json_decode($tenant->allowed_ips, true);

            return in_array($ip, $allowedIps);
        }

        return true; // No restrictions
    }

    /**
     * Get default resolution strategy
     */
    protected function getDefaultStrategy(): string
    {
        return config('tenant.default_strategy', self::STRATEGY_SUBDOMAIN);
    }

    /**
     * Log tenant resolution
     */
    protected function logTenantResolution(Request $request, object $tenant, string $strategy): void
    {
        logger()->debug('Tenant resolved', [
            'tenant_id' => $tenant->id,
            'strategy' => $strategy,
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
        ]);
    }

    /**
     * Create tenant-aware middleware for specific strategies
     */
    public static function subdomain(array $options = []): string
    {
        $optionsString = http_build_query($options);
        return static::class . ':' . self::STRATEGY_SUBDOMAIN . ',' . $optionsString;
    }

    public static function domain(array $options = []): string
    {
        $optionsString = http_build_query($options);
        return static::class . ':' . self::STRATEGY_DOMAIN . ',' . $optionsString;
    }

    public static function header(string $headerName = 'X-Tenant-ID', array $options = []): string
    {
        $options['header_name'] = $headerName;
        $optionsString = http_build_query($options);
        return static::class . ':' . self::STRATEGY_HEADER . ',' . $optionsString;
    }

    public static function path(int $position = 0, array $options = []): string
    {
        $options['position'] = $position;
        $optionsString = http_build_query($options);
        return static::class . ':' . self::STRATEGY_PATH . ',' . $optionsString;
    }
}