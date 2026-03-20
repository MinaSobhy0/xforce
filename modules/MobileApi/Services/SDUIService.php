<?php

namespace Modules\MobileApi\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Auth\Models\User;

class SDUIService
{
    protected string $version = '1.0.0';

    /**
     * Build a screen definition for a user.
     */
    public function buildScreen(string $screenId, User $user): array
    {
        $screen = $this->getScreenDefinition($screenId);

        if (!$screen) {
            return [];
        }

        // Filter components based on user permissions
        $screen['components'] = collect($screen['components'] ?? [])
            ->filter(fn($component) => $this->userCanSeeComponent($user, $component))
            ->map(fn($component) => $this->processComponent($component, $user))
            ->values()
            ->all();

        $screen['version'] = $this->version;
        $screen['timestamp'] = now()->toIso8601String();

        return $screen;
    }

    /**
     * Get screen data only (for refresh).
     */
    public function getScreenData(string $screenId, User $user): array
    {
        $dataProvider = $this->getDataProvider($screenId);

        if (!$dataProvider) {
            return [];
        }

        return $dataProvider($user);
    }

    /**
     * Check if user can see a component.
     */
    protected function userCanSeeComponent(User $user, array $component): bool
    {
        $requires = $component['requires'] ?? [];

        if (empty($requires)) {
            return true;
        }

        if (is_string($requires)) {
            $requires = [$requires];
        }

        foreach ($requires as $permission) {
            if (!$user->can($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Process a component, filtering nested items by permission.
     */
    protected function processComponent(array $component, User $user): array
    {
        $props = $component['props'] ?? [];

        // Filter items in grid/list components
        if (isset($props['items'])) {
            $props['items'] = collect($props['items'])
                ->filter(function ($item) use ($user) {
                    $requires = $item['requires'] ?? null;
                    if (!$requires) {
                        return true;
                    }
                    return $user->can($requires);
                })
                ->values()
                ->all();

            $component['props'] = $props;
        }

        return $component;
    }

    /**
     * Get the screen definition.
     */
    protected function getScreenDefinition(string $screenId): ?array
    {
        $screens = [
            'dashboard' => $this->getDashboardScreen(),
            'attendance' => $this->getAttendanceScreen(),
            'time_off' => $this->getTimeOffScreen(),
            'payslip' => $this->getPayslipScreen(),
            'schedule' => $this->getScheduleScreen(),
            'appointments' => $this->getAppointmentsScreen(),
            'patients' => $this->getPatientsScreen(),
            'profile' => $this->getProfileScreen(),
            'commission' => $this->getCommissionScreen(),
        ];

        return $screens[$screenId] ?? null;
    }

    /**
     * Get data provider for a screen.
     */
    protected function getDataProvider(string $screenId): ?callable
    {
        $providers = [
            'dashboard' => fn($user) => $this->getDashboardData($user),
            'attendance' => fn($user) => $this->getAttendanceData($user),
            'time_off' => fn($user) => $this->getTimeOffData($user),
            'payslip' => fn($user) => $this->getPayslipData($user),
        ];

        return $providers[$screenId] ?? null;
    }

    // Screen Definitions

    protected function getDashboardScreen(): array
    {
        return [
            'id' => 'dashboard',
            'title' => __('mobile_api::mobile.screens.dashboard'),
            'components' => [
                [
                    'type' => 'attendance_status',
                    'requires' => ['attendance.view'],
                    'props' => [
                        'actions' => ['check_in', 'check_out', 'break'],
                    ],
                ],
                [
                    'type' => 'stats_grid',
                    'props' => [
                        'items' => [
                            [
                                'key' => 'today_appointments',
                                'requires' => 'appointments.view',
                                'label' => __('mobile_api::mobile.dashboard.today_appointments'),
                            ],
                            [
                                'key' => 'completed',
                                'requires' => 'appointments.view',
                                'label' => __('mobile_api::mobile.dashboard.completed'),
                            ],
                            [
                                'key' => 'pending_commission',
                                'requires' => 'commission.view_own',
                                'label' => __('mobile_api::mobile.dashboard.pending_commission'),
                            ],
                            [
                                'key' => 'hours_today',
                                'requires' => 'attendance.view',
                                'label' => __('mobile_api::mobile.dashboard.hours_today'),
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'upcoming_appointments',
                    'requires' => ['appointments.view'],
                    'props' => [
                        'limit' => 3,
                    ],
                ],
                [
                    'type' => 'quick_actions',
                    'props' => [
                        'items' => [
                            ['key' => 'schedule', 'requires' => 'schedule.view', 'icon' => 'calendar'],
                            ['key' => 'time_off', 'requires' => 'time_off.view', 'icon' => 'clock'],
                            ['key' => 'payslip', 'requires' => 'payroll.view_own', 'icon' => 'document'],
                            ['key' => 'patients', 'requires' => 'patients.view', 'icon' => 'users'],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function getAttendanceScreen(): array
    {
        return [
            'id' => 'attendance',
            'title' => __('mobile_api::mobile.screens.attendance'),
            'components' => [
                [
                    'type' => 'attendance_card',
                    'props' => [
                        'show_check_in_time' => true,
                        'show_breaks' => true,
                    ],
                ],
                [
                    'type' => 'tab_bar',
                    'props' => [
                        'tabs' => ['history', 'violations', 'summary'],
                    ],
                ],
                [
                    'type' => 'attendance_history',
                    'props' => [
                        'grouped_by' => 'month',
                    ],
                ],
            ],
        ];
    }

    protected function getTimeOffScreen(): array
    {
        return [
            'id' => 'time_off',
            'title' => __('mobile_api::mobile.screens.time_off'),
            'components' => [
                [
                    'type' => 'balance_cards',
                    'props' => [
                        'types' => ['vacation', 'sick', 'personal'],
                    ],
                ],
                [
                    'type' => 'request_button',
                    'requires' => ['time_off.create'],
                    'props' => [
                        'actions' => [
                            ['type' => 'navigate', 'target' => 'time_off_request'],
                        ],
                    ],
                ],
                [
                    'type' => 'requests_list',
                    'props' => [
                        'filter' => 'all',
                    ],
                ],
            ],
        ];
    }

    protected function getPayslipScreen(): array
    {
        return [
            'id' => 'payslip',
            'title' => __('mobile_api::mobile.screens.payslip'),
            'components' => [
                [
                    'type' => 'period_selector',
                    'props' => [
                        'format' => 'month_year',
                    ],
                ],
                [
                    'type' => 'salary_summary',
                    'props' => [
                        'show_net' => true,
                    ],
                ],
                [
                    'type' => 'earnings_breakdown',
                    'props' => [
                        'sections' => ['base', 'allowances', 'commissions', 'bonuses'],
                    ],
                ],
                [
                    'type' => 'deductions_breakdown',
                    'props' => [
                        'sections' => ['tax', 'insurance', 'violations'],
                    ],
                ],
                [
                    'type' => 'download_button',
                    'props' => [
                        'format' => 'pdf',
                    ],
                ],
            ],
        ];
    }

    protected function getScheduleScreen(): array
    {
        return [
            'id' => 'schedule',
            'title' => __('mobile_api::mobile.screens.schedule'),
            'components' => [
                [
                    'type' => 'week_calendar',
                    'props' => [
                        'show_today' => true,
                    ],
                ],
                [
                    'type' => 'shift_card',
                    'props' => [
                        'show_times' => true,
                        'show_break' => true,
                    ],
                ],
                [
                    'type' => 'working_hours',
                    'props' => [
                        'format' => 'weekly',
                    ],
                ],
            ],
        ];
    }

    protected function getAppointmentsScreen(): array
    {
        return [
            'id' => 'appointments',
            'title' => __('mobile_api::mobile.screens.appointments'),
            'components' => [
                [
                    'type' => 'date_picker',
                    'props' => [
                        'default' => 'today',
                    ],
                ],
                [
                    'type' => 'appointments_list',
                    'props' => [
                        'grouped_by' => 'time',
                        'show_status' => true,
                    ],
                ],
            ],
        ];
    }

    protected function getPatientsScreen(): array
    {
        return [
            'id' => 'patients',
            'title' => __('mobile_api::mobile.screens.patients'),
            'components' => [
                [
                    'type' => 'search_bar',
                    'props' => [
                        'placeholder' => 'Search patients...',
                    ],
                ],
                [
                    'type' => 'patients_list',
                    'props' => [
                        'show_avatar' => true,
                        'show_recent_visit' => true,
                    ],
                ],
            ],
        ];
    }

    protected function getProfileScreen(): array
    {
        return [
            'id' => 'profile',
            'title' => __('mobile_api::mobile.screens.profile'),
            'components' => [
                [
                    'type' => 'profile_header',
                    'props' => [
                        'show_avatar' => true,
                        'show_employee_number' => true,
                    ],
                ],
                [
                    'type' => 'profile_details',
                    'props' => [
                        'editable' => ['phone', 'emergency_contact'],
                    ],
                ],
                [
                    'type' => 'action_list',
                    'props' => [
                        'items' => [
                            ['key' => 'commission', 'requires' => 'commission.view_own'],
                            ['key' => 'documents'],
                            ['key' => 'settings'],
                            ['key' => 'logout'],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function getCommissionScreen(): array
    {
        return [
            'id' => 'commission',
            'title' => __('mobile_api::mobile.screens.commission'),
            'requires' => ['commission.view_own'],
            'components' => [
                [
                    'type' => 'commission_summary',
                    'props' => [
                        'show_pending' => true,
                        'show_paid' => true,
                    ],
                ],
                [
                    'type' => 'commission_plan',
                    'props' => [],
                ],
                [
                    'type' => 'commission_history',
                    'props' => [
                        'grouped_by' => 'month',
                    ],
                ],
            ],
        ];
    }

    // Data Providers

    protected function getDashboardData(User $user): array
    {
        $staffProfile = $user->staffProfile;
        $data = [];

        // Today's appointments
        if ($user->can('appointments.view') && class_exists(\Modules\Booking\Models\Appointment::class)) {
            $appointments = \Modules\Booking\Models\Appointment::whereDate('scheduled_at', today())
                ->where('practitioner_id', $staffProfile?->id)
                ->get();

            $data['today_appointments'] = $appointments->count();
            $data['completed'] = $appointments->where('status', 'completed')->count();
        }

        // Pending commission
        if ($user->can('commission.view_own') && class_exists(\Modules\Staff\Models\CommissionRecord::class)) {
            $data['pending_commission'] = \Modules\Staff\Models\CommissionRecord::where('staff_profile_id', $staffProfile?->id)
                ->where('status', 'pending')
                ->sum('amount');
        }

        // Hours worked today
        if ($user->can('attendance.view') && class_exists(\Modules\Attendance\Models\AttendanceRecord::class)) {
            $attendance = \Modules\Attendance\Models\AttendanceRecord::where('staff_profile_id', $staffProfile?->id)
                ->whereDate('check_in', today())
                ->first();

            if ($attendance) {
                $checkOut = $attendance->check_out ?? now();
                $totalMinutes = $attendance->check_in->diffInMinutes($checkOut);
                $breakMinutes = $attendance->breaks()->sum('duration_minutes');
                $data['hours_today'] = round(($totalMinutes - $breakMinutes) / 60, 1);
            } else {
                $data['hours_today'] = 0;
            }
        }

        return $data;
    }

    protected function getAttendanceData(User $user): array
    {
        // Implemented in AttendanceController
        return [];
    }

    protected function getTimeOffData(User $user): array
    {
        // Implemented in TimeOffController
        return [];
    }

    protected function getPayslipData(User $user): array
    {
        // Implemented in PayrollController
        return [];
    }
}
