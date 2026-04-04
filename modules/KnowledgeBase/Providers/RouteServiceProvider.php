<?php

namespace Modules\KnowledgeBase\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected string $moduleNamespace = 'Modules\KnowledgeBase\Http\Controllers';

    /**
     * Called before routes are registered.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     */
    protected function mapWebRoutes(): void
    {
        $routePath = module_path('KnowledgeBase', '/Routes/web.php');

        if (file_exists($routePath)) {
            Route::middleware('web')
                ->namespace($this->moduleNamespace)
                ->group($routePath);
        }
    }

    /**
     * Define the "api" routes for the application.
     */
    protected function mapApiRoutes(): void
    {
        $routePath = module_path('KnowledgeBase', '/Routes/api.php');

        if (file_exists($routePath)) {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->moduleNamespace)
                ->group($routePath);
        }
    }
}
