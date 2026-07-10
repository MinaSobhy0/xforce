<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [
    'name' => env('APP_NAME', 'XLinic'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'asset_url' => env('ASSET_URL'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'ar'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'ar_EG'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
    ],
    'providers' => ServiceProvider::defaultProviders()->merge([
        App\Providers\AppServiceProvider::class,
        App\Providers\FrameworkServiceProvider::class,
        App\Providers\LlmServiceProvider::class,
        App\Providers\Filament\AdminPanelProvider::class,
        App\Providers\Filament\TenantPanelProvider::class,
        App\Providers\Filament\SuperAdminPanelProvider::class,
        Modules\PatientPortal\Providers\PortalPanelProvider::class,
    ])->toArray(),
    'aliases' => Facade::defaultAliases()->merge([
        // Custom aliases...
    ])->toArray(),
];