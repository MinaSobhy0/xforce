<?php

namespace Modules\KnowledgeBase\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\KnowledgeBase\Livewire\HelpButton;
use Modules\KnowledgeBase\Livewire\ScreenGuide;
use Modules\KnowledgeBase\Livewire\ContextualHelp;
use Modules\KnowledgeBase\Livewire\ArticleSearch;
use Modules\KnowledgeBase\Services\HelpService;
use Modules\KnowledgeBase\Services\ScreenMappingService;
use Modules\KnowledgeBase\Services\SearchService;

class KnowledgeBaseServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'KnowledgeBase';
    protected string $moduleNameLower = 'knowledgebase';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        $this->registerLivewireComponents();
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Register services as singletons
        $this->app->singleton(HelpService::class, function ($app) {
            return new HelpService();
        });

        $this->app->singleton(ScreenMappingService::class, function ($app) {
            return new ScreenMappingService();
        });

        $this->app->singleton(SearchService::class, function ($app) {
            return new SearchService();
        });
    }

    /**
     * Register Livewire components.
     */
    protected function registerLivewireComponents(): void
    {
        Livewire::component('knowledgebase::help-button', HelpButton::class);
        Livewire::component('knowledgebase::screen-guide', ScreenGuide::class);
        Livewire::component('knowledgebase::contextual-help', ContextualHelp::class);
        Livewire::component('knowledgebase::article-search', ArticleSearch::class);
    }

    /**
     * Register config.
     */
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

    /**
     * Register views.
     */
    protected function registerViews(): void
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);

        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([
            $sourcePath => $viewPath,
        ], ['views', $this->moduleNameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    /**
     * Register translations.
     */
    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'Lang'), $this->moduleNameLower);
            $this->loadJsonTranslationsFrom(module_path($this->moduleName, 'Lang'));
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            HelpService::class,
            ScreenMappingService::class,
            SearchService::class,
        ];
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
}
