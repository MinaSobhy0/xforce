<?php

namespace Modules\Marketing\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Marketing\Services\WhatsAppService;
use Modules\Marketing\Services\SmsService;
use Modules\Marketing\Services\EmailService;
use Modules\Marketing\Services\NotificationService;

class MarketingServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Marketing';

    protected string $moduleNameLower = 'marketing';

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(EventServiceProvider::class);

        // Register services as singletons
        $this->app->singleton(WhatsAppService::class, function ($app) {
            return new WhatsAppService();
        });

        $this->app->singleton(SmsService::class, function ($app) {
            return new SmsService();
        });

        $this->app->singleton(EmailService::class, function ($app) {
            return new EmailService();
        });

        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService(
                $app->make(WhatsAppService::class),
                $app->make(SmsService::class),
                $app->make(EmailService::class)
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

    public function provides(): array
    {
        return [
            WhatsAppService::class,
            SmsService::class,
            EmailService::class,
            NotificationService::class,
        ];
    }
}
