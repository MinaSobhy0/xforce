<?php

namespace Modules\GiftCards\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Modules\Billing\Events\InvoicePaid;
use Modules\GiftCards\Listeners\ActivateGiftCardOnInvoicePaid;

class GiftCardsServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'GiftCards';
    protected string $moduleNameLower = 'giftcards';

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

    protected function registerListeners(): void
    {
        // Activate gift card when purchase invoice is paid
        if (class_exists(InvoicePaid::class)) {
            Event::listen(InvoicePaid::class, ActivateGiftCardOnInvoicePaid::class);
        }
    }

    public function provides(): array
    {
        return [];
    }
}
