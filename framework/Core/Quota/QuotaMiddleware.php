<?php

namespace XLinic\Framework\Core\Quota;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Quota Middleware
 *
 * HTTP middleware for enforcing resource quotas on requests.
 * Provides rate limiting, usage tracking, and automatic
 * quota enforcement with configurable actions and responses.
 *
 * @package XLinic\Framework\Core\Quota
 */
class QuotaMiddleware
{
    /**
     * Response formats
     */
    public const FORMAT_JSON = 'json';
    public const FORMAT_HTML = 'html';

    /**
     * The quota service instance
     */
    protected QuotaService $quotaService;

    /**
     * Create a new middleware instance
     */
    public function __construct(QuotaService $quotaService)
    {
        $this->quotaService = $quotaService;
    }

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next, string $quotaType = QuotaService::TYPE_API_CALLS, int $amount = 1, array $options = []): mixed
    {
        // Parse options if passed as string
        if (is_string($options)) {
            parse_str($options, $options);
        }

        // Check if quota should be skipped
        if ($this->shouldSkipQuota($request, $options)) {
            return $next($request);
        }

        // Get tenant ID from request or current context
        $tenantId = $this->getTenantId($request, $options);

        // Check quota before processing request
        if (!$this->quotaService->allows($quotaType, $amount, $tenantId)) {
            return $this->handleQuotaExceeded($request, $quotaType, $tenantId, $options);
        }

        // Process the request
        $response = $next($request);

        // Consume quota after successful request
        if ($this->shouldConsumeQuota($response, $options)) {
            $this->quotaService->consume($quotaType, $amount, $tenantId);
        }

        // Add quota headers to response
        if ($options['include_headers'] ?? true) {
            $response = $this->addQuotaHeaders($response, $quotaType, $tenantId);
        }

        return $response;
    }

    /**
     * Handle rate limiting for API endpoints
     */
    public static function rateLimit(string $quotaType = QuotaService::TYPE_API_CALLS, int $amount = 1, array $options = []): string
    {
        $optionsString = http_build_query($options);
        return static::class . ":{$quotaType},{$amount},{$optionsString}";
    }

    /**
     * Handle quota exceeded scenario
     */
    protected function handleQuotaExceeded(Request $request, string $quotaType, ?string $tenantId, array $options): Response
    {
        $usage = $this->quotaService->getUsage($quotaType, $tenantId);
        $action = $options['action'] ?? QuotaService::ACTION_DENY;

        // Log quota exceeded event
        $this->logQuotaExceeded($request, $quotaType, $tenantId, $usage);

        // Handle different actions
        switch ($action) {
            case QuotaService::ACTION_THROTTLE:
                return $this->handleThrottle($request, $quotaType, $usage, $options);

            case QuotaService::ACTION_ALLOW:
                // Force consume and allow request
                $this->quotaService->forceConsume($quotaType, $options['amount'] ?? 1, $tenantId);
                return response()->json([
                    'message' => 'Request allowed despite quota exceeded',
                    'quota' => $usage,
                ], 200);

            case QuotaService::ACTION_DENY:
            default:
                return $this->createQuotaExceededResponse($request, $quotaType, $usage, $options);
        }
    }

    /**
     * Handle throttling
     */
    protected function handleThrottle(Request $request, string $quotaType, array $usage, array $options): Response
    {
        $delay = $options['throttle_delay'] ?? 1; // seconds
        $maxDelay = $options['max_throttle_delay'] ?? 60;

        // Calculate progressive delay based on over-usage
        $overUsage = max(0, $usage['current'] - $usage['limit']);
        $calculatedDelay = min($maxDelay, $delay * (1 + $overUsage));

        // Sleep for the calculated delay
        sleep((int) $calculatedDelay);

        // Allow the request to continue but with delay
        return response()->json([
            'message' => 'Request throttled due to quota usage',
            'throttle_delay' => $calculatedDelay,
            'quota' => $usage,
        ], 200);
    }

    /**
     * Create quota exceeded response
     */
    protected function createQuotaExceededResponse(Request $request, string $quotaType, array $usage, array $options): Response
    {
        $format = $this->getResponseFormat($request, $options);
        $statusCode = $options['status_code'] ?? 429; // Too Many Requests

        $data = [
            'error' => 'Quota exceeded',
            'message' => $this->getQuotaExceededMessage($quotaType, $usage),
            'quota_type' => $quotaType,
            'usage' => $usage,
            'retry_after' => $usage['reset_at']?->diffInSeconds(now()) ?? 3600,
        ];

        if ($format === self::FORMAT_HTML) {
            return response()->view('errors.quota-exceeded', $data, $statusCode);
        }

        $response = response()->json($data, $statusCode);

        // Add standard rate limiting headers
        return $this->addRateLimitHeaders($response, $usage);
    }

    /**
     * Add quota headers to response
     */
    protected function addQuotaHeaders(Response $response, string $quotaType, ?string $tenantId): Response
    {
        $usage = $this->quotaService->getUsage($quotaType, $tenantId);

        $headers = [
            'X-RateLimit-Limit' => $usage['limit'],
            'X-RateLimit-Remaining' => $usage['remaining'],
            'X-RateLimit-Reset' => $usage['reset_at']?->getTimestamp(),
            'X-Quota-Type' => $quotaType,
        ];

        foreach ($headers as $key => $value) {
            if ($value !== null) {
                $response->headers->set($key, (string) $value);
            }
        }

        return $response;
    }

    /**
     * Add rate limiting headers
     */
    protected function addRateLimitHeaders(Response $response, array $usage): Response
    {
        $headers = [
            'Retry-After' => $usage['reset_at']?->diffInSeconds(now()) ?? 3600,
            'X-RateLimit-Limit' => $usage['limit'],
            'X-RateLimit-Remaining' => 0,
            'X-RateLimit-Reset' => $usage['reset_at']?->getTimestamp(),
        ];

        foreach ($headers as $key => $value) {
            if ($value !== null) {
                $response->headers->set($key, (string) $value);
            }
        }

        return $response;
    }

    /**
     * Check if quota should be skipped
     */
    protected function shouldSkipQuota(Request $request, array $options): bool
    {
        // Skip for certain routes
        $skipRoutes = $options['skip_routes'] ?? [];
        if (!empty($skipRoutes)) {
            $currentRoute = $request->route()?->getName();
            if (in_array($currentRoute, $skipRoutes)) {
                return true;
            }
        }

        // Skip for certain IPs
        $skipIps = $options['skip_ips'] ?? [];
        if (!empty($skipIps) && in_array($request->ip(), $skipIps)) {
            return true;
        }

        // Skip for certain user roles
        $user = $request->user();
        if ($user) {
            $skipRoles = $options['skip_roles'] ?? [];
            if (!empty($skipRoles)) {
                $userRoles = $this->getUserRoles($user);
                if (!empty(array_intersect($userRoles, $skipRoles))) {
                    return true;
                }
            }
        }

        // Skip based on custom condition
        $skipCondition = $options['skip_condition'] ?? null;
        if (is_callable($skipCondition)) {
            return $skipCondition($request);
        }

        return false;
    }

    /**
     * Check if quota should be consumed
     */
    protected function shouldConsumeQuota(mixed $response, array $options): bool
    {
        // Only consume quota for successful responses
        $consumeOnSuccess = $options['consume_on_success'] ?? true;
        if (!$consumeOnSuccess) {
            return true; // Always consume if not checking success
        }

        // Check response status
        if ($response instanceof Response) {
            $statusCode = $response->getStatusCode();
            return $statusCode >= 200 && $statusCode < 400;
        }

        if ($response instanceof SymfonyResponse) {
            $statusCode = $response->getStatusCode();
            return $statusCode >= 200 && $statusCode < 400;
        }

        return true; // Default to consuming quota
    }

    /**
     * Get tenant ID for quota tracking
     */
    protected function getTenantId(Request $request, array $options): ?string
    {
        // Check if tenant ID is provided in options
        if (isset($options['tenant_id'])) {
            return $options['tenant_id'];
        }

        // Try to get tenant from request header
        $tenantId = $request->header('X-Tenant-ID');
        if ($tenantId) {
            return $tenantId;
        }

        // Try to get tenant from subdomain
        $host = $request->getHost();
        $parts = explode('.', $host);
        if (count($parts) > 2 && $parts[0] !== 'www') {
            return $parts[0]; // Use subdomain as tenant ID
        }

        // Try to get tenant from current context
        return $this->quotaService->getCurrentTenantId();
    }

    /**
     * Get response format
     */
    protected function getResponseFormat(Request $request, array $options): string
    {
        // Check options first
        if (isset($options['format'])) {
            return $options['format'];
        }

        // Check Accept header
        if ($request->expectsJson()) {
            return self::FORMAT_JSON;
        }

        // Check if it's an API request
        if (str_starts_with($request->path(), 'api/')) {
            return self::FORMAT_JSON;
        }

        return self::FORMAT_HTML;
    }

    /**
     * Get quota exceeded message
     */
    protected function getQuotaExceededMessage(string $quotaType, array $usage): string
    {
        $resetTime = $usage['reset_at'];
        $timeRemaining = $resetTime ? $resetTime->diffForHumans() : 'in some time';

        return match ($quotaType) {
            QuotaService::TYPE_API_CALLS => "API rate limit exceeded. Try again {$timeRemaining}.",
            QuotaService::TYPE_STORAGE => "Storage quota exceeded. Please upgrade your plan or free up space.",
            QuotaService::TYPE_USERS => "User limit exceeded. Please upgrade your plan to add more users.",
            QuotaService::TYPE_RECORDS => "Record limit exceeded. Please upgrade your plan or archive old records.",
            QuotaService::TYPE_REPORTS => "Report generation limit exceeded. Try again {$timeRemaining}.",
            QuotaService::TYPE_EMAILS => "Email sending limit exceeded. Try again {$timeRemaining}.",
            QuotaService::TYPE_FILE_UPLOADS => "File upload limit exceeded. Try again {$timeRemaining}.",
            default => "Quota limit exceeded for {$quotaType}. Try again {$timeRemaining}."
        };
    }

    /**
     * Get user roles
     */
    protected function getUserRoles(object $user): array
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
     * Log quota exceeded event
     */
    protected function logQuotaExceeded(Request $request, string $quotaType, ?string $tenantId, array $usage): void
    {
        logger()->warning('Quota exceeded', [
            'quota_type' => $quotaType,
            'tenant_id' => $tenantId,
            'usage' => $usage,
            'request_path' => $request->path(),
            'request_method' => $request->method(),
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Handle quota for specific routes
     */
    public static function forRoute(string $routePattern, string $quotaType, int $amount = 1, array $options = []): array
    {
        return [
            'pattern' => $routePattern,
            'middleware' => static::rateLimit($quotaType, $amount, $options),
        ];
    }

    /**
     * Create API rate limiting middleware
     */
    public static function api(int $requestsPerMinute = 60, array $options = []): string
    {
        return static::rateLimit(
            QuotaService::TYPE_API_CALLS,
            1,
            array_merge($options, [
                'period' => QuotaService::PERIOD_MINUTE,
                'limit' => $requestsPerMinute,
            ])
        );
    }

    /**
     * Create file upload limiting middleware
     */
    public static function fileUpload(int $uploadsPerDay = 100, array $options = []): string
    {
        return static::rateLimit(
            QuotaService::TYPE_FILE_UPLOADS,
            1,
            array_merge($options, [
                'period' => QuotaService::PERIOD_DAY,
                'limit' => $uploadsPerDay,
            ])
        );
    }

    /**
     * Create report generation limiting middleware
     */
    public static function reportGeneration(int $reportsPerHour = 10, array $options = []): string
    {
        return static::rateLimit(
            QuotaService::TYPE_REPORTS,
            1,
            array_merge($options, [
                'period' => QuotaService::PERIOD_HOUR,
                'limit' => $reportsPerHour,
            ])
        );
    }
}