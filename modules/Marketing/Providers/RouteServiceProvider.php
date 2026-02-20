<?php

namespace Modules\Marketing\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected string $moduleNamespace = 'Modules\Marketing\Http\Controllers';

    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    protected function mapWebRoutes(): void
    {
        $routePath = module_path('Marketing', 'Routes/web.php');
        if (file_exists($routePath)) {
            Route::middleware('web')
                ->namespace($this->moduleNamespace)
                ->group($routePath);
        }
    }

    protected function mapApiRoutes(): void
    {
        $routePath = module_path('Marketing', 'Routes/api.php');
        if (file_exists($routePath)) {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->moduleNamespace)
                ->group($routePath);
        }
    }
}
