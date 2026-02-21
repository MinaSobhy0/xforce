<?php

namespace Modules\Api\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class ApiServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Api';
    protected string $moduleNameLower = 'api';

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerTranslations();
        $this->configureRateLimiting();
    }

    public function register(): void
    {
        //
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
        $this->loadTranslationsFrom(module_path($this->moduleName, 'Lang'), $this->moduleNameLower);
    }

    protected function configureRateLimiting(): void
    {
        // Default API rate limit
        RateLimiter::for('api', function (Request $request) {
            $config = config('api.rate_limits.default');
            return Limit::perMinute($config['max_attempts'])
                ->by($request->user()?->id ?: $request->ip());
        });

        // Auth endpoints rate limit
        RateLimiter::for('api-auth', function (Request $request) {
            $config = config('api.rate_limits.auth');
            return Limit::perMinute($config['max_attempts'])
                ->by($request->ip());
        });

        // OTP rate limit
        RateLimiter::for('api-otp', function (Request $request) {
            $config = config('api.rate_limits.otp');
            return Limit::perMinutes($config['decay_minutes'], $config['max_attempts'])
                ->by($request->ip());
        });
    }
}
