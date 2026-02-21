<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * IP Whitelist Middleware
 *
 * Restricts access to routes based on IP address whitelist.
 * Supports tenant-specific whitelists and global whitelists.
 *
 * Usage in routes:
 *   Route::middleware('ip-whitelist')->group(...)                    // Uses tenant/global whitelist
 *   Route::middleware('ip-whitelist:admin')->group(...)              // Uses 'admin' whitelist from config
 *   Route::middleware('ip-whitelist:custom,192.168.1.1')->group(...) // Inline IPs
 *
 * Configuration (config/security.php):
 *   'ip_whitelist' => [
 *       'enabled' => true,
 *       'admin' => ['192.168.1.0/24', '10.0.0.1'],
 *       'api' => ['*'],  // Allow all
 *   ]
 */
class IpWhitelist
{
    /**
     * IPs that are always allowed (localhost, etc.)
     */
    protected array $alwaysAllowed = [
        '127.0.0.1',
        '::1',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $listName = null, string ...$inlineIps): Response
    {
        // Skip if IP whitelist is disabled globally
        if (!config('security.ip_whitelist.enabled', true)) {
            return $next($request);
        }

        $clientIp = $request->ip();

        // Always allow localhost
        if (in_array($clientIp, $this->alwaysAllowed)) {
            return $next($request);
        }

        // Build the whitelist to check against
        $whitelist = $this->buildWhitelist($request, $listName, $inlineIps);

        // Check if client IP is in whitelist
        if ($this->isIpAllowed($clientIp, $whitelist)) {
            return $next($request);
        }

        // Log the blocked attempt
        $this->logBlockedAttempt($request, $listName);

        return $this->buildBlockedResponse($request);
    }

    /**
     * Build the whitelist from various sources.
     */
    protected function buildWhitelist(Request $request, ?string $listName, array $inlineIps): array
    {
        $whitelist = [];

        // Add inline IPs if provided
        if (!empty($inlineIps)) {
            $whitelist = array_merge($whitelist, $inlineIps);
        }

        // Add IPs from named config list
        if ($listName && $listName !== 'custom') {
            $configList = config("security.ip_whitelist.{$listName}", []);
            $whitelist = array_merge($whitelist, $configList);
        }

        // Add tenant-specific whitelist if available
        $tenantWhitelist = $this->getTenantWhitelist($request);
        if (!empty($tenantWhitelist)) {
            $whitelist = array_merge($whitelist, $tenantWhitelist);
        }

        // Add global whitelist
        $globalWhitelist = config('security.ip_whitelist.global', []);
        $whitelist = array_merge($whitelist, $globalWhitelist);

        return array_unique(array_filter($whitelist));
    }

    /**
     * Get tenant-specific whitelist from settings.
     */
    protected function getTenantWhitelist(Request $request): array
    {
        $tenant = $request->attributes->get('tenant');

        if (!$tenant) {
            return [];
        }

        // Try to get from tenant settings
        $settings = $tenant->settings ?? [];

        return $settings['ip_whitelist'] ?? [];
    }

    /**
     * Check if an IP is allowed based on the whitelist.
     */
    protected function isIpAllowed(string $clientIp, array $whitelist): bool
    {
        // Empty whitelist means all IPs are allowed
        if (empty($whitelist)) {
            return true;
        }

        // Check for wildcard (allow all)
        if (in_array('*', $whitelist)) {
            return true;
        }

        foreach ($whitelist as $allowedIp) {
            // Direct match
            if ($allowedIp === $clientIp) {
                return true;
            }

            // CIDR notation match
            if (str_contains($allowedIp, '/') && $this->ipInCidr($clientIp, $allowedIp)) {
                return true;
            }

            // Wildcard pattern match (e.g., 192.168.1.*)
            if (str_contains($allowedIp, '*')) {
                $pattern = str_replace('.', '\.', $allowedIp);
                $pattern = str_replace('*', '\d+', $pattern);
                if (preg_match("/^{$pattern}$/", $clientIp)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if an IP is within a CIDR range.
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $mask] = explode('/', $cidr);

        // Handle IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = -1 << (32 - (int) $mask);

            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        // Handle IPv6 (simplified check)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ipBin = inet_pton($ip);
            $subnetBin = inet_pton($subnet);

            if ($ipBin === false || $subnetBin === false) {
                return false;
            }

            $maskBits = (int) $mask;
            $bytes = (int) floor($maskBits / 8);
            $bits = $maskBits % 8;

            // Compare full bytes
            if (substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
                return false;
            }

            // Compare remaining bits
            if ($bits > 0 && $bytes < 16) {
                $ipByte = ord($ipBin[$bytes]);
                $subnetByte = ord($subnetBin[$bytes]);
                $mask = 0xFF << (8 - $bits);

                return ($ipByte & $mask) === ($subnetByte & $mask);
            }

            return true;
        }

        return false;
    }

    /**
     * Log a blocked access attempt.
     */
    protected function logBlockedAttempt(Request $request, ?string $listName): void
    {
        // Track repeated attempts for potential alerts
        $cacheKey = "ip_blocked:{$request->ip()}";
        $attempts = Cache::increment($cacheKey);
        Cache::put($cacheKey, $attempts, now()->addHours(1));

        Log::warning('IP whitelist blocked access', [
            'ip' => $request->ip(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_agent' => $request->userAgent(),
            'list_name' => $listName,
            'tenant_id' => session('tenant_id') ?? $request->attributes->get('tenant_id'),
            'attempts_last_hour' => $attempts,
        ]);

        // Alert on repeated attempts
        if ($attempts >= 10) {
            Log::alert('Repeated blocked IP attempts detected', [
                'ip' => $request->ip(),
                'attempts' => $attempts,
            ]);
        }
    }

    /**
     * Build the blocked response.
     */
    protected function buildBlockedResponse(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => 'ip_not_allowed',
                'message' => 'Access denied. Your IP address is not authorized.',
            ], 403);
        }

        abort(403, 'Access denied. Your IP address is not authorized to access this resource.');
    }
}
