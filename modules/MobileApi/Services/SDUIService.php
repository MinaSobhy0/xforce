<?php

namespace Modules\MobileApi\Services;

use Modules\Auth\Models\User;
use Modules\Core\Models\Tenant;

class SDUIService
{
    protected string $version = '1.0.0';

    /**
     * Build a screen definition for a user.
     */
    public function buildScreen(string $screenId, User $user): array
    {
        $screen = $this->getScreenDefinition($screenId);

        if (! $screen) {
            return [];
        }

        // Get tenant config
        $tenant = $this->getCurrentTenant();
        $config = $tenant?->getMobileAppConfig() ?? Tenant::getDefaultMobileConfig();

        // Apply tenant customizations to the screen
        $screen = $this->applyTenantCustomizations($screen, $screenId, $config);

        // Filter components based on user permissions and tenant config
        $screen['components'] = collect($screen['components'] ?? [])
            ->filter(fn ($component) => $this->userCanSeeComponent($user, $component))
            ->filter(fn ($component) => $this->isComponentEnabled($component, $screenId, $config))
            ->sortBy('sort')
            ->map(fn ($component) => $this->processComponent($component, $user))
            ->values()
            ->all();

        $screen['version'] = $this->version;
        $screen['timestamp'] = now()->toIso8601String();

        return $screen;
    }

    /**
     * Get the current tenant from the container.
     */
    protected function getCurrentTenant(): ?Tenant
    {
        return app('currentTenant');
    }

    /**
     * Apply tenant customizations to a screen definition.
     */
    protected function applyTenantCustomizations(array $screen, string $screenId, array $config): array
    {
        $screenConfig = $config['screens'][$screenId] ?? null;

        if (! $screenConfig) {
            return $screen;
        }

        // Merge component overrides
        foreach ($screen['components'] as &$component) {
            $override = collect($screenConfig['components'] ?? [])
                ->firstWhere('type', $component['type']);

            if ($override) {
                $component['enabled'] = $override['enabled'] ?? true;
                $component['sort'] = $override['sort'] ?? 999;
                $component['props'] = array_merge(
                    $component['props'] ?? [],
                    $override['props'] ?? []
                );
            }
        }

        return $screen;
    }

    /**
     * Check if a component is enabled based on tenant config.
     */
    protected function isComponentEnabled(array $component, string $screenId, array $config): bool
    {
        $screenConfig = $config['screens'][$screenId] ?? null;

        if (! $screenConfig) {
            return true; // Default: enabled if no screen config
        }

        $componentConfig = collect($screenConfig['components'] ?? [])
            ->firstWhere('type', $component['type']);

        if (! $componentConfig) {
            return true; // Default: enabled if component not in config
        }

        return $componentConfig['enabled'] ?? true;
    }

    /**
     * Get navigation configuration for the tenant.
     */
    public function getNavigation(): array
    {
        $tenant = $this->getCurrentTenant();
        $config = $tenant?->getMobileAppConfig() ?? Tenant::getDefaultMobileConfig();

        return [
            'tabs' => collect($config['navigation']['tabs'] ?? [])
                ->filter(fn ($tab) => $tab['enabled'] ?? true)
                ->unique('id')  // Deduplicate by id
                ->sortBy('sort')
                ->values()
                ->map(fn ($tab) => [
                    'id' => $tab['id'],
                    'label' => $this->getScreenLabel($tab['id']),
                    'icon' => $this->getScreenIcon($tab['id']),
                ])
                ->all(),
            'more_menu' => collect($config['navigation']['more_menu'] ?? [])
                ->filter(fn ($item) => $item['enabled'] ?? true)
                ->unique('id')  // Deduplicate by id
                ->sortBy('sort')
                ->values()
                ->map(fn ($item) => [
                    'id' => $item['id'],
                    'label' => $this->getScreenLabel($item['id']),
                    'icon' => $this->getScreenIcon($item['id']),
                ])
                ->all(),
        ];
    }

    /**
     * Get the label for a screen.
     */
    protected function getScreenLabel(string $screenId): string
    {
        return match ($screenId) {
            'dashboard' => __('mobile_api::mobile.screens.dashboard'),
            'appointments' => __('mobile_api::mobile.screens.appointments'),
            'attendance' => __('mobile_api::mobile.screens.attendance'),
            'schedule' => __('mobile_api::mobile.screens.schedule'),
            'more' => __('mobile_api::mobile.screens.more'),
            'payslip' => __('mobile_api::mobile.screens.payslip'),
            'time_off' => __('mobile_api::mobile.screens.time_off'),
            'commission' => __('mobile_api::mobile.screens.commission'),
            'patients' => __('mobile_api::mobile.screens.patients'),
            'profile' => __('mobile_api::mobile.screens.profile'),
            default => ucfirst(str_replace('_', ' ', $screenId)),
        };
    }

