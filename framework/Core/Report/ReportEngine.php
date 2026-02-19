<?php

namespace XLinic\Framework\Core\Report;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Collection;
use XLinic\Framework\Core\Tenancy\TenantManager;
use XLinic\Framework\Core\Security\SecurityManager;

/**
 * Report Engine
 *
 * Core engine for generating, caching, scheduling, and managing
 * reports. Handles async generation, security, tenant isolation,
 * and export functionality for the reporting system.
 *
 * @package XLinic\Framework\Core\Report
 */
class ReportEngine
{
    /**
     * Report generation statuses
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_GENERATING = 'generating';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The report registry
     */
    protected ReportRegistry $registry;

    /**
     * The tenant manager
     */
    protected TenantManager $tenantManager;

    /**
     * The security manager
     */
    protected SecurityManager $securityManager;

    /**
     * Currently running reports
     */
    protected array $runningReports = [];

    /**
     * Create a new report engine
     */
    public function __construct(
        ReportRegistry $registry,
        TenantManager $tenantManager,
        SecurityManager $securityManager
    ) {
        $this->registry = $registry;
        $this->tenantManager = $tenantManager;
        $this->securityManager = $securityManager;
    }

    /**
     * Generate a report synchronously
     */
    public function generate(string $reportKey, array $parameters = [], array $options = []): Collection
    {
        $this->validateAccess($reportKey);

        $report = $this->createReportInstance($reportKey, $parameters);

        // Check cache first
        if ($options['use_cache'] ?? true) {
            $cached = $this->getCachedResult($reportKey, $parameters);
            if ($cached) {
                return $cached;
            }
        }

        // Generate report
        $startTime = microtime(true);
        $data = $report->generate($parameters);
        $generationTime = microtime(true) - $startTime;

        // Cache result if cacheable
        if ($this->isCacheable($reportKey)) {
            $this->cacheResult($reportKey, $parameters, $data, $generationTime);
        }

        // Log generation
        $this->logGeneration($reportKey, $parameters, $generationTime, count($data));

        return $data;
    }

    /**
     * Generate a report asynchronously
     */
    public function generateAsync(string $reportKey, array $parameters = [], array $options = []): string
    {
        $this->validateAccess($reportKey);

        $jobId = uniqid('report_', true);
        $tenant = $this->tenantManager->getCurrentTenant();
        $user = auth()->user();

        // Store job information
        $jobData = [
            'id' => $jobId,
            'report_key' => $reportKey,
            'parameters' => $parameters,
            'options' => $options,
            'tenant_id' => $tenant?->id,
            'user_id' => $user?->id,
            'status' => self::STATUS_PENDING,
            'created_at' => now(),
            'estimated_completion' => $this->estimateCompletion($reportKey, $parameters),
        ];

        $this->storeJobData($jobId, $jobData);

        // Dispatch async job
        Queue::push(new \XLinic\Framework\Jobs\GenerateReportJob(
            $jobId,
            $reportKey,
            $parameters,
            $options,
            $tenant?->id,
            $user?->id
        ));

        return $jobId;
    }

    /**
     * Get report generation status
     */
    public function getStatus(string $jobId): array
    {
        $jobData = $this->getJobData($jobId);

        if (!$jobData) {
            return [
                'status' => 'not_found',
                'message' => 'Report generation job not found',
            ];
        }

        return [
            'id' => $jobId,
            'status' => $jobData['status'],
            'progress' => $jobData['progress'] ?? 0,
            'message' => $jobData['message'] ?? '',
            'created_at' => $jobData['created_at'],
            'started_at' => $jobData['started_at'] ?? null,
            'completed_at' => $jobData['completed_at'] ?? null,
            'estimated_completion' => $jobData['estimated_completion'] ?? null,
            'error' => $jobData['error'] ?? null,
        ];
    }

    /**
     * Cancel report generation
     */
    public function cancel(string $jobId): bool
    {
        $jobData = $this->getJobData($jobId);

        if (!$jobData) {
            return false;
        }

        if (in_array($jobData['status'], [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED])) {
            return false; // Already finished
        }

        $jobData['status'] = self::STATUS_CANCELLED;
        $jobData['cancelled_at'] = now();

        $this->storeJobData($jobId, $jobData);

        return true;
    }

