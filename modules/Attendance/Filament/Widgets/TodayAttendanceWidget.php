<?php

namespace Modules\Attendance\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\Attendance;
use Modules\Attendance\Models\AttendanceViolation;
use Modules\Staff\Models\StaffProfile;

class TodayAttendanceWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $today = now()->toDateString();

        // Get total active staff count
        $totalStaff = StaffProfile::query()
            ->where('status', 'active')
            ->count();

        // Today's attendance stats
        $todayAttendance = Attendance::whereDate('attendance_date', $today);

        $presentCount = (clone $todayAttendance)
            ->where('status', Attendance::STATUS_PRESENT)
            ->count();

        $lateCount = (clone $todayAttendance)
            ->where('late_hours', '>', 0)
            ->count();

        $onLeaveCount = (clone $todayAttendance)
            ->where('status', Attendance::STATUS_LEAVE)
            ->count();

        $checkedInCount = (clone $todayAttendance)
            ->whereNotNull('check_in_time')
            ->whereNull('check_out_time')
            ->count();

        $checkedOutCount = (clone $todayAttendance)
            ->whereNotNull('check_out_time')
            ->count();

        // Calculate absent (staff without attendance record today)
        $attendedStaffIds = Attendance::whereDate('attendance_date', $today)
            ->pluck('staff_profile_id')
            ->toArray();

        $absentCount = StaffProfile::query()
            ->where('status', 'active')
            ->whereNotIn('id', $attendedStaffIds)
            ->count();

        // Pending violations
        $pendingViolations = AttendanceViolation::query()
            ->where('status', AttendanceViolation::STATUS_PENDING)
            ->count();

        // Average working hours this week
        $avgHoursThisWeek = Attendance::whereBetween('attendance_date', [now()->startOfWeek(), now()])
            ->whereNotNull('working_hours')
            ->avg('working_hours') ?? 0;

        // Week comparison
        $thisWeekPresent = Attendance::whereBetween('attendance_date', [now()->startOfWeek(), now()])
            ->where('status', Attendance::STATUS_PRESENT)
            ->count();

        $lastWeekPresent = Attendance::whereBetween('attendance_date', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
            ->where('status', Attendance::STATUS_PRESENT)
            ->count();

        $weekTrend = $lastWeekPresent > 0
            ? round(($thisWeekPresent - $lastWeekPresent) / $lastWeekPresent * 100, 1)
            : 0;

        return [
            Stat::make(
                __('attendance::attendance.widgets.present_today'),
                $presentCount . '/' . $totalStaff
            )
                ->description(
                    __('attendance::attendance.widgets.checked_in') . ": {$checkedInCount} | " .
                    __('attendance::attendance.widgets.checked_out') . ": {$checkedOutCount}"
                )
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success')
                ->chart($this->getWeeklyPresentData()),

            Stat::make(
                __('attendance::attendance.widgets.late_absent'),
                $lateCount + $absentCount
            )
                ->description(
                    __('attendance::attendance.widgets.late') . ": {$lateCount} | " .
                    __('attendance::attendance.widgets.absent') . ": {$absentCount}"
                )
                ->descriptionIcon('heroicon-m-clock')
                ->color(($lateCount + $absentCount) > 0 ? 'warning' : 'gray'),

            Stat::make(
                __('attendance::attendance.widgets.on_leave'),
                $onLeaveCount
            )
                ->description(
                    ($weekTrend >= 0 ? '+' : '') . $weekTrend . '% ' . __('attendance::attendance.widgets.vs_last_week')
                )
                ->descriptionIcon($weekTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color('info'),

            Stat::make(
                __('attendance::attendance.widgets.pending_violations'),
                $pendingViolations
            )
                ->description(
                    __('attendance::attendance.widgets.avg_hours') . ': ' . round($avgHoursThisWeek, 1) . 'h'
                )
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($pendingViolations > 0 ? 'danger' : 'gray'),
        ];
    }

    protected function getWeeklyPresentData(): array
    {
        return Attendance::whereBetween('attendance_date', [now()->subDays(6), now()])
            ->where('status', Attendance::STATUS_PRESENT)
            ->groupBy('attendance_date')
            ->orderBy('attendance_date')
            ->pluck(DB::raw('COUNT(*)'))
            ->toArray();
    }
}
