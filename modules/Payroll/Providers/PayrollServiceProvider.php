<?php

namespace Modules\Payroll\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Payroll\Services\FormulaEvaluator;
use Modules\Payroll\Services\PayrollCalculationService;

class PayrollServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Payroll';

    protected string $moduleNameLower = 'payroll';

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerTranslations();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(EventServiceProvider::class);

        // Register FormulaEvaluator as singleton
        $this->app->singleton(FormulaEvaluator::class, function ($app) {
            return new FormulaEvaluator();
        });

        // Register PayrollCalculationService as singleton
        $this->app->singleton(PayrollCalculationService::class, function ($app) {
            return new PayrollCalculationService(
                $app->make(FormulaEvaluator::class)
            );
        });
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
