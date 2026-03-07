<?php

namespace Modules\Core\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\TenantUsage;
use XLinic\Framework\Core\Tenancy\TenantManager;

class UsageDashboardPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'core::filament.pages.usage-dashboard';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 70;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Super admin and key roles have full access
        if (method_exists($user, 'hasRole') && $user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner', 'admin'])) {
            return true;
        }

        // Check reports.view permission (usage dashboard is a type of report)
        if ($user->can('reports.view')) {
            return true;
        }

        // If permission doesn't exist, allow access (fallback)
        $permissionExists = \Spatie\Permission\Models\Permission::where('name', 'reports.view')
            ->where('guard_name', 'web')
            ->exists();

        return !$permissionExists;
    }

    public ?array $usage = [];
    public ?array $limits = [];
    public ?array $monthlyStats = [];

    public static function getNavigationLabel(): string
    {
        return __('core::core.usage_dashboard');
    }

    public function getTitle(): string
    {
        return __('core::core.usage_dashboard');
    }

    public function mount(): void
    {
        $tenantManager = app(TenantManager::class);
        $tenant = $tenantManager->current();

        if (!$tenant) {
            $this->usage = [];
            $this->limits = [];
            $this->monthlyStats = [];
            return;
        }

        // Get current usage
        $tenantUsage = TenantUsage::where('tenant_id', $tenant->id)->first();

        // Get plan limits from tenant's plan + any extra purchased
        // Only users, branches, and storage are limited
        // Patients, services, products, equipment are unlimited
        $usersLimit = $tenant->getEffectiveLimit('users');
        $branchesLimit = $tenant->getEffectiveLimit('branches');
        $storageLimit = $tenant->getEffectiveLimit('storage_mb');

        $this->limits = [
            'users' => $usersLimit ?? 10,
            'branches' => $branchesLimit ?? 3,
            'storage_gb' => round(($storageLimit ?? 1024) / 1024, 1), // Convert MB to GB
        ];

        if ($tenantUsage) {
            $this->usage = [
                'users' => $tenantUsage->users ?? 0,
                'branches' => $tenantUsage->branches ?? 0,
                'storage_gb' => round(($tenantUsage->storage_mb ?? 0) / 1024, 2),
                // Unlimited resources (for stats display only)
                'patients' => $tenantUsage->patients ?? 0,
                'services' => $tenantUsage->services ?? 0,
                'products' => $tenantUsage->products ?? 0,
                'equipment' => $tenantUsage->equipment ?? 0,
            ];

            // Get monthly stats from monthly_stats JSON or calculate
            $currentMonth = now()->format('Y-m');
            $monthlyData = $tenantUsage->monthly_stats[$currentMonth] ?? [];

            $this->monthlyStats = [
                'appointments_this_month' => $tenantUsage->appointments_this_month ?? ($monthlyData['appointments'] ?? 0),
                'revenue_this_month' => $monthlyData['revenue'] ?? 0,
                'new_patients_this_month' => $monthlyData['new_patients'] ?? 0,
                'services_this_month' => $monthlyData['services'] ?? 0,
            ];
        } else {
            $this->usage = array_fill_keys(array_keys($this->limits), 0);
            $this->usage['patients'] = 0;
            $this->usage['services'] = 0;
            $this->usage['products'] = 0;
            $this->usage['equipment'] = 0;
            $this->monthlyStats = [
                'appointments_this_month' => 0,
                'revenue_this_month' => 0,
                'new_patients_this_month' => 0,
                'services_this_month' => 0,
            ];
        }
    }

    public function getUsageItems(): array
    {
        // Only users, branches, and storage have limits
        // Patients, services, products, equipment are unlimited
        return [
            [
                'label' => __('core::core.users'),
                'icon' => 'heroicon-o-users',
                'current' => $this->usage['users'] ?? 0,
                'limit' => $this->limits['users'] ?? 0,
                'color' => 'primary',
            ],
            [
                'label' => __('core::core.branches'),
                'icon' => 'heroicon-o-building-office-2',
                'current' => $this->usage['branches'] ?? 0,
                'limit' => $this->limits['branches'] ?? 0,
                'color' => 'success',
            ],
            [
                'label' => __('core::core.storage'),
                'icon' => 'heroicon-o-server',
                'current' => $this->usage['storage_gb'] ?? 0,
                'limit' => $this->limits['storage_gb'] ?? 0,
                'suffix' => 'GB',
                'color' => 'warning',
            ],
        ];
    }

    public function getUnlimitedItems(): array
    {
        // Resources that have no limits
        return [
            [
                'label' => __('core::core.patients'),
                'icon' => 'heroicon-o-user-group',
                'current' => $this->usage['patients'] ?? 0,
                'color' => 'info',
            ],
            [
                'label' => __('core::core.services'),
                'icon' => 'heroicon-o-heart',
                'current' => $this->usage['services'] ?? 0,
                'color' => 'success',
            ],
            [
                'label' => __('core::core.products'),
                'icon' => 'heroicon-o-cube',
                'current' => $this->usage['products'] ?? 0,
                'color' => 'primary',
            ],
            [
                'label' => __('core::core.equipment'),
                'icon' => 'heroicon-o-wrench-screwdriver',
                'current' => $this->usage['equipment'] ?? 0,
                'color' => 'warning',
            ],
        ];
    }

    public function getStatsItems(): array
    {
        return [
            [
                'label' => __('core::core.appointments_this_month'),
                'value' => $this->monthlyStats['appointments_this_month'] ?? 0,
                'icon' => 'heroicon-o-calendar',
                'color' => 'primary',
            ],
            [
                'label' => __('core::core.revenue_this_month'),
                'value' => number_format(($this->monthlyStats['revenue_this_month'] ?? 0) / 100, 2) . ' EGP',
                'icon' => 'heroicon-o-banknotes',
                'color' => 'success',
            ],
            [
                'label' => __('core::core.new_patients_this_month'),
                'value' => $this->monthlyStats['new_patients_this_month'] ?? 0,
                'icon' => 'heroicon-o-user-plus',
                'color' => 'info',
            ],
            [
                'label' => __('core::core.services_this_month'),
                'value' => $this->monthlyStats['services_this_month'] ?? 0,
                'icon' => 'heroicon-o-heart',
                'color' => 'warning',
            ],
        ];
    }

    public function calculatePercentage(int|float $current, int|float $limit): float
    {
        if ($limit <= 0) {
            return 0;
        }

        return min(100, round(($current / $limit) * 100, 1));
    }

    public function getPercentageColor(float $percentage): string
    {
        if ($percentage >= 90) {
            return 'danger';
        }

        if ($percentage >= 75) {
            return 'warning';
        }

        return 'success';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label(__('core::core.refresh_usage'))
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->refreshUsage()),
        ];
    }

    public function refreshUsage(): void
    {
        $tenantManager = app(TenantManager::class);
        $tenant = $tenantManager->current();

        if (!$tenant) {
            return;
        }

        // Calculate real counts from database
        $usersCount = DB::table('users')->where('is_active', true)->count();
        $branchesCount = DB::table('branches')->count();
        $patientsCount = DB::table('patients')->count();
        $servicesCount = DB::table('services')->count();
        $productsCount = DB::table('products')->count();
        $equipmentCount = DB::table('equipment')->count();
        $appointmentsCount = DB::table('appointments')->count();

        // Calculate this month's stats
        $startOfMonth = now()->startOfMonth();
        $appointmentsThisMonth = DB::table('appointments')
            ->where('scheduled_at', '>=', $startOfMonth)
            ->count();

        $newPatientsThisMonth = DB::table('patients')
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        // Update or create TenantUsage record
        $tenantUsage = TenantUsage::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'users' => $usersCount,
                'branches' => $branchesCount,
                'patients' => $patientsCount,
                'services' => $servicesCount,
                'products' => $productsCount,
                'equipment' => $equipmentCount,
                'appointments' => $appointmentsCount,
                'appointments_this_month' => $appointmentsThisMonth,
                'last_activity_at' => now(),
            ]
        );

        // Update monthly stats
        $currentMonth = now()->format('Y-m');
        $monthlyStats = $tenantUsage->monthly_stats ?? [];
        $monthlyStats[$currentMonth] = [
            'appointments' => $appointmentsThisMonth,
            'new_patients' => $newPatientsThisMonth,
        ];
        $tenantUsage->update(['monthly_stats' => $monthlyStats]);

        // Reload the page data
        $this->mount();

        Notification::make()
            ->title(__('core::core.usage_refreshed'))
            ->success()
            ->send();
    }
}
