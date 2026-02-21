<?php

namespace Modules\Payroll\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->routes(function () {
            Route::middleware('web')
                ->group(module_path('Payroll', '/Routes/web.php'));

            Route::prefix('api')
                ->middleware('api')
                ->group(module_path('Payroll', '/Routes/api.php'));
        });
    }
}
