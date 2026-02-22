<?php

namespace Modules\Attendance\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Attendance\Services\AttendanceRuleService;
use Modules\Attendance\Services\AttendanceService;
use Modules\Attendance\Services\WorkingScheduleService;

class AttendanceServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Attendance';

    protected string $moduleNameLower = 'attendance';

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerTranslations();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Register services as singletons
        $this->app->singleton(AttendanceRuleService::class, function ($app) {
            return new AttendanceRuleService();
        });

        $this->app->singleton(AttendanceService::class, function ($app) {
            return new AttendanceService($app->make(AttendanceRuleService::class));
        });

        $this->app->singleton(WorkingScheduleService::class, function ($app) {
            return new WorkingScheduleService();
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
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'Lang'), $this->moduleNameLower);
        }
    }
}
