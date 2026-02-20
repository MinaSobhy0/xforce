<?php

namespace App\Providers;

use App\Database\PostgresConnection;
use App\Http\Middleware\IdentifyTenant;
use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register custom PostgreSQL connection that handles boolean types properly
        Connection::resolverFor('pgsql', function ($connection, $database, $prefix, $config) {
            return new PostgresConnection($connection, $database, $prefix, $config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Add our custom IdentifyTenant middleware to Livewire's persistent middleware
        // This ensures the middleware runs on Livewire AJAX requests, not just initial page loads
        Livewire::addPersistentMiddleware([
            IdentifyTenant::class,
        ]);

    }
}