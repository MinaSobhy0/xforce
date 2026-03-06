<?php

namespace Modules\Packages\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Modules\Packages\Events\PackageSessionUsed;
use Modules\Packages\Listeners\RecognizePackageRevenue;
use Modules\Packages\Services\PackageService;
use Modules\Packages\Services\RevenueRecognitionService;

class PackagesServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Packages';
    protected string $moduleNameLower = 'packages';

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerTranslations();
        $this->registerEvents();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Register services as singletons
        $this->app->singleton(PackageService::class);
        $this->app->singleton(RevenueRecognitionService::class);
    }

    protected function registerEvents(): void
    {
        Event::listen(
            PackageSessionUsed::class,
            RecognizePackageRevenue::class
        );
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower . '.php'),
        ], 'config');

        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'),
            $this->moduleNameLower
        );
    }

    protected function registerTranslations(): void
    {
        $this->loadTranslationsFrom(module_path($this->moduleName, 'Lang'), $this->moduleNameLower);
    }

    protected function registerViews(): void
    {
        $this->loadViewsFrom(module_path($this->moduleName, 'resources/views'), $this->moduleNameLower);
    }

    public function provides(): array
    {
        return [];
    }
}
