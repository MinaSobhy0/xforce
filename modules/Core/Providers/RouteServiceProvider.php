<?php

namespace Modules\Core\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected string $moduleNamespace = 'Modules\Core\Controllers';

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
        $webRoutes = module_path('Core', 'Routes/web.php');
        if (file_exists($webRoutes)) {
            Route::middleware('web')
                ->namespace($this->moduleNamespace)
                ->group($webRoutes);
        }
    }

    protected function mapApiRoutes(): void
    {
        $apiRoutes = module_path('Core', 'Routes/api.php');
        if (file_exists($apiRoutes)) {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->moduleNamespace)
                ->group($apiRoutes);
        }
    }
}