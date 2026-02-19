<?php

namespace XLinic\Framework\Core\Module;

use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleManager::class, function ($app) {
            return new ModuleManager(
                $app->make(ModuleRegistry::class),
                $app->make(DependencyResolver::class),
                $app->make(\Framework\Core\Tenancy\TenantManager::class)
            );
        });

        $this->app->singleton(ModuleRegistry::class, function ($app) {
            return new ModuleRegistry(
                $app->make(\Framework\Core\Tenancy\TenantManager::class)
            );
        });

        $this->app->singleton(DependencyResolver::class, function ($app) {
            return new DependencyResolver();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Boot the module manager to discover and register modules
        $this->app->make(ModuleManager::class)->boot();
    }
}