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
        // Register XLinic Framework middleware
        $middleware->alias([
            'tenant' => \XLinic\Framework\Core\Tenancy\TenantMiddleware::class,
            'quota' => \XLinic\Framework\Core\Quota\QuotaMiddleware::class,
        ]);

        // Add tenant middleware to web group (for tenant-scoped routes)
        $middleware->web(append: [
            \XLinic\Framework\Core\Tenancy\TenantMiddleware::class,
        ]);

        // Add quota middleware to api group (for rate limiting)
        $middleware->api(append: [
            \XLinic\Framework\Core\Quota\QuotaMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();