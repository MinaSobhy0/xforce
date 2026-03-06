<?php

namespace Modules\Booking\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Booking\Models\Appointment;
use Modules\Booking\Models\TreatmentSessionData;
use Modules\Booking\Models\SessionConsumable;
use Modules\Booking\Models\SessionProduct;
use Modules\Equipment\Models\Equipment;
use Modules\Equipment\Models\EquipmentShotLog;
use Modules\Services\Models\Service;

class TreatmentAnalyticsService
{
    /**
     * Get parameter statistics for a service over a date range.
     */
    public function getParameterStatistics(
        string $serviceId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $startDate = $startDate ?? now()->subMonths(3);
        $endDate = $endDate ?? now();

        $sessions = TreatmentSessionData::query()
            ->where('service_id', $serviceId)
            ->where('is_complete', true)
            ->whereBetween('session_ended_at', [$startDate, $endDate])
            ->whereNotNull('parameter_values')
            ->get();

        if ($sessions->isEmpty()) {
            return [];
        }

        // Collect all parameter values
        $parameterData = [];
        foreach ($sessions as $session) {
            foreach ($session->parameter_values ?? [] as $key => $value) {
                if (is_numeric($value)) {
                    $parameterData[$key][] = (float) $value;
                }
            }
        }

        // Calculate statistics for each parameter
        $statistics = [];
        foreach ($parameterData as $key => $values) {
            $count = count($values);
            if ($count === 0) continue;

            sort($values);
            $sum = array_sum($values);

            $statistics[$key] = [
                'count' => $count,
                'min' => min($values),
                'max' => max($values),
                'avg' => round($sum / $count, 2),
                'median' => $this->calculateMedian($values),
                'std_dev' => $this->calculateStdDev($values),
            ];
        }

        return $statistics;
    }

    /**
     * Get session metrics summary.
     */
    public function getSessionMetrics(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?string $serviceId = null,
        ?string $practitionerId = null
    ): array {
        $startDate = $startDate ?? now()->subMonths(1);
        $endDate = $endDate ?? now();

        $query = TreatmentSessionData::query()
            ->where('is_complete', true)
            ->whereBetween('session_ended_at', [$startDate, $endDate]);

        if ($serviceId) {
            $query->where('service_id', $serviceId);
        }

        if ($practitionerId) {
            $query->where('practitioner_id', $practitionerId);
        }

        $sessions = $query->get();

        $durations = $sessions->pluck('actual_duration_minutes')->filter()->values()->toArray();
        $painLevels = $sessions->pluck('pain_level')->filter()->map(fn ($v) => (int) $v)->values()->toArray();

        // Skin reaction distribution
        $skinReactions = $sessions->groupBy('skin_reaction')
            ->map(fn ($group) => $group->count())
            ->toArray();

        return [
            'total_sessions' => $sessions->count(),
            'duration' => [
                'avg' => count($durations) > 0 ? round(array_sum($durations) / count($durations), 1) : 0,
                'min' => count($durations) > 0 ? min($durations) : 0,
                'max' => count($durations) > 0 ? max($durations) : 0,
            ],
            'pain_level' => [
                'avg' => count($painLevels) > 0 ? round(array_sum($painLevels) / count($painLevels), 1) : 0,
                'distribution' => $this->getPainLevelDistribution($painLevels),
            ],
            'skin_reactions' => $skinReactions,
            'completion_rate' => $this->getCompletionRate($startDate, $endDate, $serviceId),
        ];
    }

    /**
     * Get equipment utilization report.
     */
    public function getEquipmentUtilization(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?string $branchId = null
    ): array {
        $startDate = $startDate ?? now()->subMonths(1);
        $endDate = $endDate ?? now();

        $query = Equipment::query()
            ->where('status', Equipment::STATUS_ACTIVE)
            ->with(['shotLogs' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            }]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $equipment = $query->get();

        $utilization = [];
        foreach ($equipment as $eq) {
            $totalShots = $eq->shotLogs->sum('shots_fired');
            $sessionCount = $eq->shotLogs->count();

            $utilization[] = [
                'id' => $eq->id,
                'name' => $eq->name,
                'code' => $eq->code,
                'type' => $eq->equipmentType?->translated_name ?? '-',
                'total_shots' => $totalShots,
                'session_count' => $sessionCount,
                'avg_shots_per_session' => $sessionCount > 0 ? round($totalShots / $sessionCount, 1) : 0,
                'current_shot_count' => $eq->current_shot_count ?? 0,
                'max_shots' => $eq->max_shots ?? null,
                'utilization_percentage' => $eq->max_shots > 0
                    ? round(($eq->current_shot_count / $eq->max_shots) * 100, 1)
                    : null,
            ];
        }

        return $utilization;
    }

