<?php

namespace Modules\Booking\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Booking\Livewire\SlotGrid;
use Modules\Booking\Livewire\BookingCart;
use Modules\Booking\Livewire\PatientPackages;
use Modules\Booking\Services\SlotGenerationService;
use Modules\Booking\Services\ReceptionService;

class BookingServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Booking';
    protected string $moduleNameLower = 'booking';

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(EventServiceProvider::class);

        // Register SlotGenerationService as singleton
        $this->app->singleton(SlotGenerationService::class, function ($app) {
            return new SlotGenerationService();
        });

        // Register ReceptionService as singleton
        $this->app->singleton(ReceptionService::class, function ($app) {
            return new ReceptionService();
        });
    }

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->registerLivewireComponents();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
    }

    protected function registerLivewireComponents(): void
    {
        Livewire::component('booking::slot-grid', SlotGrid::class);
        Livewire::component('booking::booking-cart', BookingCart::class);
        Livewire::component('booking::patient-packages', PatientPackages::class);
    }

    protected function registerViews(): void
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);
        $sourcePath = module_path($this->moduleName, 'Resources/views');

        if (is_dir($sourcePath)) {
            $this->publishes([
                $sourcePath => $viewPath
            ], ['views', $this->moduleNameLower . '-module-views']);

            // Register views with both methods for compatibility
            $this->loadViewsFrom($sourcePath, $this->moduleNameLower);

            // Also register directly with view finder
            $this->app->booted(function () use ($sourcePath) {
                $this->app['view.finder']->addNamespace($this->moduleNameLower, $sourcePath);
            });
        }
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
}
