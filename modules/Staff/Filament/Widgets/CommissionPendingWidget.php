<?php

namespace Modules\Staff\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Staff\Models\StaffCommissionRecord;
use Illuminate\Support\Facades\DB;

class CommissionPendingWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        if (method_exists($user, 'hasRole') && $user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner', 'admin'])) {
            return true;
        }

        return $user->can('commission_plans.view') || !\Spatie\Permission\Models\Permission::where('name', 'commission_plans.view')->where('guard_name', 'web')->exists();
    }

    protected function getStats(): array
    {
        $pendingCount = StaffCommissionRecord::pending()->count();
        $pendingAmount = StaffCommissionRecord::pending()->sum('amount_minor');

        $approvedCount = StaffCommissionRecord::where('status', StaffCommissionRecord::STATUS_APPROVED)->count();
        $approvedAmount = StaffCommissionRecord::where('status', StaffCommissionRecord::STATUS_APPROVED)->sum('amount_minor');

        $paidThisMonth = StaffCommissionRecord::where('status', StaffCommissionRecord::STATUS_PAID)
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->sum('amount_minor');

        return [
            Stat::make(
                __('staff::staff.widgets.pending_commissions'),
                $pendingCount
            )
                ->description($this->formatCurrency($pendingAmount) . ' ' . __('staff::staff.widgets.pending_value'))
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->chart($this->getWeeklyPendingData()),

            Stat::make(
                __('staff::staff.widgets.approved_commissions'),
                $approvedCount
            )
                ->description($this->formatCurrency($approvedAmount) . ' ' . __('staff::staff.widgets.awaiting_payment'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('info'),

            Stat::make(
                __('staff::staff.widgets.paid_this_month'),
                $this->formatCurrency($paidThisMonth)
            )
                ->description(__('staff::staff.widgets.commissions_paid'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }

    protected function getWeeklyPendingData(): array
    {
        $data = StaffCommissionRecord::pending()
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->pluck('count')
            ->toArray();

        return !empty($data) ? $data : [0];
    }

    protected function formatCurrency(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2) . ' EGP';
    }
}
