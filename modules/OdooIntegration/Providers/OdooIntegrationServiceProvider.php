<?php

namespace Modules\OdooIntegration\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\OdooIntegration\Services\Api\OdooApiClientInterface;
use Modules\OdooIntegration\Services\Api\OdooApiFactory;
use Modules\OdooIntegration\Services\Sync\SyncEngine;
use Modules\OdooIntegration\Services\Sync\ImportService;
use Modules\OdooIntegration\Services\Sync\ExportService;
use Modules\OdooIntegration\Services\Sync\ConflictResolver;
use Modules\OdooIntegration\Services\Sync\WatermarkService;
use Modules\OdooIntegration\Services\Transform\FieldTransformer;
use Modules\OdooIntegration\Services\Transform\RelationResolver;
use Modules\OdooIntegration\Services\Transform\TimezoneConverter;
use Modules\OdooIntegration\Jobs\BatchSyncJob;
use Modules\OdooIntegration\Jobs\CleanupSyncLogsJob;

class OdooIntegrationServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'OdooIntegration';
    protected string $moduleNameLower = 'odoo-integration';

    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);

        // Register API factory
        $this->app->singleton(OdooApiFactory::class);

        // Register services
        $this->app->singleton(SyncEngine::class);
        $this->app->singleton(ImportService::class);
        $this->app->singleton(ExportService::class);
        $this->app->singleton(ConflictResolver::class);
        $this->app->singleton(WatermarkService::class);
        $this->app->singleton(FieldTransformer::class);
        $this->app->singleton(RelationResolver::class);
        $this->app->singleton(TimezoneConverter::class);
    }

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        $this->registerSchedule();
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

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }
        return $paths;
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

    protected function registerSchedule(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            // Daily cleanup of old sync logs
            $schedule->job(new CleanupSyncLogsJob())
                ->daily()
                ->at('02:00')
                ->withoutOverlapping();
        });
    }

    public function provides(): array
    {
        return [
            OdooApiFactory::class,
            SyncEngine::class,
            ImportService::class,
            ExportService::class,
            ConflictResolver::class,
            WatermarkService::class,
            FieldTransformer::class,
            RelationResolver::class,
            TimezoneConverter::class,
        ];
    }
}
