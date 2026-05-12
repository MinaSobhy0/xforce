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
        $this->registerCrossModuleActions();
        $this->registerRealtimeSyncListener();
    }

    /**
     * Wire the "Real-time" sync_frequency option.
     *
     * Listens to eloquent.saved: * globally. For each saved model, asks
     * RealtimeSyncManager whether an active realtime+export mapping exists
     * for that class; if yes, dispatches RealtimeSyncJob to push the row.
     * ImportService wraps its own writes in RealtimeSyncManager::suppress()
     * to prevent feedback loops.
     */
    protected function registerRealtimeSyncListener(): void
    {
        \Illuminate\Support\Facades\Event::listen('eloquent.saved: *', function ($eventName, array $payload) {
            $model = $payload[0] ?? null;
            if (! $model instanceof \Illuminate\Database\Eloquent\Model) {
                return;
            }

            $mapping = \Modules\OdooIntegration\Services\RealtimeSyncManager::shouldDispatchFor($model);
            if (! $mapping) {
                return;
            }

            \Modules\OdooIntegration\Jobs\RealtimeSyncJob::dispatch(
                $mapping->id,
                (int) $model->getKey(),
            );
        });
    }

    /**
     * Register the "Push to Odoo" header action on host List pages.
     *
     * Each host List page opts in by using `App\Filament\Traits\HasExtraHeaderActions`
     * and spreading `resolveExtraHeaderActions()` into its `getHeaderActions()`.
     * The button sits next to the page's primary "Create" action and pushes every
     * record without an odoo_id to Odoo. Adding a new resource is one entry below.
     */
    protected function registerCrossModuleActions(): void
    {
        $listPages = [
            // [List page class, Eloquent model class]
            [
                \Modules\Attendance\Filament\Resources\AttendanceResource\Pages\ListAttendances::class,
                \Modules\Attendance\Models\Attendance::class,
            ],
            [
                \Modules\Staff\Filament\Resources\StaffProfileResource\Pages\ListStaffProfiles::class,
                \Modules\Staff\Models\StaffProfile::class,
            ],
        ];

        foreach ($listPages as [$pageClass, $modelClass]) {
            if (! class_exists($pageClass) || ! method_exists($pageClass, 'registerHeaderAction')) {
                continue;
            }

            $pageClass::registerHeaderAction(
                fn () => \Modules\OdooIntegration\Filament\Actions\PushUnlinkedToOdooAction::make($modelClass),
            );
        }
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
