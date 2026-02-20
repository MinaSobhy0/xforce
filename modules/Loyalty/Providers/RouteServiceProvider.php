<?php

namespace Modules\Loyalty\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected string $moduleNamespace = 'Modules\\Loyalty\\Http\\Controllers';

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
        $path = module_path('Loyalty', 'Routes/web.php');

        if (file_exists($path)) {
            Route::middleware('web')
                ->namespace($this->moduleNamespace)
                ->group($path);
        }
    }

    protected function mapApiRoutes(): void
    {
        $path = module_path('Loyalty', 'Routes/api.php');

        if (file_exists($path)) {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->moduleNamespace)
                ->group($path);
        }
    }
}
