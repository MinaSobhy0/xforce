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
        ]);

        // Add IdentifyTenant middleware globally for web routes
        // It will skip sys.x-linic.com and other excluded subdomains
        $middleware->web(append: [
            \App\Http\Middleware\IdentifyTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();