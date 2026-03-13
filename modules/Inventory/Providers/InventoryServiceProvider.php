<?php

namespace Modules\Inventory\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Booking\Events\AppointmentCompleted;
use Modules\Inventory\Listeners\DeductStockOnAppointmentCompleted;

class InventoryServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Inventory';

    protected string $moduleNameLower = 'inventory';

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerTranslations();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        $this->registerCommands();
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Inventory\Console\FixStockMovementCosts::class,
                \Modules\Inventory\Console\RecalculateProductCosts::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(EventServiceProvider::class);
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
}