    /**
     * Get consumables usage report.
     */
    public function getConsumablesUsage(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?string $serviceId = null
    ): array {
        $startDate = $startDate ?? now()->subMonths(1);
        $endDate = $endDate ?? now();

        $query = SessionConsumable::query()
            ->select(
                'product_id',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(total_cost_minor) as total_cost'),
                DB::raw('COUNT(*) as usage_count'),
                DB::raw('AVG(quantity) as avg_quantity')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_deducted', true)
            ->groupBy('product_id')
            ->with('product');

        if ($serviceId) {
            $query->whereHas('appointment', function ($q) use ($serviceId) {
                $q->where('service_id', $serviceId);
            });
        }

        $consumables = $query->get();

        return $consumables->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'product_name' => $item->product?->getTranslation('name', app()->getLocale()) ?? 'Unknown',
                'product_sku' => $item->product?->sku ?? '-',
                'total_quantity' => round($item->total_quantity, 2),
                'total_cost' => $item->total_cost / 100,
                'usage_count' => $item->usage_count,
                'avg_quantity' => round($item->avg_quantity, 2),
                'unit' => $item->product?->unit_abbreviation ?? 'pcs',
            ];
        })->toArray();
    }

    /**
     * Get products usage/sales report.
     */
    public function getProductsUsage(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?string $usageType = null
    ): array {
        $startDate = $startDate ?? now()->subMonths(1);
        $endDate = $endDate ?? now();

        $query = SessionProduct::query()
            ->select(
                'product_id',
                'usage_type',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(total_price_minor) as total_revenue'),
                DB::raw('COUNT(*) as usage_count')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('product_id', 'usage_type')
            ->with('product');

        if ($usageType) {
            $query->where('usage_type', $usageType);
        }

        $products = $query->get();

        return $products->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'product_name' => $item->product?->getTranslation('name', app()->getLocale()) ?? 'Unknown',
                'product_sku' => $item->product?->sku ?? '-',
                'usage_type' => $item->usage_type,
                'total_quantity' => round($item->total_quantity, 2),
                'total_revenue' => $item->total_revenue / 100,
                'usage_count' => $item->usage_count,
            ];
        })->toArray();
    }

    /**
     * Get treatment outcomes by service.
     */
    public function getTreatmentOutcomes(
        string $serviceId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $startDate = $startDate ?? now()->subMonths(6);
        $endDate = $endDate ?? now();

        // Get session data with outcomes
        $sessions = TreatmentSessionData::query()
            ->where('service_id', $serviceId)
            ->where('is_complete', true)
            ->whereBetween('session_ended_at', [$startDate, $endDate])
            ->with('appointment.patient')
            ->get();

        // Group by patient to track progress
        $patientSessions = $sessions->groupBy('appointment.patient_id');

        $outcomes = [
            'total_patients' => $patientSessions->count(),
            'total_sessions' => $sessions->count(),
            'avg_sessions_per_patient' => $patientSessions->count() > 0
                ? round($sessions->count() / $patientSessions->count(), 1)
                : 0,
            'skin_reaction_summary' => $sessions->groupBy('skin_reaction')
                ->map(fn ($g) => [
                    'count' => $g->count(),
                    'percentage' => round(($g->count() / $sessions->count()) * 100, 1),
                ])
                ->toArray(),
            'pain_level_trend' => $this->calculatePainLevelTrend($sessions),
        ];

        return $outcomes;
    }

    /**
     * Get session trends over time.
     */
    public function getSessionTrends(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        string $groupBy = 'day'
    ): array {
        $startDate = $startDate ?? now()->subMonths(1);
        $endDate = $endDate ?? now();

        $format = match ($groupBy) {
            'week' => '%Y-%W',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $sessions = Appointment::query()
            ->select(
                DB::raw("TO_CHAR(date, 'YYYY-MM-DD') as date_group"),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled"),
                DB::raw("SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show")
            )
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('date_group')
            ->orderBy('date_group')
            ->get();

        return $sessions->map(function ($item) {
            return [
                'date' => $item->date_group,
                'total' => $item->total,
                'completed' => $item->completed,
                'cancelled' => $item->cancelled,
                'no_show' => $item->no_show,
                'completion_rate' => $item->total > 0
                    ? round(($item->completed / $item->total) * 100, 1)
                    : 0,
            ];
        })->toArray();
    }

    /**
     * Get top services by session count.
     */
    public function getTopServices(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        int $limit = 10
    ): array {
        $startDate = $startDate ?? now()->subMonths(1);
        $endDate = $endDate ?? now();

        return Appointment::query()
            ->select(
                'service_id',
                DB::raw('COUNT(*) as session_count'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count"),
                DB::raw('SUM(price_minor) as total_revenue')
            )
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNotNull('service_id')
            ->groupBy('service_id')
            ->orderByDesc('session_count')
            ->limit($limit)
            ->with('service')
            ->get()
            ->map(function ($item) {
                return [
                    'service_id' => $item->service_id,
                    'service_name' => $item->service?->translated_name ?? 'Unknown',
                    'session_count' => $item->session_count,
                    'completed_count' => $item->completed_count,
                    'completion_rate' => $item->session_count > 0
                        ? round(($item->completed_count / $item->session_count) * 100, 1)
                        : 0,
                    'total_revenue' => $item->total_revenue / 100,
                ];
            })
            ->toArray();
    }

    /**
     * Get practitioner performance metrics.
     */
    public function getPractitionerPerformance(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $startDate = $startDate ?? now()->subMonths(1);
        $endDate = $endDate ?? now();

        return Appointment::query()
            ->select(
                'practitioner_id',
                DB::raw('COUNT(*) as total_sessions'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"),
                DB::raw('AVG(duration_minutes) as avg_duration'),
                DB::raw('SUM(price_minor) as total_revenue')
            )
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNotNull('practitioner_id')
            ->groupBy('practitioner_id')
            ->orderByDesc('total_sessions')
            ->with('practitioner')
            ->get()
            ->map(function ($item) {
                return [
                    'practitioner_id' => $item->practitioner_id,
                    'practitioner_name' => $item->practitioner?->full_name ?? 'Unknown',
                    'total_sessions' => $item->total_sessions,
                    'completed' => $item->completed,
                    'completion_rate' => $item->total_sessions > 0
                        ? round(($item->completed / $item->total_sessions) * 100, 1)
                        : 0,
                    'avg_duration' => round($item->avg_duration ?? 0, 1),
                    'total_revenue' => $item->total_revenue / 100,
                ];
            })
            ->toArray();
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    protected function calculateMedian(array $values): float
    {
        $count = count($values);
        if ($count === 0) return 0;

        sort($values);
        $middle = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return round(($values[$middle - 1] + $values[$middle]) / 2, 2);
        }

        return round($values[$middle], 2);
    }

    protected function calculateStdDev(array $values): float
    {
        $count = count($values);
        if ($count < 2) return 0;

        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(fn ($v) => pow($v - $mean, 2), $values)) / ($count - 1);

        return round(sqrt($variance), 2);
    }

    protected function getPainLevelDistribution(array $painLevels): array
    {
        $distribution = [
            'none' => 0,      // 0
            'mild' => 0,      // 1-3
            'moderate' => 0,  // 4-6
            'severe' => 0,    // 7-10
        ];

        foreach ($painLevels as $level) {
            if ($level === 0) {
                $distribution['none']++;
            } elseif ($level <= 3) {
                $distribution['mild']++;
            } elseif ($level <= 6) {
                $distribution['moderate']++;
            } else {
                $distribution['severe']++;
            }
        }

        return $distribution;
    }

    protected function getCompletionRate(Carbon $startDate, Carbon $endDate, ?string $serviceId = null): float
    {
        $query = Appointment::query()
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($serviceId) {
            $query->where('service_id', $serviceId);
        }

        $total = $query->count();
        $completed = $query->clone()->where('status', Appointment::STATUS_COMPLETED)->count();

        return $total > 0 ? round(($completed / $total) * 100, 1) : 0;
    }

    protected function calculatePainLevelTrend(Collection $sessions): array
    {
        // Group sessions by week and calculate average pain level
        return $sessions
            ->filter(fn ($s) => $s->pain_level !== null)
            ->groupBy(fn ($s) => $s->session_ended_at?->format('Y-W'))
            ->map(function ($group, $week) {
                $levels = $group->pluck('pain_level')->map(fn ($v) => (int) $v)->toArray();
                return [
                    'week' => $week,
                    'avg_pain_level' => count($levels) > 0 ? round(array_sum($levels) / count($levels), 1) : 0,
                    'session_count' => $group->count(),
                ];
            })
            ->values()
            ->toArray();
    }
}
