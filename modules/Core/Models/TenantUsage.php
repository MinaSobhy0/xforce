<?php

namespace Modules\Core\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUsage extends BaseModel
{
    /**
     * This is a central table (not tenant-specific), so it uses the
     * central/public schema connection instead of tenant connection.
     */
    protected $connection = 'pgsql';

    protected $table = 'tenant_usage';

    protected $fillable = [
        'tenant_id',
        'users',
        'branches',
        'patients',
        'appointments',
        'treatments',
        'storage_mb',
        'api_requests',
        'email_sent',
        'sms_sent',
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
        'treatments' => 'integer',
        'storage_mb' => 'integer',
        'api_requests' => 'integer',
        'email_sent' => 'integer',
        'sms_sent' => 'integer',
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
            'treatments' => $this->treatments,
            'api_requests' => $this->api_requests,
            'email_sent' => $this->email_sent,
            'sms_sent' => $this->sms_sent,
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
            'treatments' => 5000,
            'storage_mb' => 1024, // 1GB
            'api_requests' => 100000,
            'email_sent' => 1000,
            'sms_sent' => 500,
            'reports_generated' => 100,
            default => 0,
        };
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