<?php

namespace Modules\MobileApi\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Modules\Attendance\Models\AttendanceViolation;
use Modules\Booking\Models\PractitionerTimeOff;
use Modules\MobileApi\Observers\AttendanceViolationObserver;
use Modules\MobileApi\Observers\TimeOffObserver;
use Modules\MobileApi\Services\PushNotificationService;
use Modules\Payroll\Events\PayrollPaid;
use Modules\MobileApi\Listeners\SendPayslipReadyPush;

class MobileApiServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'MobileApi';
    protected string $moduleNameLower = 'mobile_api';

    /**
     * The event listener mappings.
     */
    protected array $listen = [
        PayrollPaid::class => [
            SendPayslipReadyPush::class,
        ],
    ];

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerTranslations();
        $this->registerViews();
        $this->configureRateLimiting();
        $this->registerObservers();
        $this->registerEventListeners();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Register PushNotificationService as singleton
        $this->app->singleton(PushNotificationService::class, function ($app) {
            return new PushNotificationService();
        });
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

    /**
     * Register model observers for push notifications.
     */
    protected function registerObservers(): void
    {
        // Only register if push notifications are enabled
        if (!config('mobile_api.push_notifications.enabled', true)) {
            return;
        }

        // Register AttendanceViolation observer if the model exists
        if (class_exists(AttendanceViolation::class)) {
            AttendanceViolation::observe(AttendanceViolationObserver::class);
        }

        // Register TimeOff observer if the model exists
        if (class_exists(PractitionerTimeOff::class)) {
            PractitionerTimeOff::observe(TimeOffObserver::class);
        }
    }

    /**
     * Register event listeners for push notifications.
     */
    protected function registerEventListeners(): void
    {
        // Only register if push notifications are enabled
        if (!config('mobile_api.push_notifications.enabled', true)) {
            return;
        }

        $events = $this->app->make('events');

        foreach ($this->listen as $event => $listeners) {
            if (!class_exists($event)) {
                continue;
            }

            foreach ($listeners as $listener) {
                $events->listen($event, $listener);
            }
        }
    }
}
