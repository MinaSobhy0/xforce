<?php

namespace Modules\Marketing\Services;

use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantUsage;
use Modules\Marketing\Exceptions\QuotaExceededException;

class MessageQuotaService
{
    /**
     * Check if sending is allowed for the given channel and tenant.
     *
     * @throws QuotaExceededException
     */
    public function checkQuota(string $channel, ?string $tenantId = null, int $count = 1): bool
    {
        $usage = $this->getUsage($tenantId);

        if (!$usage) {
            // No usage record - allow (will be created on first use)
            return true;
        }

        $metric = TenantUsage::getChannelMetric($channel);
        $remaining = $usage->getRemainingUsage($metric);

        if ($remaining < $count) {
            throw new QuotaExceededException(
                channel: $channel,
                remaining: $remaining,
                requested: $count
            );
        }

        return true;
    }

    /**
     * Track messages sent.
     */
    public function trackUsage(string $channel, ?string $tenantId = null, int $count = 1): void
    {
        $usage = $this->getOrCreateUsage($tenantId);

        if ($usage) {
            $usage->trackMessageSent($channel, $count);
        }
    }

    /**
     * Get remaining quota for a channel.
     */
    public function getRemainingQuota(string $channel, ?string $tenantId = null): int
    {
        $usage = $this->getUsage($tenantId);

        if (!$usage) {
            // Return default limit if no usage record
            return match ($channel) {
                'whatsapp' => 500,
                'sms' => 500,
                'email' => 1000,
                default => 0,
            };
        }

        $metric = TenantUsage::getChannelMetric($channel);
        return $usage->getRemainingUsage($metric);
    }

    /**
     * Get usage percentage for a channel.
     */
    public function getUsagePercentage(string $channel, ?string $tenantId = null): float
    {
        $usage = $this->getUsage($tenantId);

        if (!$usage) {
            return 0.0;
        }

        $metric = TenantUsage::getChannelMetric($channel);
        return $usage->getUsagePercentage($metric);
    }

    /**
     * Check if quota is over warning threshold (80%).
     */
    public function isNearLimit(string $channel, ?string $tenantId = null): bool
    {
        return $this->getUsagePercentage($channel, $tenantId) >= 80;
    }

    /**
     * Get usage summary for all messaging channels.
     */
    public function getUsageSummary(?string $tenantId = null): array
    {
        $channels = ['whatsapp', 'sms', 'email'];
        $summary = [];

        foreach ($channels as $channel) {
            $summary[$channel] = [
                'remaining' => $this->getRemainingQuota($channel, $tenantId),
                'percentage' => $this->getUsagePercentage($channel, $tenantId),
                'near_limit' => $this->isNearLimit($channel, $tenantId),
            ];
        }

        return $summary;
    }

    /**
     * Get tenant usage record.
     */
    protected function getUsage(?string $tenantId): ?TenantUsage
    {
        $tenantId = $tenantId ?? tenant('id');

        if (!$tenantId) {
            return null;
        }

        return TenantUsage::where('tenant_id', $tenantId)->first();
    }

    /**
     * Get or create tenant usage record.
     */
    protected function getOrCreateUsage(?string $tenantId): ?TenantUsage
    {
        $tenantId = $tenantId ?? tenant('id');

        if (!$tenantId) {
            return null;
        }

        return TenantUsage::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'users' => 0,
                'branches' => 0,
                'patients' => 0,
                'appointments' => 0,
                'treatments' => 0,
                'storage_mb' => 0,
                'api_requests' => 0,
                'email_sent' => 0,
                'sms_sent' => 0,
                'whatsapp_sent' => 0,
                'reports_generated' => 0,
            ]
        );
    }
}
