<?php

namespace Modules\Auth\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected string $moduleNamespace = 'Modules\Auth\Http\Controllers';

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
        $webRoutes = module_path('Auth', 'Routes/web.php');

        if (file_exists($webRoutes)) {
            Route::middleware('web')
                ->namespace($this->moduleNamespace)
                ->group($webRoutes);
        }
    }

    protected function mapApiRoutes(): void
    {
        $apiRoutes = module_path('Auth', 'Routes/api.php');

        if (file_exists($apiRoutes)) {
            Route::middleware('api')
                ->namespace($this->moduleNamespace)
                ->prefix('api')
                ->group($apiRoutes);
        }
    }
}
