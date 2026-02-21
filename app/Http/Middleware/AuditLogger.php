<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to log all significant HTTP requests for audit purposes.
 *
 * Usage in routes:
 *   Route::middleware('audit')->group(...)
 *   Route::middleware('audit:login,logout')->group(...)  // Only log specific events
 */
class AuditLogger
{
    /**
     * Events that should always be logged.
     */
    protected array $auditableEvents = [
        'login',
        'logout',
        'failed_login',
        'password_change',
        'profile_update',
        'permission_change',
        'data_export',
        'data_delete',
        'settings_change',
        'payment',
        'refund',
    ];

    /**
     * HTTP methods to audit (modifying operations).
     */
    protected array $auditableMethods = [
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
    ];

    /**
     * Routes to exclude from auditing.
     */
    protected array $excludedRoutes = [
        'livewire/*',
        'broadcasting/*',
        '_debugbar/*',
        'horizon/*',
        'telescope/*',
        'health',
        'pulse/*',
    ];

    /**
     * Sensitive fields to mask in logs.
     */
    protected array $sensitiveFields = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
        'secret',
        'token',
        'api_key',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string ...$events): Response
    {
        // Process the request first
        $response = $next($request);

        // Check if we should audit this request
        if (!$this->shouldAudit($request, $events)) {
            return $response;
        }

        // Log the audit entry asynchronously if possible
        try {
            $this->logAudit($request, $response, $events);
        } catch (\Exception $e) {
            // Don't fail the request if audit logging fails
            Log::error('Audit logging failed', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl(),
            ]);
        }

        return $response;
    }

    /**
     * Determine if the request should be audited.
     */
    protected function shouldAudit(Request $request, array $events): bool
    {
        // If specific events are passed, always audit
        if (!empty($events)) {
            return true;
        }

        // Skip excluded routes
        foreach ($this->excludedRoutes as $pattern) {
            if ($request->is($pattern)) {
                return false;
            }
        }

        // Only audit modifying HTTP methods by default
        if (!in_array($request->method(), $this->auditableMethods)) {
            return false;
        }

        // Skip AJAX polling requests
        if ($request->ajax() && $request->method() === 'GET') {
            return false;
        }

        return true;
    }

    /**
     * Log the audit entry.
     */
    protected function logAudit(Request $request, Response $response, array $events): void
    {
        $user = $request->user();
        $eventType = $this->determineEventType($request, $events);

        AuditLog::create([
            'tenant_id' => session('tenant_id') ?? $request->attributes->get('tenant_id'),
            'user_id' => $user?->id,
            'user_type' => $user ? get_class($user) : null,
            'event' => $eventType,
            'auditable_type' => $this->getAuditableType($request),
            'auditable_id' => $this->getAuditableId($request),
            'old_values' => null, // Would require model observers for old values
            'new_values' => $this->sanitizeInput($request->except($this->sensitiveFields)),
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 500),
            'tags' => $this->generateTags($request, $response),
        ]);
    }

    /**
     * Determine the event type based on the request.
     */
    protected function determineEventType(Request $request, array $events): string
    {
        // Use explicitly passed event if available
        if (!empty($events)) {
            return $events[0];
        }

        // Determine from route name or path
        $routeName = $request->route()?->getName() ?? '';

        // Common patterns
        if (str_contains($routeName, 'login')) {
            return 'login';
        }
        if (str_contains($routeName, 'logout')) {
            return 'logout';
        }
        if (str_contains($routeName, 'password')) {
            return 'password_change';
        }
        if (str_contains($routeName, 'delete') || $request->method() === 'DELETE') {
            return 'data_delete';
        }
        if (str_contains($routeName, 'export')) {
            return 'data_export';
        }

        // Default based on HTTP method
        return match ($request->method()) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'access',
        };
    }

    /**
     * Get the auditable type from the request.
     */
    protected function getAuditableType(Request $request): string
    {
        $routeName = $request->route()?->getName() ?? '';

        // Try to extract resource name from route
        $parts = explode('.', $routeName);
        if (count($parts) >= 2) {
            return ucfirst(str_replace('-', '_', $parts[count($parts) - 2]));
        }

        // Fall back to path segment
        $segments = $request->segments();
        if (!empty($segments)) {
            return ucfirst($segments[0]);
        }

        return 'Request';
    }

    /**
     * Get the auditable ID from the request.
     */
    protected function getAuditableId(Request $request): string
    {
        // Try to get ID from route parameters
        $parameters = $request->route()?->parameters() ?? [];

        foreach ($parameters as $key => $value) {
            if (is_string($value) && $this->isUuid($value)) {
                return $value;
            }
            if (is_object($value) && method_exists($value, 'getKey')) {
                return (string) $value->getKey();
            }
        }

        // Try from request body
        if ($id = $request->input('id')) {
            return (string) $id;
        }

        return '00000000-0000-0000-0000-000000000000';
    }

    /**
     * Sanitize input data for logging.
     */
    protected function sanitizeInput(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            // Mask sensitive fields
            if ($this->isSensitiveField($key)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            // Recursively sanitize arrays
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeInput($value);
                continue;
            }

            // Truncate long values
            if (is_string($value) && strlen($value) > 1000) {
                $sanitized[$key] = substr($value, 0, 1000) . '...[truncated]';
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * Check if a field name is sensitive.
     */
    protected function isSensitiveField(string $key): bool
    {
        $key = strtolower($key);

        foreach ($this->sensitiveFields as $sensitive) {
            if (str_contains($key, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate tags for the audit log.
     */
    protected function generateTags(Request $request, Response $response): ?string
    {
        $tags = [];

        // Add HTTP method
        $tags[] = $request->method();

        // Add response status category
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            $tags[] = 'success';
        } elseif ($status >= 400 && $status < 500) {
            $tags[] = 'client_error';
        } elseif ($status >= 500) {
            $tags[] = 'server_error';
        }

        // Add route name if available
        if ($routeName = $request->route()?->getName()) {
            $tags[] = $routeName;
        }

        return implode(',', $tags);
    }

    /**
     * Check if a string is a valid UUID.
     */
    protected function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
