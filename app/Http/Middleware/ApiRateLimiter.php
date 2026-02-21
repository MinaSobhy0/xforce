<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Rate Limiter Middleware
 *
 * Provides granular rate limiting for API endpoints with support for:
 * - Per-user rate limiting
 * - Per-IP rate limiting for unauthenticated requests
 * - Per-tenant rate limiting
 * - Different limits for different endpoint groups
 * - Gradual backoff for repeated violations
 *
 * Usage in routes:
 *   Route::middleware('api-limit')->group(...)              // Default: 60 requests/minute
 *   Route::middleware('api-limit:100,1')->group(...)        // 100 requests per minute
 *   Route::middleware('api-limit:1000,60')->group(...)      // 1000 requests per hour
 *   Route::middleware('api-limit:10,1,strict')->group(...)  // Strict mode: longer lockout
 */
class ApiRateLimiter
{
    protected RateLimiter $limiter;

    /**
     * Default rate limit configurations by tier.
     */
    protected array $tierLimits = [
        'free' => ['requests' => 60, 'minutes' => 1],
        'basic' => ['requests' => 120, 'minutes' => 1],
        'professional' => ['requests' => 300, 'minutes' => 1],
        'enterprise' => ['requests' => 1000, 'minutes' => 1],
    ];

    /**
     * Endpoints with special rate limits.
     */
    protected array $specialLimits = [
        'api/auth/login' => ['requests' => 5, 'minutes' => 1],
        'api/auth/register' => ['requests' => 3, 'minutes' => 1],
        'api/password/reset' => ['requests' => 3, 'minutes' => 5],
        'api/export/*' => ['requests' => 5, 'minutes' => 10],
        'api/reports/*' => ['requests' => 10, 'minutes' => 5],
        'api/bulk/*' => ['requests' => 5, 'minutes' => 5],
    ];

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1, string $mode = 'normal'): Response
    {
        // Check for special endpoint limits first
        $specialLimit = $this->getSpecialLimit($request);
        if ($specialLimit) {
            $maxAttempts = $specialLimit['requests'];
            $decayMinutes = $specialLimit['minutes'];
        }

        // Apply tier-based limits if user is authenticated
        if ($user = $request->user()) {
            $tierLimit = $this->getTierLimit($user);
            if ($tierLimit && !$specialLimit) {
                $maxAttempts = $tierLimit['requests'];
                $decayMinutes = $tierLimit['minutes'];
            }
        }

        $key = $this->resolveRequestSignature($request);

        // Check if currently in lockout
        $lockoutKey = "api_lockout:{$key}";
        if ($lockoutUntil = Cache::get($lockoutKey)) {
            if (now()->timestamp < $lockoutUntil) {
                return $this->buildLockoutResponse($lockoutUntil - now()->timestamp);
            }
            Cache::forget($lockoutKey);
        }

        // Check rate limit
        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            // Apply lockout for strict mode or repeated violations
            if ($mode === 'strict') {
                $this->applyLockout($key, $lockoutKey);
            }

            return $this->buildResponse($key, $maxAttempts, $decayMinutes);
        }

        $this->limiter->hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts),
            $this->limiter->availableIn($key)
        );
    }

    /**
     * Resolve the rate limiting key for the request.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $parts = [];

        // Include tenant ID if available
        if ($tenantId = session('tenant_id') ?? $request->attributes->get('tenant_id')) {
            $parts[] = "tenant:{$tenantId}";
        }

        // Include user ID if authenticated
        if ($user = $request->user()) {
            $parts[] = "user:{$user->id}";
        } else {
            // Use IP for unauthenticated requests
            $parts[] = "ip:{$request->ip()}";
        }

        // Include route signature for granular limiting
        $parts[] = "route:" . sha1($request->method() . '|' . $request->path());

        return 'api_rate_limit:' . implode(':', $parts);
    }

    /**
     * Get special rate limit for specific endpoints.
     */
    protected function getSpecialLimit(Request $request): ?array
    {
        $path = $request->path();

        foreach ($this->specialLimits as $pattern => $limit) {
            if ($request->is($pattern)) {
                return $limit;
            }
        }

        return null;
    }

    /**
     * Get tier-based rate limit for the user.
     */
    protected function getTierLimit($user): ?array
    {
        // Try to get tenant's plan tier
        $tier = 'basic'; // Default tier

        if (method_exists($user, 'tenant') && $user->tenant) {
            $tier = $user->tenant->plan?->code ?? $user->tenant->subscription_plan ?? 'basic';
        }

        // Map plan codes to tiers
        $tier = match (true) {
            str_contains(strtolower($tier), 'enterprise') => 'enterprise',
            str_contains(strtolower($tier), 'professional'), str_contains(strtolower($tier), 'pro') => 'professional',
            str_contains(strtolower($tier), 'basic'), str_contains(strtolower($tier), 'starter') => 'basic',
            default => 'free',
        };

        return $this->tierLimits[$tier] ?? null;
    }

    /**
     * Calculate remaining attempts.
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->limiter->attempts($key));
    }

    /**
     * Apply a lockout for repeated violations.
     */
    protected function applyLockout(string $key, string $lockoutKey): void
    {
        // Get current violation count
        $violationKey = "api_violations:{$key}";
        $violations = Cache::increment($violationKey);
        Cache::put($violationKey, $violations, now()->addHours(24));

        // Calculate lockout duration based on violations (exponential backoff)
        $lockoutMinutes = min(60, pow(2, $violations - 1)); // 1, 2, 4, 8, 16, 32, 60 minutes

        Cache::put($lockoutKey, now()->addMinutes($lockoutMinutes)->timestamp, now()->addMinutes($lockoutMinutes));

        // Log the lockout
        \Log::warning('API rate limit lockout applied', [
            'key' => $key,
            'violations' => $violations,
            'lockout_minutes' => $lockoutMinutes,
        ]);
    }

    /**
     * Build the rate limit exceeded response.
     */
    protected function buildResponse(string $key, int $maxAttempts, int $decayMinutes): Response
    {
        $retryAfter = $this->limiter->availableIn($key);

        return response()->json([
            'success' => false,
            'error' => 'rate_limit_exceeded',
            'message' => 'Too many requests. Please slow down.',
            'retry_after' => $retryAfter,
            'limit' => $maxAttempts,
            'window_minutes' => $decayMinutes,
        ], 429)->withHeaders([
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
        ]);
    }

    /**
     * Build the lockout response.
     */
    protected function buildLockoutResponse(int $secondsRemaining): Response
    {
        return response()->json([
            'success' => false,
            'error' => 'rate_limit_lockout',
            'message' => 'You have been temporarily locked out due to repeated rate limit violations.',
            'retry_after' => $secondsRemaining,
        ], 429)->withHeaders([
            'Retry-After' => $secondsRemaining,
            'X-RateLimit-Limit' => 0,
            'X-RateLimit-Remaining' => 0,
            'X-RateLimit-Reset' => now()->addSeconds($secondsRemaining)->timestamp,
        ]);
    }

    /**
     * Add rate limit headers to the response.
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts, int $retryAfter): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
        ]);

        return $response;
    }
}
