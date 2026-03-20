<?php

namespace Modules\MobileApi\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class MobileApiServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'MobileApi';
    protected string $moduleNameLower = 'mobile_api';

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerTranslations();
        $this->registerViews();
        $this->configureRateLimiting();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig(): void
    {
        $configPath = module_path($this->moduleName, 'Config/config.php');

        if (file_exists($configPath)) {
            $this->publishes([
                $configPath => config_path($this->moduleNameLower . '.php'),
            ], 'config');

            $this->mergeConfigFrom($configPath, $this->moduleNameLower);
        }
    }

    protected function registerTranslations(): void
    {
        $langPath = module_path($this->moduleName, 'Lang');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
        }
    }

    protected function registerViews(): void
    {
        $viewPath = module_path($this->moduleName, 'resources/views');

        if (is_dir($viewPath)) {
            $this->loadViewsFrom($viewPath, $this->moduleNameLower);
        }

        // Publish views
        $this->publishes([
            $viewPath => resource_path('views/vendor/mobile_api'),
        ], 'views');
    }

    protected function configureRateLimiting(): void
    {
        // Mobile API default rate limit
        RateLimiter::for('mobile-api', function (Request $request) {
            $config = config('mobile_api.rate_limits.default');
            return Limit::perMinute($config['max_attempts'])
                ->by($request->user()?->id ?: $request->ip());
        });

        // Mobile auth endpoints rate limit
        RateLimiter::for('mobile-api-auth', function (Request $request) {
            $config = config('mobile_api.rate_limits.auth');
            return Limit::perMinute($config['max_attempts'])
                ->by($request->ip());
        });

        // Mobile OTP rate limit
        RateLimiter::for('mobile-api-otp', function (Request $request) {
            $config = config('mobile_api.rate_limits.otp');
            return Limit::perMinutes($config['decay_minutes'], $config['max_attempts'])
                ->by($request->ip());
        });
    }
}
