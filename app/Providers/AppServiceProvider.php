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

        // Register Livewire components from modules
        $this->registerModuleLivewireComponents();
    }

    /**
     * Register Livewire components from all modules.
     */
    protected function registerModuleLivewireComponents(): void
    {
        $modulesPath = base_path('modules');

        if (!is_dir($modulesPath)) {
            return;
        }

        // Register widgets
        foreach (glob($modulesPath . '/*/Filament/Widgets/*.php') as $file) {
            $className = $this->getClassFromFile($file);
            if ($className && class_exists($className)) {
                $alias = $this->getComponentAlias($className);
                Livewire::component($alias, $className);
            }
        }

        // Register pages
        foreach (glob($modulesPath . '/*/Filament/Pages/*.php') as $file) {
            $className = $this->getClassFromFile($file);
            if ($className && class_exists($className)) {
                $alias = $this->getComponentAlias($className);
                Livewire::component($alias, $className);
            }
        }

        // Register resource pages
        foreach (glob($modulesPath . '/*/Filament/Resources/*/Pages/*.php') as $file) {
            $className = $this->getClassFromFile($file);
            if ($className && class_exists($className)) {
                $alias = $this->getComponentAlias($className);
                Livewire::component($alias, $className);
            }
        }

        // Register relation managers
        foreach (glob($modulesPath . '/*/Filament/Resources/*/RelationManagers/*.php') as $file) {
            $className = $this->getClassFromFile($file);
            if ($className && class_exists($className)) {
                $alias = $this->getComponentAlias($className);
                Livewire::component($alias, $className);
            }
        }
    }

    /**
     * Get fully qualified class name from file path.
     */
    protected function getClassFromFile(string $file): ?string
    {
        // Extract module name and class name from path
        // e.g., /var/www/html/x_linic/modules/Core/Filament/Widgets/TenantOverviewWidget.php
        if (preg_match('#modules/([^/]+)/(.+)\.php$#', $file, $matches)) {
            $module = $matches[1];
            $relativePath = $matches[2];
            $namespace = 'Modules\\' . $module . '\\' . str_replace('/', '\\', $relativePath);
            return $namespace;
        }
        return null;
    }

    /**
     * Get Livewire component alias from class name.
     */
    protected function getComponentAlias(string $className): string
    {
        // Convert Modules\Core\Filament\Widgets\TenantOverviewWidget
        // to modules.core.filament.widgets.tenant-overview-widget
        $alias = str_replace('\\', '.', $className);
        // Add hyphens before uppercase letters (while case is preserved)
        $alias = preg_replace('/([a-z])([A-Z])/', '$1-$2', $alias);
        // Now lowercase everything
        $alias = strtolower($alias);
        return $alias;
    }
}