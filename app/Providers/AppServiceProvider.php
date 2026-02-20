<?php

namespace App\Providers;

use App\Http\Middleware\IdentifyTenant;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Add our custom IdentifyTenant middleware to Livewire's persistent middleware
        // This ensures the middleware runs on Livewire AJAX requests, not just initial page loads
        Livewire::addPersistentMiddleware([
            IdentifyTenant::class,
        ]);

    }
}