    /**
     * Get the icon for a screen.
     */
    protected function getScreenIcon(string $screenId): string
    {
        return match ($screenId) {
            'dashboard' => 'home',
            'appointments' => 'calendar',
            'attendance' => 'clock',
            'schedule' => 'calendar-days',
            'more' => 'ellipsis-horizontal',
            'payslip' => 'document-text',
            'time_off' => 'sun',
            'commission' => 'currency-dollar',
            'patients' => 'users',
            'profile' => 'user-circle',
            default => 'squares-2x2',
        };
    }

    /**
     * Get features configuration for the tenant.
     */
    public function getFeatures(): array
    {
        $tenant = $this->getCurrentTenant();
        $config = $tenant?->getMobileAppConfig() ?? Tenant::getDefaultMobileConfig();

        return $config['features'] ?? [];
    }

    /**
     * Get quick actions configuration for the tenant.
     */
    public function getQuickActions(): array
    {
        $tenant = $this->getCurrentTenant();
        $config = $tenant?->getMobileAppConfig() ?? Tenant::getDefaultMobileConfig();

        return collect($config['quick_actions'] ?? [])
            ->filter(fn ($action) => $action['enabled'] ?? false)
            ->sortBy('sort')
            ->values()
            ->map(fn ($action) => [
                'id' => $action['id'],
                'label' => $this->getScreenLabel($action['id']),
                'icon' => $action['icon'] ?? $this->getScreenIcon($action['id']),
                'icon_color' => $action['icon_color'] ?? '#3B82F6',
            ])
            ->all();
    }

    /**
     * Get quick action items for dashboard component.
     */
    protected function getQuickActionsItems(): array
    {
        $tenant = $this->getCurrentTenant();
        $config = $tenant?->getMobileAppConfig() ?? Tenant::getDefaultMobileConfig();

        return collect($config['quick_actions'] ?? [])
            ->filter(fn ($action) => $action['enabled'] ?? false)
            ->sortBy('sort')
            ->values()
            ->map(fn ($action) => [
                'key' => $action['id'],
                'icon' => $action['icon'] ?? $this->getScreenIcon($action['id']),
                'icon_color' => $action['icon_color'] ?? '#3B82F6',
            ])
            ->all();
    }

    /**
     * Get branding configuration for the tenant.
     */
    public function getBranding(): array
    {
        $tenant = $this->getCurrentTenant();
        $config = $tenant?->getMobileAppConfig() ?? Tenant::getDefaultMobileConfig();

        $branding = $config['branding'] ?? [];

        return [
            'app_name' => $branding['app_name'] ?? $tenant?->name ?? 'Staff App',
            'primary_color' => $branding['primary_color'] ?? $tenant?->primary_color ?? '#3B82F6',
            'secondary_color' => $branding['secondary_color'] ?? $tenant?->secondary_color ?? '#1E40AF',
            'accent_color' => $branding['accent_color'] ?? '#F59E0B',
            'logo_url' => $this->resolveLogoUrl($branding['logo_url'] ?? null, $tenant),
            'dark_mode_enabled' => $branding['dark_mode_enabled'] ?? true,
        ];
    }

    /**
     * Resolve the logo URL, falling back to tenant logo if not set.
     */
    protected function resolveLogoUrl(?string $logoUrl, ?Tenant $tenant): ?string
    {
        if ($logoUrl) {
            // If it's already a full URL, return as-is
            if (filter_var($logoUrl, FILTER_VALIDATE_URL)) {
                return $logoUrl;
            }

            // Otherwise, it's a storage path
            return url('storage/'.$logoUrl);
        }

        return $tenant?->getLogoUrl();
    }

    /**
     * Get screen data only (for refresh).
     */
    public function getScreenData(string $screenId, User $user): array
    {
        $dataProvider = $this->getDataProvider($screenId);

        if (! $dataProvider) {
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
            if (! $user->can($permission)) {
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
                    if (! $requires) {
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
            'dashboard' => fn ($user) => $this->getDashboardData($user),
            'attendance' => fn ($user) => $this->getAttendanceData($user),
            'time_off' => fn ($user) => $this->getTimeOffData($user),
            'payslip' => fn ($user) => $this->getPayslipData($user),
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
                        'items' => $this->getQuickActionsItems(),
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
            $appointments = \Modules\Booking\Models\Appointment::whereDate('date', today())
                ->where('practitioner_id', $user->id)
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
