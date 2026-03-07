<?php

namespace Modules\Core\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Patients\Models\Patient;
use Modules\Booking\Models\Appointment;
use Modules\Billing\Models\Invoice;
use Illuminate\Support\Facades\DB;

class TenantOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        // Super admin and key roles always see overview
        if (method_exists($user, 'hasRole') && $user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner', 'admin'])) {
            return true;
        }

        // Anyone with reports.view can see overview
        return $user->can('reports.view') || !\Spatie\Permission\Models\Permission::where('name', 'reports.view')->where('guard_name', 'web')->exists();
    }

    protected function getStats(): array
    {
        // Patients
        $totalPatients = class_exists(Patient::class) ? Patient::count() : 0;
        $newPatientsThisMonth = class_exists(Patient::class)
            ? Patient::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count()
            : 0;

        // Appointments today
        $todayAppointments = class_exists(Appointment::class)
            ? Appointment::whereDate('date', today())->count()
            : 0;
        $upcomingAppointments = class_exists(Appointment::class)
            ? Appointment::whereDate('date', '>', today())
                ->whereIn('status', [
                    Appointment::STATUS_SCHEDULED,
                    Appointment::STATUS_CONFIRMED,
                ])
                ->count()
            : 0;

        // Revenue this month
        $monthlyRevenue = class_exists(Invoice::class)
            ? Invoice::where('status', 'paid')
                ->whereMonth('issued_at', now()->month)
                ->whereYear('issued_at', now()->year)
                ->sum('paid_minor')
            : 0;

        $lastMonthRevenue = class_exists(Invoice::class)
            ? Invoice::where('status', 'paid')
                ->whereMonth('issued_at', now()->subMonth()->month)
                ->whereYear('issued_at', now()->subMonth()->year)
                ->sum('paid_minor')
            : 0;

        $revenueTrend = $lastMonthRevenue > 0
            ? round(($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue * 100, 1)
            : 0;

        // Outstanding balance
        $outstandingBalance = class_exists(Invoice::class)
            ? Invoice::whereIn('status', [
                    Invoice::STATUS_ISSUED,
                    Invoice::STATUS_PARTIALLY_PAID,
                    Invoice::STATUS_OVERDUE,
                ])
                ->selectRaw('COALESCE(SUM(total_minor - paid_minor), 0) as outstanding')
                ->value('outstanding') ?? 0
            : 0;

        return [
            Stat::make(
                __('core::core.widgets.total_patients'),
                number_format($totalPatients)
            )
                ->description('+' . $newPatientsThisMonth . ' ' . __('core::core.widgets.this_month'))
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('primary')
                ->chart($this->getPatientTrend()),

            Stat::make(
                __('core::core.widgets.todays_appointments'),
                $todayAppointments
            )
                ->description($upcomingAppointments . ' ' . __('core::core.widgets.upcoming'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('success'),

            Stat::make(
                __('core::core.widgets.monthly_revenue'),
                $this->formatCurrency($monthlyRevenue)
            )
                ->description(
                    ($revenueTrend >= 0 ? '+' : '') . $revenueTrend . '% ' . __('core::core.widgets.vs_last_month')
                )
                ->descriptionIcon($revenueTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueTrend >= 0 ? 'success' : 'danger'),

            Stat::make(
                __('core::core.widgets.outstanding_balance'),
                $this->formatCurrency($outstandingBalance)
            )
                ->description(__('core::core.widgets.pending_payments'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($outstandingBalance > 0 ? 'warning' : 'gray'),
        ];
    }

    protected function getPatientTrend(): array
    {
        if (!class_exists(Patient::class)) {
            return [];
        }

        return Patient::where('created_at', '>=', now()->subDays(7))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->select(DB::raw('COUNT(*) as count'))
            ->pluck('count')
            ->toArray();
    }

    protected function formatCurrency(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2) . ' EGP';
    }
}
