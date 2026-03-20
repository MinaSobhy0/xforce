<?php

namespace Modules\MobileApi\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected string $moduleNamespace = 'Modules\MobileApi\Http\Controllers';

    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapApiRoutes();
    }

    protected function mapApiRoutes(): void
    {
        Route::prefix('api/v2')
            ->middleware('api')
            ->namespace($this->moduleNamespace)
            ->group(module_path('MobileApi', '/Routes/api.php'));
    }
}
