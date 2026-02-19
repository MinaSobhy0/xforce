<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use XLinic\Framework\Core\Module\ModuleManager;
use XLinic\Framework\Core\Module\ModuleRegistry;
use XLinic\Framework\Core\Module\DependencyResolver;
use XLinic\Framework\Core\Model\ModelRegistry;
use XLinic\Framework\Core\View\ViewExtensionManager;
use XLinic\Framework\Core\Navigation\NavigationRegistry;
use XLinic\Framework\Core\Settings\SettingsRegistry;
use XLinic\Framework\Core\Security\PermissionRegistry;
use XLinic\Framework\Core\Security\RecordPolicyEngine;
use XLinic\Framework\Core\Sequence\SequenceService;
use XLinic\Framework\Core\Quota\QuotaService;
use XLinic\Framework\Core\Report\ReportRegistry;
use XLinic\Framework\Core\Report\ReportEngine;
use XLinic\Framework\Core\Action\ActionRegistry;
use XLinic\Framework\Core\Tenancy\TenantManager;

class FrameworkServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register as singletons
        $this->app->singleton(ModuleManager::class);
        $this->app->singleton(ModuleRegistry::class);
        $this->app->singleton(DependencyResolver::class);
        $this->app->singleton(ModelRegistry::class);
        $this->app->singleton(ViewExtensionManager::class);
        $this->app->singleton(NavigationRegistry::class);
        $this->app->singleton(SettingsRegistry::class);
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(RecordPolicyEngine::class);
        $this->app->singleton(SequenceService::class);
        $this->app->singleton(QuotaService::class);
        $this->app->singleton(ReportRegistry::class);
        $this->app->singleton(ReportEngine::class);
        $this->app->singleton(ActionRegistry::class);
        $this->app->singleton(TenantManager::class);

        // Register framework configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../config/xlinic.php',
            'xlinic'
        );

        // Register Blade components
        $this->loadViewsFrom(
            resource_path('views/framework'),
            'framework'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Boot the module system - this discovers and loads all modules
        $moduleManager = $this->app->make(ModuleManager::class);
        $moduleManager->boot();

        // Register middleware
        $this->registerMiddleware();

        // Register view composers and blade directives
        $this->registerViewComposers();

        // Publish framework assets
        $this->publishes([
            __DIR__.'/../../resources/views/framework' => resource_path('views/framework'),
        ], 'framework-views');

        // Register macros and extensions
        $this->registerMacros();
    }

    /**
     * Register middleware.
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];

        // Register middleware aliases
        $router->aliasMiddleware('tenant', \XLinic\Framework\Core\Tenancy\TenantMiddleware::class);
        $router->aliasMiddleware('quota', \XLinic\Framework\Core\Quota\QuotaMiddleware::class);

        // Add tenant middleware to web and api groups
        $router->middlewareGroup('tenant', [
            \XLinic\Framework\Core\Tenancy\TenantMiddleware::class,
        ]);

        // Add quota middleware to api group
        $router->middlewareGroup('api', [
            \XLinic\Framework\Core\Quota\QuotaMiddleware::class,
        ]);
    }

    /**
     * Register view composers.
     */
    protected function registerViewComposers(): void
    {
        // Skip view composers for Filament panels - they handle their own navigation
        // These composers are only for custom blade views outside Filament

        // Navigation composer - inject navigation data into custom views only
        view()->composer(['layouts.*', 'pages.*'], function ($view) {
            try {
                if (auth()->check() && class_exists(NavigationRegistry::class)) {
                    $navigationRegistry = app(NavigationRegistry::class);
                    $view->with('navigation', $navigationRegistry->getTree());
                }
            } catch (\Exception $e) {
                // Silently ignore navigation errors
            }
        });

        // Quota composer - inject quota data for authenticated users
        view()->composer(['layouts.*', 'pages.*'], function ($view) {
            try {
                if (auth()->check() && function_exists('tenancy') && tenancy()->initialized) {
                    $quotaService = app(QuotaService::class);
                    $view->with('quotas', $quotaService->getDashboard());
                }
            } catch (\Exception $e) {
                // Silently ignore quota errors
            }
        });

        // Tenant composer - inject current tenant data
        view()->composer(['layouts.*', 'pages.*'], function ($view) {
            try {
                if (function_exists('tenancy') && tenancy()->initialized) {
                    $view->with('currentTenant', tenant());
                }
            } catch (\Exception $e) {
                // Silently ignore tenant errors
            }
        });
    }

    /**
     * Register macros and extensions.
     */
    protected function registerMacros(): void
    {
        // Add helper methods to Collection
        \Illuminate\Support\Collection::macro('toTreeArray', function () {
            return $this->map(function ($item) {
                return $item->toArray();
            })->toArray();
        });

        // Add tenant-aware methods to Request
        \Illuminate\Http\Request::macro('tenant', function () {
            return tenant();
        });

        // Add money formatting macro
        \Illuminate\Support\Str::macro('money', function ($amount, $currency = 'EGP') {
            return \Brick\Money\Money::ofMinor($amount, $currency)->formatTo('en_US');
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            ModuleManager::class,
            ModuleRegistry::class,
            DependencyResolver::class,
            ModelRegistry::class,
            ViewExtensionManager::class,
            NavigationRegistry::class,
            SettingsRegistry::class,
            PermissionRegistry::class,
            RecordPolicyEngine::class,
            SequenceService::class,
            QuotaService::class,
            ReportRegistry::class,
            ReportEngine::class,
            ActionRegistry::class,
            TenantManager::class,
        ];
    }
}