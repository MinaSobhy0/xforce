<?php

namespace XLinic\Framework\Core\Quota;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use XLinic\Framework\Core\Tenancy\TenantManager;

/**
 * Quota Service
 *
 * Manages resource quotas and usage tracking across the platform.
 * Handles tenant-specific quotas, usage limits, and provides
 * real-time quota enforcement and monitoring capabilities.
 *
 * @package XLinic\Framework\Core\Quota
 */
class QuotaService
{
    /**
     * Quota types
     */
    public const TYPE_API_CALLS = 'api_calls';
    public const TYPE_STORAGE = 'storage';
    public const TYPE_USERS = 'users';
    public const TYPE_RECORDS = 'records';
    public const TYPE_REPORTS = 'reports';
    public const TYPE_EMAILS = 'emails';
    public const TYPE_FILE_UPLOADS = 'file_uploads';

    /**
     * Time periods
     */
    public const PERIOD_MINUTE = 'minute';
    public const PERIOD_HOUR = 'hour';
    public const PERIOD_DAY = 'day';
    public const PERIOD_WEEK = 'week';
    public const PERIOD_MONTH = 'month';
    public const PERIOD_YEAR = 'year';

    /**
     * Action types
     */
    public const ACTION_ALLOW = 'allow';
    public const ACTION_DENY = 'deny';
    public const ACTION_THROTTLE = 'throttle';

    /**
     * The tenant manager instance
     */
    protected TenantManager $tenantManager;

    /**
     * Quota definitions cache
     */
    protected array $quotaCache = [];

    /**
     * Usage cache
     */
    protected array $usageCache = [];

