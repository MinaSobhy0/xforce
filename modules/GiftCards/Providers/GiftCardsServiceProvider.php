<?php

namespace Modules\GiftCards\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\GiftCards\Services\GiftCardService;
use Modules\GiftCards\Services\GiftCardGeneratorService;
use Modules\GiftCards\Services\GiftCardGLService;
use Modules\GiftCards\Services\GiftCardExportService;
use Modules\GiftCards\Services\GiftCardPdfService;
use Modules\GiftCards\Console\Commands\ExpireGiftCardsCommand;

class GiftCardsServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'GiftCards';
    protected string $moduleNameLower = 'giftcards';

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerTranslations();
        $this->registerViews();
        $this->registerCommands();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireGiftCardsCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(EventServiceProvider::class);

        // Register services
        $this->app->singleton(GiftCardGeneratorService::class);
        $this->app->singleton(GiftCardGLService::class);
        $this->app->singleton(GiftCardService::class);
        $this->app->singleton(GiftCardExportService::class);
        $this->app->singleton(GiftCardPdfService::class);
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

    public function provides(): array
    {
        return [
            GiftCardService::class,
            GiftCardGeneratorService::class,
            GiftCardGLService::class,
            GiftCardExportService::class,
            GiftCardPdfService::class,
        ];
    }
}
