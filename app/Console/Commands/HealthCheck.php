<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class HealthCheck extends Command
{
    protected $signature = 'health:check
                            {--json : Output results as JSON}
                            {--fail-on-warning : Exit with error code on warnings}';

    protected $description = 'Run system health checks';

    protected array $results = [];

    public function handle(): int
    {
        $this->info("Running health checks...");
        $this->newLine();

        // Database check
        $this->checkDatabase();

        // Cache check
        $this->checkCache();

        // Redis check (if configured)
        $this->checkRedis();

        // Storage check
        $this->checkStorage();

        // Queue check
        $this->checkQueue();

        // Disk space check
        $this->checkDiskSpace();

        // PHP extensions check
        $this->checkPhpExtensions();

        // SSL certificate check (if applicable)
        $this->checkSslCertificate();

        // Output results
        if ($this->option('json')) {
            $this->line(json_encode($this->results, JSON_PRETTY_PRINT));
        } else {
            $this->displayResults();
        }

        // Determine exit code
        $hasErrors = collect($this->results)->contains('status', 'error');
        $hasWarnings = collect($this->results)->contains('status', 'warning');

        if ($hasErrors) {
            return 1;
        }

        if ($hasWarnings && $this->option('fail-on-warning')) {
            return 1;
        }

        return 0;
    }

    protected function checkDatabase(): void
    {
        $startTime = microtime(true);

        try {
            DB::connection()->getPdo();
            $version = DB::selectOne('SELECT version()')->version ?? 'Unknown';
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->results['database'] = [
                'status' => 'ok',
                'message' => 'Database connection successful',
                'details' => [
                    'driver' => config('database.default'),
                    'version' => $version,
                    'response_time_ms' => $duration,
                ],
            ];
        } catch (\Exception $e) {
            $this->results['database'] = [
                'status' => 'error',
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function checkCache(): void
    {
        try {
            $testKey = 'health_check_' . time();
            Cache::put($testKey, 'test', 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);

            if ($value === 'test') {
                $this->results['cache'] = [
                    'status' => 'ok',
                    'message' => 'Cache is working',
                    'details' => [
                        'driver' => config('cache.default'),
                    ],
                ];
            } else {
                throw new \Exception('Cache read/write test failed');
            }
        } catch (\Exception $e) {
            $this->results['cache'] = [
                'status' => 'error',
                'message' => 'Cache check failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function checkRedis(): void
    {
        if (config('cache.default') !== 'redis' && config('queue.default') !== 'redis') {
            $this->results['redis'] = [
                'status' => 'skip',
                'message' => 'Redis not configured',
            ];
            return;
        }

        try {
            Redis::ping();
            $info = Redis::info();

            $this->results['redis'] = [
                'status' => 'ok',
                'message' => 'Redis connection successful',
                'details' => [
                    'version' => $info['redis_version'] ?? 'Unknown',
                    'memory_used' => $info['used_memory_human'] ?? 'Unknown',
                    'connected_clients' => $info['connected_clients'] ?? 'Unknown',
                ],
            ];
        } catch (\Exception $e) {
            $this->results['redis'] = [
                'status' => 'error',
                'message' => 'Redis connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function checkStorage(): void
    {
        try {
            $disk = Storage::disk('local');
            $testFile = 'health_check_' . time() . '.txt';

            $disk->put($testFile, 'test');
            $content = $disk->get($testFile);
            $disk->delete($testFile);

            if ($content === 'test') {
                $this->results['storage'] = [
                    'status' => 'ok',
                    'message' => 'Storage is working',
                    'details' => [
                        'disk' => 'local',
                        'path' => storage_path('app'),
                    ],
                ];
            } else {
                throw new \Exception('Storage read/write test failed');
            }
        } catch (\Exception $e) {
            $this->results['storage'] = [
                'status' => 'error',
                'message' => 'Storage check failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function checkQueue(): void
    {
        try {
            $driver = config('queue.default');

            if ($driver === 'sync') {
                $this->results['queue'] = [
                    'status' => 'warning',
                    'message' => 'Queue is using sync driver (not recommended for production)',
                    'details' => [
                        'driver' => $driver,
                    ],
                ];
                return;
            }

            // Check if queue worker is running (basic check)
            $failedJobs = DB::table('failed_jobs')->count();

            $this->results['queue'] = [
                'status' => $failedJobs > 10 ? 'warning' : 'ok',
                'message' => $failedJobs > 10 ? "Queue has {$failedJobs} failed jobs" : 'Queue configured',
                'details' => [
                    'driver' => $driver,
                    'failed_jobs' => $failedJobs,
                ],
            ];
        } catch (\Exception $e) {
            $this->results['queue'] = [
                'status' => 'warning',
                'message' => 'Could not check queue status',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function checkDiskSpace(): void
    {
        $path = storage_path();
        $freeBytes = disk_free_space($path);
        $totalBytes = disk_total_space($path);

        $freeGb = round($freeBytes / 1073741824, 2);
        $totalGb = round($totalBytes / 1073741824, 2);
        $usedPercent = round((($totalBytes - $freeBytes) / $totalBytes) * 100, 1);

        $status = 'ok';
        $message = "Disk space: {$freeGb} GB free of {$totalGb} GB";

        if ($usedPercent >= 90) {
            $status = 'error';
            $message = "Critical: Disk usage at {$usedPercent}%";
        } elseif ($usedPercent >= 80) {
            $status = 'warning';
            $message = "Warning: Disk usage at {$usedPercent}%";
        }

        $this->results['disk_space'] = [
            'status' => $status,
            'message' => $message,
            'details' => [
                'free_gb' => $freeGb,
                'total_gb' => $totalGb,
                'used_percent' => $usedPercent,
            ],
        ];
    }

    protected function checkPhpExtensions(): void
    {
        $required = ['pdo', 'pdo_pgsql', 'mbstring', 'openssl', 'json', 'curl', 'gd', 'zip'];
        $missing = [];

        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }

        if (empty($missing)) {
            $this->results['php_extensions'] = [
                'status' => 'ok',
                'message' => 'All required PHP extensions loaded',
                'details' => [
                    'php_version' => PHP_VERSION,
                ],
            ];
        } else {
            $this->results['php_extensions'] = [
                'status' => 'error',
                'message' => 'Missing PHP extensions: ' . implode(', ', $missing),
                'details' => [
                    'missing' => $missing,
                ],
            ];
        }
    }

    protected function checkSslCertificate(): void
    {
        $domain = config('app.url');

        if (!str_starts_with($domain, 'https://')) {
            $this->results['ssl'] = [
                'status' => 'skip',
                'message' => 'HTTPS not configured',
            ];
            return;
        }

        try {
            $response = Http::timeout(5)->head($domain);

            $this->results['ssl'] = [
                'status' => 'ok',
                'message' => 'SSL certificate valid',
                'details' => [
                    'domain' => $domain,
                ],
            ];
        } catch (\Exception $e) {
            $this->results['ssl'] = [
                'status' => 'warning',
                'message' => 'Could not verify SSL',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function displayResults(): void
    {
        $rows = [];

        foreach ($this->results as $check => $result) {
            $statusIcon = match ($result['status']) {
                'ok' => '<fg=green>OK</>',
                'warning' => '<fg=yellow>WARN</>',
                'error' => '<fg=red>FAIL</>',
                'skip' => '<fg=gray>SKIP</>',
                default => $result['status'],
            };

            $rows[] = [
                str_replace('_', ' ', ucfirst($check)),
                $statusIcon,
                $result['message'],
            ];
        }

        $this->table(['Check', 'Status', 'Message'], $rows);

        // Summary
        $this->newLine();
        $okCount = collect($this->results)->where('status', 'ok')->count();
        $warnCount = collect($this->results)->where('status', 'warning')->count();
        $errorCount = collect($this->results)->where('status', 'error')->count();

        $this->line("Summary: <fg=green>{$okCount} OK</>, <fg=yellow>{$warnCount} warnings</>, <fg=red>{$errorCount} errors</>");
    }
}
