<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class TenantStorageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Listen for tenant identification and configure tenant storage
        $this->app->resolving('currentTenant', function ($tenant) {
            if ($tenant && $tenant->slug) {
                $this->configureTenantStorage($tenant->slug);
            }
        });

        // Also check if tenant is already set (for queued jobs, etc.)
        $this->app->booted(function () {
            if ($this->app->bound('currentTenant')) {
                $tenant = $this->app->make('currentTenant');
                if ($tenant && $tenant->slug) {
                    $this->configureTenantStorage($tenant->slug);
                }
            }
        });
    }

    /**
     * Configure the tenant storage disk with the tenant's slug.
     */
    protected function configureTenantStorage(string $tenantSlug): void
    {
        $tenantPath = storage_path('app/tenants/' . $tenantSlug);

        // Ensure the tenant directory exists
        if (!is_dir($tenantPath)) {
            mkdir($tenantPath, 0755, true);
        }

        // Update the tenant disk configuration
        Config::set('filesystems.disks.tenant.root', $tenantPath);

        // Set the URL to use the current request URL (includes subdomain)
        // This ensures FileUpload previews work correctly in Filament
        if ($this->app->runningInConsole()) {
            // For console commands, use the base APP_URL
            $baseUrl = config('app.url');
        } else {
            // For web requests, use the current request URL
            $request = $this->app->make('request');
            $baseUrl = $request->getSchemeAndHttpHost();
        }

        Config::set('filesystems.disks.tenant.url', $baseUrl . '/tenant-storage');

        // Purge the disk instance so it picks up the new config
        Storage::forgetDisk('tenant');
    }
}