    /**
     * Create a new quota service
     */
    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
    }

    /**
     * Check if quota allows operation
     */
    public function allows(string $quotaType, int $amount = 1, ?string $tenantId = null): bool
    {
        $tenantId = $tenantId ?: $this->getCurrentTenantId();
        $quota = $this->getQuota($quotaType, $tenantId);

        if (!$quota) {
            return true; // No quota defined, allow operation
        }

        $usage = $this->getCurrentUsage($quotaType, $tenantId, $quota['period']);
        $newUsage = $usage + $amount;

        return $newUsage <= $quota['limit'];
    }

    /**
     * Check remaining quota
     */
    public function remaining(string $quotaType, ?string $tenantId = null): int
    {
        $tenantId = $tenantId ?: $this->getCurrentTenantId();
        $quota = $this->getQuota($quotaType, $tenantId);

        if (!$quota) {
            return PHP_INT_MAX; // No quota defined, unlimited
        }

        $usage = $this->getCurrentUsage($quotaType, $tenantId, $quota['period']);
        return max(0, $quota['limit'] - $usage);
    }

    /**
     * Consume quota
     */
    public function consume(string $quotaType, int $amount = 1, ?string $tenantId = null): bool
    {
        $tenantId = $tenantId ?: $this->getCurrentTenantId();

        if (!$this->allows($quotaType, $amount, $tenantId)) {
            return false;
        }

        $this->incrementUsage($quotaType, $amount, $tenantId);
        return true;
    }

    /**
     * Force consume quota (bypass limits)
     */
    public function forceConsume(string $quotaType, int $amount = 1, ?string $tenantId = null): void
    {
        $tenantId = $tenantId ?: $this->getCurrentTenantId();
        $this->incrementUsage($quotaType, $amount, $tenantId);
    }

    /**
     * Get current usage
     */
    public function getUsage(string $quotaType, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: $this->getCurrentTenantId();
        $quota = $this->getQuota($quotaType, $tenantId);

        if (!$quota) {
            return [
                'current' => 0,
                'limit' => null,
                'remaining' => null,
                'period' => null,
                'reset_at' => null,
            ];
        }

        $current = $this->getCurrentUsage($quotaType, $tenantId, $quota['period']);
        $resetAt = $this->getResetTime($quota['period']);

        return [
            'current' => $current,
            'limit' => $quota['limit'],
            'remaining' => max(0, $quota['limit'] - $current),
            'period' => $quota['period'],
            'reset_at' => $resetAt,
            'percentage' => $quota['limit'] > 0 ? ($current / $quota['limit']) * 100 : 0,
        ];
    }

    /**
     * Set quota for tenant
     */
    public function setQuota(string $quotaType, int $limit, string $period, ?string $tenantId = null, array $options = []): void
    {
        $tenantId = $tenantId ?: $this->getCurrentTenantId();

        $quota = [
            'type' => $quotaType,
            'limit' => $limit,
            'period' => $period,
            'tenant_id' => $tenantId,
            'action' => $options['action'] ?? self::ACTION_DENY,
            'grace_period' => $options['grace_period'] ?? 0,
            'soft_limit' => $options['soft_limit'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $key = $this->getQuotaKey($quotaType, $tenantId);
        Cache::put($key, $quota, 86400); // Cache for 24 hours

        // Clear quota cache
        unset($this->quotaCache[$key]);

        $this->logQuotaChange($tenantId, $quotaType, $limit, $period, 'set');
    }

    /**
     * Remove quota
     */
    public function removeQuota(string $quotaType, ?string $tenantId = null): void
    {
        $tenantId = $tenantId ?: $this->getCurrentTenantId();

        $key = $this->getQuotaKey($quotaType, $tenantId);
        Cache::forget($key);

        unset($this->quotaCache[$key]);

        $this->logQuotaChange($tenantId, $quotaType, 0, null, 'remove');
    }

    /**
     * Get all quotas for tenant
     */
    public function getAllQuotas(?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: $this->getCurrentTenantId();
        $quotas = [];

        $pattern = "quota:{$tenantId}:*";
        $keys = Cache::get($pattern, []);

        foreach ($keys as $key) {
            $quota = Cache::get($key);
            if ($quota) {
                $quotas[$quota['type']] = $quota;
            }
        }

        return $quotas;
    }

    /**
     * Get quota statistics
     */
    public function getStatistics(?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: $this->getCurrentTenantId();
        $quotas = $this->getAllQuotas($tenantId);

        $stats = [
            'total_quotas' => count($quotas),
            'quotas' => [],
            'warnings' => [],
            'exceeded' => [],
        ];

        foreach ($quotas as $type => $quota) {
            $usage = $this->getUsage($type, $tenantId);

            $stats['quotas'][$type] = $usage;

            // Check for warnings (80% usage)
            if ($usage['percentage'] >= 80 && $usage['percentage'] < 100) {
                $stats['warnings'][] = $type;
            }

            // Check for exceeded quotas
            if ($usage['percentage'] >= 100) {
                $stats['exceeded'][] = $type;
            }
        }

        return $stats;
    }

    /**
     * Reset usage for a quota
     */
    public function resetUsage(string $quotaType, ?string $tenantId = null): void
    {
        $tenantId = $tenantId ?: $this->getCurrentTenantId();
        $quota = $this->getQuota($quotaType, $tenantId);

        if ($quota) {
            $usageKey = $this->getUsageKey($quotaType, $tenantId, $quota['period']);
            Cache::forget($usageKey);

            // Also reset in Redis if using it
            if (class_exists('Redis') && Redis::connection()) {
                Redis::del($usageKey);
            }

            unset($this->usageCache[$usageKey]);

            $this->logQuotaChange($tenantId, $quotaType, null, null, 'reset');
        }
    }

    /**
     * Bulk set quotas
     */
    public function bulkSetQuotas(array $quotas, ?string $tenantId = null): void
    {
        $tenantId = $tenantId ?: $this->getCurrentTenantId();

        foreach ($quotas as $type => $config) {
            $this->setQuota(
                $type,
                $config['limit'],
                $config['period'],
                $tenantId,
                $config['options'] ?? []
            );
        }
    }

    /**
     * Check quota health
     */
    public function checkHealth(?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: $this->getCurrentTenantId();
        $stats = $this->getStatistics($tenantId);

        return [
            'healthy' => empty($stats['exceeded']),
            'warnings' => count($stats['warnings']),
            'exceeded' => count($stats['exceeded']),
            'total_quotas' => $stats['total_quotas'],
            'issues' => array_merge($stats['warnings'], $stats['exceeded']),
        ];
    }

    /**
     * Get quota recommendations
     */
    public function getRecommendations(?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: $this->getCurrentTenantId();
        $stats = $this->getStatistics($tenantId);
        $recommendations = [];

        foreach ($stats['quotas'] as $type => $usage) {
            if ($usage['percentage'] >= 90) {
                $recommendations[] = [
                    'type' => $type,
                    'action' => 'increase_limit',
                    'current_limit' => $usage['limit'],
                    'suggested_limit' => ceil($usage['limit'] * 1.5),
                    'reason' => 'Usage is at ' . round($usage['percentage']) . '%',
                ];
            } elseif ($usage['percentage'] <= 10 && $usage['limit'] > 100) {
                $recommendations[] = [
                    'type' => $type,
                    'action' => 'decrease_limit',
                    'current_limit' => $usage['limit'],
                    'suggested_limit' => max(100, ceil($usage['limit'] * 0.8)),
                    'reason' => 'Usage is only ' . round($usage['percentage']) . '%',
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Get quota for type and tenant
     */
    protected function getQuota(string $quotaType, string $tenantId): ?array
    {
        $key = $this->getQuotaKey($quotaType, $tenantId);

        if (isset($this->quotaCache[$key])) {
            return $this->quotaCache[$key];
        }

        $quota = Cache::get($key);
        $this->quotaCache[$key] = $quota;

        return $quota;
    }

    /**
     * Get current usage for quota
     */
    protected function getCurrentUsage(string $quotaType, string $tenantId, string $period): int
    {
        $usageKey = $this->getUsageKey($quotaType, $tenantId, $period);

        if (isset($this->usageCache[$usageKey])) {
            return $this->usageCache[$usageKey];
        }

        $usage = (int) Cache::get($usageKey, 0);
        $this->usageCache[$usageKey] = $usage;

        return $usage;
    }

    /**
     * Increment usage
     */
    protected function incrementUsage(string $quotaType, int $amount, string $tenantId): void
    {
        $quota = $this->getQuota($quotaType, $tenantId);

        if (!$quota) {
            return; // No quota defined
        }

        $usageKey = $this->getUsageKey($quotaType, $tenantId, $quota['period']);
        $ttl = $this->getPeriodTtl($quota['period']);

        // Use atomic increment
        if (class_exists('Redis') && Redis::connection()) {
            Redis::incrby($usageKey, $amount);
            Redis::expire($usageKey, $ttl);
        } else {
            $current = (int) Cache::get($usageKey, 0);
            Cache::put($usageKey, $current + $amount, $ttl);
        }

        // Update local cache
        $this->usageCache[$usageKey] = ($this->usageCache[$usageKey] ?? 0) + $amount;

        $this->logUsage($tenantId, $quotaType, $amount);
    }

    /**
     * Get quota cache key
     */
    protected function getQuotaKey(string $quotaType, string $tenantId): string
    {
        return "quota:{$tenantId}:{$quotaType}";
    }

    /**
     * Get usage cache key
     */
    protected function getUsageKey(string $quotaType, string $tenantId, string $period): string
    {
        $periodKey = $this->getPeriodKey($period);
        return "usage:{$tenantId}:{$quotaType}:{$periodKey}";
    }

    /**
     * Get period key based on current time
     */
    protected function getPeriodKey(string $period): string
    {
        $now = now();

        return match ($period) {
            self::PERIOD_MINUTE => $now->format('Y-m-d-H-i'),
            self::PERIOD_HOUR => $now->format('Y-m-d-H'),
            self::PERIOD_DAY => $now->format('Y-m-d'),
            self::PERIOD_WEEK => $now->format('Y-W'),
            self::PERIOD_MONTH => $now->format('Y-m'),
            self::PERIOD_YEAR => $now->format('Y'),
            default => $now->format('Y-m-d')
        };
    }

    /**
     * Get TTL for period
     */
    protected function getPeriodTtl(string $period): int
    {
        return match ($period) {
            self::PERIOD_MINUTE => 120, // 2 minutes
            self::PERIOD_HOUR => 3660, // 1 hour + 1 minute
            self::PERIOD_DAY => 86460, // 1 day + 1 minute
            self::PERIOD_WEEK => 604860, // 1 week + 1 minute
            self::PERIOD_MONTH => 2678460, // ~31 days + 1 minute
            self::PERIOD_YEAR => 31536060, // 1 year + 1 minute
            default => 86460
        };
    }

    /**
     * Get reset time for period
     */
    protected function getResetTime(string $period): \DateTime
    {
        $now = now();

        return match ($period) {
            self::PERIOD_MINUTE => $now->copy()->addMinute()->startOfMinute(),
            self::PERIOD_HOUR => $now->copy()->addHour()->startOfHour(),
            self::PERIOD_DAY => $now->copy()->addDay()->startOfDay(),
            self::PERIOD_WEEK => $now->copy()->addWeek()->startOfWeek(),
            self::PERIOD_MONTH => $now->copy()->addMonth()->startOfMonth(),
            self::PERIOD_YEAR => $now->copy()->addYear()->startOfYear(),
            default => $now->copy()->addDay()->startOfDay()
        };
    }

    /**
     * Get current tenant ID
     */
    protected function getCurrentTenantId(): ?string
    {
        $tenant = $this->tenantManager->getCurrentTenant();
        return $tenant?->id;
    }

    /**
     * Log quota change
     */
    protected function logQuotaChange(string $tenantId, string $quotaType, ?int $limit, ?string $period, string $action): void
    {
        logger()->info('Quota changed', [
            'tenant_id' => $tenantId,
            'quota_type' => $quotaType,
            'limit' => $limit,
            'period' => $period,
            'action' => $action,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Log usage
     */
    protected function logUsage(string $tenantId, string $quotaType, int $amount): void
    {
        logger()->debug('Quota usage', [
            'tenant_id' => $tenantId,
            'quota_type' => $quotaType,
            'amount' => $amount,
            'user_id' => auth()->id(),
        ]);
    }
}