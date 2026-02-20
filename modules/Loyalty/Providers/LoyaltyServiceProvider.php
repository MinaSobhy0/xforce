<?php

namespace Modules\Loyalty\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Modules\Loyalty\Services\LoyaltyService;
use Modules\Loyalty\Listeners\AwardPointsOnPayment;
use Modules\Loyalty\Listeners\AwardPointsOnVisit;
use Modules\Loyalty\Listeners\AwardReferralBonus;

class LoyaltyServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Loyalty';
    protected string $moduleNameLower = 'loyalty';

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerTranslations();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        $this->registerListeners();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

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

    protected function registerListeners(): void
    {
        // Listen for payment events to award points
        Event::listen(
            \Modules\Billing\Events\PaymentReceived::class,
            AwardPointsOnPayment::class
        );

        // Listen for appointment completion to award visit points
        Event::listen(
            \Modules\Booking\Events\AppointmentCompleted::class,
            AwardPointsOnVisit::class
        );

        // Listen for referral completion
        Event::listen(
            \Modules\Loyalty\Events\ReferralCompleted::class,
            AwardReferralBonus::class
        );
    }

    public function provides(): array
    {
        return [
            LoyaltyService::class,
        ];
    }
}
