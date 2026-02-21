<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Module;
use App\Models\SubscriptionPlan;
use App\Models\PlatformSetting;
use Modules\Core\Models\Tenant;

class CacheWarm extends Command
{
    protected $signature = 'cache:warm
                            {--force : Force refresh even if cache exists}';

    protected $description = 'Pre-warm application caches for better performance';

    public function handle(): int
    {
        $force = $this->option('force');

        $this->info("Warming up application caches...");
        $this->newLine();

        // 1. Optimize Laravel caches
        $this->task('Caching configuration', function () {
            Artisan::call('config:cache');
            return true;
        });

        $this->task('Caching routes', function () {
            Artisan::call('route:cache');
            return true;
        });

        $this->task('Caching views', function () {
            Artisan::call('view:cache');
            return true;
        });

        $this->task('Caching events', function () {
            Artisan::call('event:cache');
            return true;
        });

        // 2. Cache Filament components
        $this->task('Caching Filament components', function () {
            Artisan::call('filament:cache-components');
            return true;
        });

        // 3. Cache blade icons
        $this->task('Caching blade icons', function () {
            Artisan::call('icons:cache');
            return true;
        });

        // 4. Warm up application-specific caches
        $this->task('Caching platform settings', function () use ($force) {
            return $this->cachePlatformSettings($force);
        });

        $this->task('Caching subscription plans', function () use ($force) {
            return $this->cacheSubscriptionPlans($force);
        });

        $this->task('Caching modules list', function () use ($force) {
            return $this->cacheModules($force);
        });

        $this->task('Caching tenant count', function () use ($force) {
            return $this->cacheTenantStats($force);
        });

        $this->newLine();
        $this->info("Cache warming completed!");

        return 0;
    }

    protected function cachePlatformSettings(bool $force): bool
    {
        $cacheKey = 'platform_settings';

        if (!$force && Cache::has($cacheKey)) {
            return true;
        }

        try {
            $settings = PlatformSetting::all()->pluck('value', 'key')->toArray();
            Cache::put($cacheKey, $settings, now()->addDay());
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function cacheSubscriptionPlans(bool $force): bool
    {
        $cacheKey = 'subscription_plans_active';

        if (!$force && Cache::has($cacheKey)) {
            return true;
        }

        try {
            $plans = SubscriptionPlan::active()->ordered()->get();
            Cache::put($cacheKey, $plans, now()->addHours(6));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function cacheModules(bool $force): bool
    {
        $cacheKey = 'modules_active';

        if (!$force && Cache::has($cacheKey)) {
            return true;
        }

        try {
            $modules = Module::active()->ordered()->get();
            Cache::put($cacheKey, $modules, now()->addHours(6));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function cacheTenantStats(bool $force): bool
    {
        $cacheKey = 'tenant_stats_overview';

        if (!$force && Cache::has($cacheKey)) {
            return true;
        }

        try {
            $stats = [
                'total' => Tenant::count(),
                'active' => Tenant::where('status', 'active')->count(),
                'trial' => Tenant::where('status', 'trial')->count(),
                'suspended' => Tenant::where('status', 'suspended')->count(),
            ];
            Cache::put($cacheKey, $stats, now()->addMinutes(30));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function task(string $description, callable $callback): void
    {
        $this->output->write("  {$description}...");

        $startTime = microtime(true);
        $result = $callback();
        $duration = round((microtime(true) - $startTime) * 1000);

        if ($result) {
            $this->output->writeln(" <fg=green>done</> ({$duration}ms)");
        } else {
            $this->output->writeln(" <fg=yellow>skipped</>");
        }
    }
}
