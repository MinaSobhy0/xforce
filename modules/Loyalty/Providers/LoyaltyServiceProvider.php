<?php

namespace Modules\Loyalty\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Loyalty\Services\LoyaltyService;

class LoyaltyServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Loyalty';
    protected string $moduleNameLower = 'loyalty';

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerTranslations();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(EventServiceProvider::class);

        // Register the LoyaltyService as a singleton
        $this->app->singleton(LoyaltyService::class, function ($app) {
            return new LoyaltyService();
        });
    }

    protected function registerConfig(): void
    {
        $configPath = module_path($this->moduleName, 'Config/config.php');

        if (file_exists($configPath)) {
            $this->publishes([
                $configPath => config_path($this->moduleNameLower . '.php'),
            ], 'config');

            $this->mergeConfigFrom($configPath, $this->moduleNameLower);
        }
    }

    protected function registerTranslations(): void
    {
        $this->loadTranslationsFrom(module_path($this->moduleName, 'Lang'), $this->moduleNameLower);
    }

    public function provides(): array
    {
        return [
            LoyaltyService::class,
        ];
    }
}
