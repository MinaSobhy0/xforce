<?php

namespace Modules\TreatmentPlans\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Booking\Models\Appointment;
use Modules\TreatmentPlans\Observers\AppointmentObserver;
use Modules\TreatmentPlans\Services\TreatmentPlanService;

class TreatmentPlansServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'TreatmentPlans';
    protected string $moduleNameLower = 'treatment_plans';

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerTranslations();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        $this->registerObservers();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->app->singleton(TreatmentPlanService::class, function ($app) {
            return new TreatmentPlanService();
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

    protected function registerObservers(): void
    {
        // Register the appointment observer to track treatment plan progress
        if (class_exists(Appointment::class)) {
            Appointment::observe(AppointmentObserver::class);
        }
    }

    public function provides(): array
    {
        return [
            TreatmentPlanService::class,
        ];
    }
}