    /**
     * Get report result
     */
    public function getResult(string $jobId): ?Collection
    {
        $jobData = $this->getJobData($jobId);

        if (!$jobData || $jobData['status'] !== self::STATUS_COMPLETED) {
            return null;
        }

        $resultKey = "report_result:{$jobId}";
        return Cache::get($resultKey);
    }

    /**
     * Export report
     */
    public function export(string $reportKey, array $parameters = [], string $format = 'pdf', array $options = []): mixed
    {
        $this->validateAccess($reportKey);

        $report = $this->createReportInstance($reportKey, $parameters);
        $data = $this->generate($reportKey, $parameters, ['use_cache' => true]);

        $report->setData($data);
        return $report->export($format, $options);
    }

    /**
     * Schedule a report
     */
    public function schedule(string $reportKey, array $parameters, string $frequency, array $options = []): string
    {
        $this->validateAccess($reportKey);

        $scheduleId = uniqid('schedule_', true);
        $tenant = $this->tenantManager->getCurrentTenant();
        $user = auth()->user();

        $scheduleData = [
            'id' => $scheduleId,
            'report_key' => $reportKey,
            'parameters' => $parameters,
            'frequency' => $frequency,
            'options' => $options,
            'tenant_id' => $tenant?->id,
            'user_id' => $user?->id,
            'active' => true,
            'created_at' => now(),
            'last_run' => null,
            'next_run' => $this->calculateNextRun($frequency),
        ];

        $this->storeScheduleData($scheduleId, $scheduleData);

        return $scheduleId;
    }

    /**
     * Get scheduled reports
     */
    public function getScheduled(?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: $this->tenantManager->getCurrentTenant()?->id;
        $pattern = "schedule:*";

        if ($tenantId) {
            $pattern = "schedule:{$tenantId}:*";
        }

        $schedules = [];
        foreach (Cache::get($pattern, []) as $key) {
            $schedule = Cache::get($key);
            if ($schedule && ($schedule['tenant_id'] ?? null) === $tenantId) {
                $schedules[] = $schedule;
            }
        }

        return $schedules;
    }

