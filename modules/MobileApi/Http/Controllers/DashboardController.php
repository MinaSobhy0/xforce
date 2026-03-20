<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseApiController
{
    /**
     * Get dashboard data for the staff member.
     * GET /api/v2/staff/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->user();
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        $data = [
            'greeting' => $this->getGreeting($user),
            'attendance' => $this->getAttendanceStatus($staffProfile),
            'stats' => $this->getStats($user, $staffProfile),
            'upcoming_appointments' => $this->getUpcomingAppointments($staffProfile),
            'recent_activity' => $this->getRecentActivity($staffProfile),
        ];

        return $this->success($data);
    }

    /**
     * Get personalized greeting.
     */
    protected function getGreeting($user): array
    {
        $hour = now()->hour;
        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };

        return [
            'text' => $greeting,
            'name' => $user->first_name,
            'date' => now()->format('l, F j, Y'),
        ];
    }

    /**
     * Get current attendance status.
     */
    protected function getAttendanceStatus($staffProfile): ?array
    {
        if (!$this->hasPermission('attendance.view')) {
            return null;
        }

        if (!class_exists(\Modules\Attendance\Models\AttendanceRecord::class)) {
            return null;
        }

        $attendance = \Modules\Attendance\Models\AttendanceRecord::where('staff_profile_id', $staffProfile->id)
            ->whereDate('check_in', today())
            ->first();

        if (!$attendance) {
            return [
                'status' => 'not_checked_in',
                'can_check_in' => true,
                'can_check_out' => false,
                'can_start_break' => false,
            ];
        }

        $isOnBreak = $attendance->breaks()
            ->whereNull('ended_at')
            ->exists();

        return [
            'status' => $attendance->check_out ? 'checked_out' : ($isOnBreak ? 'on_break' : 'checked_in'),
            'check_in_time' => $attendance->check_in->format('H:i'),
            'check_out_time' => $attendance->check_out?->format('H:i'),
            'worked_hours' => $this->calculateWorkedHours($attendance),
            'break_minutes' => $attendance->breaks()->sum('duration_minutes'),
            'can_check_in' => false,
            'can_check_out' => !$attendance->check_out && !$isOnBreak,
            'can_start_break' => !$attendance->check_out && !$isOnBreak,
            'can_end_break' => $isOnBreak,
        ];
    }

    /**
     * Calculate worked hours.
     */
    protected function calculateWorkedHours($attendance): float
    {
        $checkOut = $attendance->check_out ?? now();
        $totalMinutes = $attendance->check_in->diffInMinutes($checkOut);
        $breakMinutes = $attendance->breaks()->sum('duration_minutes');

        return round(($totalMinutes - $breakMinutes) / 60, 1);
    }

    /**
     * Get dashboard stats.
     */
    protected function getStats($user, $staffProfile): array
    {
        $stats = [];

        // Today's appointments
        if ($this->hasPermission('appointments.view') && class_exists(\Modules\Booking\Models\Appointment::class)) {
            $appointments = \Modules\Booking\Models\Appointment::whereDate('date', today())
                ->where('practitioner_id', $user->id)
                ->get();

            $stats['today_appointments'] = [
                'value' => $appointments->count(),
                'label' => __('mobile_api::mobile.dashboard.today_appointments'),
            ];

            $stats['completed_appointments'] = [
                'value' => $appointments->where('status', 'completed')->count(),
                'label' => __('mobile_api::mobile.dashboard.completed'),
            ];
        }

        // Pending commission
        if ($this->hasPermission('commission.view_own') && class_exists(\Modules\Staff\Models\CommissionRecord::class)) {
            $pendingCommission = \Modules\Staff\Models\CommissionRecord::where('staff_profile_id', $staffProfile->id)
                ->where('status', 'pending')
                ->sum('amount');

            $stats['pending_commission'] = [
                'value' => number_format($pendingCommission, 2),
                'label' => __('mobile_api::mobile.dashboard.pending_commission'),
                'currency' => $this->tenant()->currency ?? 'EGP',
            ];
        }

        // Hours worked today
        if ($this->hasPermission('attendance.view') && class_exists(\Modules\Attendance\Models\AttendanceRecord::class)) {
            $attendance = \Modules\Attendance\Models\AttendanceRecord::where('staff_profile_id', $staffProfile->id)
                ->whereDate('check_in', today())
                ->first();

            $hoursToday = 0;
            if ($attendance) {
                $hoursToday = $this->calculateWorkedHours($attendance);
            }

            $stats['hours_today'] = [
                'value' => $hoursToday,
                'label' => __('mobile_api::mobile.dashboard.hours_today'),
            ];
        }

        return $stats;
    }

    /**
     * Get upcoming appointments.
     */
    protected function getUpcomingAppointments($staffProfile, int $limit = 3): array
    {
        if (!$this->hasPermission('appointments.view')) {
            return [];
        }

        if (!class_exists(\Modules\Booking\Models\Appointment::class)) {
            return [];
        }

        $appointments = \Modules\Booking\Models\Appointment::with(['patient', 'service'])
            ->where('practitioner_id', $this->user()->id)
            ->where(function ($q) {
                $q->whereDate('date', '>', today())
                  ->orWhere(function ($q2) {
                      $q2->whereDate('date', today())
                         ->whereTime('start_time', '>=', now()->format('H:i:s'));
                  });
            })
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get();

        return $appointments->map(fn($apt) => [
            'id' => $apt->id,
            'patient_name' => $apt->patient?->full_name,
            'service_name' => $apt->service?->name,
            'scheduled_at' => $apt->date->format('Y-m-d') . ' ' . $apt->start_time->format('H:i'),
            'duration_minutes' => $apt->duration_minutes,
            'status' => $apt->status,
        ])->all();
    }

    /**
     * Get recent activity.
     */
    protected function getRecentActivity($staffProfile, int $limit = 5): array
    {
        // This could be implemented with an activity log
        // For now, return empty array
        return [];
    }
}
