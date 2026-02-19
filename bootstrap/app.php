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
        // These are applied via route groups, NOT globally
        $middleware->alias([
            'tenant' => \XLinic\Framework\Core\Tenancy\TenantMiddleware::class,
            'quota' => \XLinic\Framework\Core\Quota\QuotaMiddleware::class,
        ]);

        // NOTE: Do NOT add tenant middleware globally to web group
        // The admin/platform panels should NOT use tenant middleware
        // Tenant middleware is applied via route groups for tenant-scoped routes only
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();