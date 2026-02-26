<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TenantUsage model - lives in public schema, not subject to tenant scoping.
 * Uses base Laravel Model and 'central' connection to avoid tenant schema switching.
 */
class TenantUsage extends Model
{
    /**
     * The connection to use (always public schema).
     */
    protected $connection = 'central';

    protected $table = 'public.tenant_usage';

    protected $fillable = [
        'tenant_id',
        'users',
        'branches',
        'patients',
        'appointments',
        'services',
        'equipment',
        'products',
        'storage_mb',
        'storage_photos_mb',
        'storage_documents_mb',
        'storage_consent_mb',
        'appointments_this_month',
        'whatsapp_this_month',
        'sms_this_month',
        'emails_this_month',
        'api_calls_today',
        'api_requests',
        'email_sent',
        'sms_sent',
        'whatsapp_sent',
        'reports_generated',
        'last_login_at',
        'last_activity_at',
        'monthly_stats',
        'yearly_stats',
    ];

    protected $casts = [
        'users' => 'integer',
        'branches' => 'integer',
        'patients' => 'integer',
        'appointments' => 'integer',
        'services' => 'integer',
        'equipment' => 'integer',
        'products' => 'integer',
        'storage_mb' => 'integer',
        'storage_photos_mb' => 'integer',
        'storage_documents_mb' => 'integer',
        'storage_consent_mb' => 'integer',
        'appointments_this_month' => 'integer',
        'whatsapp_this_month' => 'integer',
        'sms_this_month' => 'integer',
        'emails_this_month' => 'integer',
        'api_calls_today' => 'integer',
        'api_requests' => 'integer',
        'email_sent' => 'integer',
        'sms_sent' => 'integer',
        'whatsapp_sent' => 'integer',
        'reports_generated' => 'integer',
        'monthly_stats' => 'array',
        'yearly_stats' => 'array',
        'last_login_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function incrementUsage(string $metric, int $amount = 1): void
    {
        $this->increment($metric, $amount);
        $this->updateMonthlyStats($metric, $amount);
        $this->touch('last_activity_at');
    }

    public function getUsagePercentage(string $metric): float
    {
        $current = $this->getAttribute($metric) ?? 0;

        // Get limit from tenant's subscription or default limits
        $limit = $this->tenant?->subscription?->getUsageLimit($metric)
               ?? $this->getDefaultLimit($metric);

        if ($limit <= 0) {
            return 0.0;
        }

        return min(100, ($current / $limit) * 100);
    }

    public function isOverLimit(string $metric): bool
    {
        return $this->getUsagePercentage($metric) >= 100;
    }

    public function getRemainingUsage(string $metric): int
    {
        $current = $this->getAttribute($metric) ?? 0;
        $limit = $this->tenant?->subscription?->getUsageLimit($metric)
               ?? $this->getDefaultLimit($metric);

        return max(0, $limit - $current);
    }

    public function resetMonthlyUsage(): void
    {
        // Archive current month's stats
        $monthlyStats = $this->monthly_stats ?? [];
        $currentMonth = now()->format('Y-m');

        $monthlyStats[$currentMonth] = [
            'users' => $this->users,
            'patients' => $this->patients,
            'appointments' => $this->appointments,
            'services' => $this->treatments,
            'api_requests' => $this->api_requests,
            'email_sent' => $this->email_sent,
            'sms_sent' => $this->sms_sent,
            'whatsapp_sent' => $this->whatsapp_sent,
            'reports_generated' => $this->reports_generated,
        ];

        // Keep only last 12 months
        $monthlyStats = collect($monthlyStats)
            ->sortKeys()
            ->slice(-12)
            ->toArray();

        $this->update([
            'monthly_stats' => $monthlyStats,
            'api_requests' => 0,
            'email_sent' => 0,
            'sms_sent' => 0,
            'whatsapp_sent' => 0,
            'reports_generated' => 0,
        ]);
    }

    protected function updateMonthlyStats(string $metric, int $amount): void
    {
        $monthlyStats = $this->monthly_stats ?? [];
        $currentMonth = now()->format('Y-m');

        if (!isset($monthlyStats[$currentMonth])) {
            $monthlyStats[$currentMonth] = [];
        }

        $monthlyStats[$currentMonth][$metric] =
            ($monthlyStats[$currentMonth][$metric] ?? 0) + $amount;

        $this->monthly_stats = $monthlyStats;
        $this->save();
    }

    protected function getDefaultLimit(string $metric): int
    {
        return match ($metric) {
            'users' => 10,
            'branches' => 1,
            'patients' => 1000,
            'appointments' => 10000,
            'services' => 5000,
            'storage_mb' => 1024, // 1GB
            'api_requests' => 100000,
            'email_sent' => 1000,
            'sms_sent' => 500,
            'whatsapp_sent' => 500,
            'reports_generated' => 100,
            default => 0,
        };
    }

    /**
     * Get the usage metric name for a messaging channel.
     */
    public static function getChannelMetric(string $channel): string
    {
        return match ($channel) {
            'whatsapp' => 'whatsapp_sent',
            'sms' => 'sms_sent',
            'email' => 'email_sent',
            default => throw new \InvalidArgumentException("Unknown channel: {$channel}"),
        };
    }

    /**
     * Check if sending via channel is allowed (quota not exceeded).
     */
    public function canSendViaChannel(string $channel): bool
    {
        $metric = self::getChannelMetric($channel);
        return !$this->isOverLimit($metric);
    }

    /**
     * Track message sent via channel.
     */
    public function trackMessageSent(string $channel, int $count = 1): void
    {
        $metric = self::getChannelMetric($channel);
        $this->incrementUsage($metric, $count);
    }

    public function getMonthlyUsage(string $month = null): array
    {
        $month = $month ?? now()->format('Y-m');
        $monthlyStats = $this->monthly_stats ?? [];

        return $monthlyStats[$month] ?? [];
    }

    public function getUsageGrowth(string $metric, int $months = 3): array
    {
        $monthlyStats = $this->monthly_stats ?? [];
        $growth = [];

        for ($i = $months; $i >= 0; $i--) {
            $month = now()->subMonths($i)->format('Y-m');
            $usage = $monthlyStats[$month][$metric] ?? 0;
            $growth[] = [
                'month' => $month,
                'usage' => $usage,
            ];
        }

        return $growth;
    }
}