<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register XLinic Framework middleware as named middleware
        $middleware->alias([
            'tenant' => \XLinic\Framework\Core\Tenancy\TenantMiddleware::class,
            'quota' => \XLinic\Framework\Core\Quota\QuotaMiddleware::class,
            'identify-tenant' => \App\Http\Middleware\IdentifyTenant::class,

            // Security middleware
            'audit' => \App\Http\Middleware\AuditLogger::class,
            'api-limit' => \App\Http\Middleware\ApiRateLimiter::class,
            'ip-whitelist' => \App\Http\Middleware\IpWhitelist::class,
            '2fa-enforce' => \App\Http\Middleware\TwoFactorEnforce::class,

            // Tenant middleware
            'tenant-limit' => \App\Http\Middleware\TenantUsageLimit::class,
            'tenant.module' => \App\Http\Middleware\TenantModuleAccess::class,
            'tenant.feature' => \App\Http\Middleware\TenantFeatureFlag::class,
            'tenant.subscription' => \App\Http\Middleware\TenantSubscription::class,
        ]);

        // Add IdentifyTenant middleware globally for web routes
        // It will skip sys.x-linic.com and other excluded subdomains
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class, // SECURITY: Add security headers to all responses
            \App\Http\Middleware\IdentifyTenant::class,
            \App\Http\Middleware\SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle PostgreSQL foreign key violations with user-friendly messages
        $exceptions->renderable(function (\Illuminate\Database\QueryException $e, \Illuminate\Http\Request $request) {
            $handler = new \App\Exceptions\ForeignKeyViolationHandler;
            $response = $handler->handle($e, $request);

            if ($response !== null) {
                return $response;
            }

            // Let Laravel handle other query exceptions
            return null;
        });
    })->create();
