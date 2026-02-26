<?php

namespace Modules\Assets\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Assets\Services\AssetService;
use Modules\Assets\Services\AssetGLService;
use Modules\Assets\Console\Commands\ProcessDepreciationCommand;

class AssetsServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Assets';
    protected string $moduleNameLower = 'assets';

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerTranslations();
        $this->registerViews();
        $this->registerCommands();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
    }

    protected function registerViews(): void
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);
        $sourcePath = module_path($this->moduleName, 'resources/views');

        $this->publishes([
            $sourcePath => $viewPath,
        ], ['views', $this->moduleNameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    protected function getPublishableViewPaths(): array
    {
        $paths = [];

        foreach (config('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }

        return $paths;
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessDepreciationCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);

        // Register services
        $this->app->singleton(AssetGLService::class);
        $this->app->singleton(AssetService::class);
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

    public function provides(): array
    {
        return [
            AssetService::class,
            AssetGLService::class,
        ];
    }
}