    /**
     * Process scheduled reports
     */
    public function processScheduled(): int
    {
        $processed = 0;
        $schedules = $this->getScheduled();

        foreach ($schedules as $schedule) {
            if ($this->shouldRunScheduled($schedule)) {
                $this->runScheduledReport($schedule);
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Get report statistics
     */
    public function getStatistics(?string $reportKey = null): array
    {
        $stats = [
            'total_generated' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'avg_generation_time' => 0,
            'by_report' => [],
            'by_user' => [],
            'by_tenant' => [],
        ];

        // This would typically query from a database or analytics store
        // For now, return basic structure

        return $stats;
    }

    /**
     * Clear report cache
     */
    public function clearCache(?string $reportKey = null): int
    {
        $cleared = 0;

        if ($reportKey) {
            $pattern = "report_cache:{$reportKey}:*";
        } else {
            $pattern = "report_cache:*";
        }

        $keys = Cache::get($pattern, []);
        foreach ($keys as $key) {
            Cache::forget($key);
            $cleared++;
        }

        return $cleared;
    }

    /**
     * Validate user access to report
     */
    protected function validateAccess(string $reportKey): void
    {
        if (!$this->registry->has($reportKey)) {
            throw new \InvalidArgumentException("Report {$reportKey} not found");
        }

        $metadata = $this->registry->getMetadata($reportKey);
        $user = auth()->user();

        if (!$user) {
            throw new \UnauthorizedHttpException('Authentication required');
        }

        // Check permissions
        $permissions = $metadata['permissions'] ?? [];
        foreach ($permissions as $permission) {
            if (!$user->can($permission)) {
                throw new \ForbiddenHttpException('Insufficient permissions');
            }
        }

        // Check roles
        $roles = $metadata['roles'] ?? [];
        if (!empty($roles)) {
            $userRoles = $this->getUserRoles($user);
            if (empty(array_intersect($userRoles, $roles))) {
                throw new \ForbiddenHttpException('Insufficient role permissions');
            }
        }

        // Check tenant access
        if ($metadata['tenant_aware'] ?? true) {
            if (!$this->tenantManager->getCurrentTenant()) {
                throw new \ForbiddenHttpException('Tenant context required');
            }
        }
    }

    /**
     * Create report instance
     */
    protected function createReportInstance(string $reportKey, array $parameters = []): BaseReport
    {
        $report = $this->registry->get($reportKey);
        $report->setParameters($parameters);

        return $report;
    }

    /**
     * Get cached result
     */
    protected function getCachedResult(string $reportKey, array $parameters): ?Collection
    {
        if (!$this->isCacheable($reportKey)) {
            return null;
        }

        $cacheKey = $this->getCacheKey($reportKey, $parameters);
        return Cache::get($cacheKey);
    }

    /**
     * Cache result
     */
    protected function cacheResult(string $reportKey, array $parameters, Collection $data, float $generationTime): void
    {
        $metadata = $this->registry->getMetadata($reportKey);
        $ttl = $metadata['cache_ttl'] ?? 3600;

        $cacheKey = $this->getCacheKey($reportKey, $parameters);
        $cacheData = [
            'data' => $data,
            'generated_at' => now(),
            'generation_time' => $generationTime,
            'parameters' => $parameters,
        ];

        Cache::put($cacheKey, $cacheData, $ttl);
    }

    /**
     * Check if report is cacheable
     */
    protected function isCacheable(string $reportKey): bool
    {
        $metadata = $this->registry->getMetadata($reportKey);
        return $metadata['cacheable'] ?? false;
    }

    /**
     * Generate cache key
     */
    protected function getCacheKey(string $reportKey, array $parameters): string
    {
        $tenant = $this->tenantManager->getCurrentTenant();
        $user = auth()->user();

        $keyData = [
            'report' => $reportKey,
            'parameters' => $parameters,
            'tenant' => $tenant?->id,
            'user' => $user?->id,
        ];

        return 'report_cache:' . md5(serialize($keyData));
    }

    /**
     * Store job data
     */
    protected function storeJobData(string $jobId, array $data): void
    {
        $key = "report_job:{$jobId}";
        Cache::put($key, $data, 86400); // 24 hours
    }

    /**
     * Get job data
     */
    protected function getJobData(string $jobId): ?array
    {
        $key = "report_job:{$jobId}";
        return Cache::get($key);
    }

    /**
     * Store schedule data
     */
    protected function storeScheduleData(string $scheduleId, array $data): void
    {
        $key = "report_schedule:{$scheduleId}";
        Cache::put($key, $data, 0); // No expiration
    }

    /**
     * Estimate completion time
     */
    protected function estimateCompletion(string $reportKey, array $parameters): ?\DateTime
    {
        // This would use historical data to estimate completion
        // For now, return a basic estimate
        return now()->addMinutes(5);
    }

    /**
     * Calculate next run time
     */
    protected function calculateNextRun(string $frequency): \DateTime
    {
        return match ($frequency) {
            'hourly' => now()->addHour(),
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            'monthly' => now()->addMonth(),
            default => now()->addDay()
        };
    }

    /**
     * Check if scheduled report should run
     */
    protected function shouldRunScheduled(array $schedule): bool
    {
        if (!$schedule['active']) {
            return false;
        }

        $nextRun = new \DateTime($schedule['next_run']);
        return $nextRun <= now();
    }

    /**
     * Run scheduled report
     */
    protected function runScheduledReport(array $schedule): void
    {
        $jobId = $this->generateAsync(
            $schedule['report_key'],
            $schedule['parameters'],
            array_merge($schedule['options'], ['scheduled' => true])
        );

        // Update schedule
        $schedule['last_run'] = now();
        $schedule['next_run'] = $this->calculateNextRun($schedule['frequency']);
        $this->storeScheduleData($schedule['id'], $schedule);
    }

    /**
     * Log generation
     */
    protected function logGeneration(string $reportKey, array $parameters, float $time, int $recordCount): void
    {
        // This would log to database or analytics system
        // For now, just log to Laravel log
        logger()->info('Report generated', [
            'report_key' => $reportKey,
            'generation_time' => $time,
            'record_count' => $recordCount,
            'tenant_id' => $this->tenantManager->getCurrentTenant()?->id,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Get user roles
     */
    protected function getUserRoles(object $user): array
    {
        if (method_exists($user, 'getRoles')) {
            return $user->getRoles();
        }

        if (method_exists($user, 'roles')) {
            return $user->roles()->pluck('name')->toArray();
        }

        return [$user->role ?? 'user'];
    }
}