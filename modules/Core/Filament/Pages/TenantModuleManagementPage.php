<?php

namespace Modules\Core\Filament\Pages;

use Filament\Pages\Page;
use Modules\Core\Models\TenantModule;
use Illuminate\Support\Facades\Cache;

class TenantModuleManagementPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static string $view = 'core::filament.pages.tenant-module-management';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 55;

    public static function getNavigationLabel(): string
    {
        return __('core::core.modules_label');
    }

    public function getTitle(): string
    {
        return __('core::core.module_management');
    }

    public function getHeading(): string
    {
        return __('core::core.module_management');
    }

    public function getSubheading(): ?string
    {
        return __('core::core.module_management_description');
    }

    /**
     * Get all available modules with their status.
     */
    public function getModules(): array
    {
        // Define all modules in the system
        $allModules = $this->getAllModuleDefinitions();

        // Get active modules for the current tenant
        $activeModules = TenantModule::query()
            ->active()
            ->valid()
            ->pluck('module_code')
            ->toArray();

        // Get modules included in subscription plan
        $planModules = $this->getPlanModules();

        // Build the module list with status
        $modules = [];
        foreach ($allModules as $code => $module) {
            $isActive = in_array($code, $activeModules);
            $isIncludedInPlan = in_array($code, $planModules) || in_array('*', $planModules);

            $modules[] = [
                'code' => $code,
                'name' => $module['name'],
                'description' => $module['description'],
                'icon' => $module['icon'],
                'category' => $module['category'],
                'is_active' => $isActive,
                'is_included_in_plan' => $isIncludedInPlan,
                'is_core' => $module['is_core'] ?? false,
                'dependencies' => $module['dependencies'] ?? [],
            ];
        }

        // Group by category
        return collect($modules)
            ->groupBy('category')
            ->toArray();
    }

    /**
     * Get modules included in the current subscription plan.
     */
    protected function getPlanModules(): array
    {
        // Try to get from tenant's subscription
        try {
            $tenant = tenant();
            if ($tenant && $tenant->subscription) {
                $plan = $tenant->subscription->plan;
                if ($plan && isset($plan->features['modules'])) {
                    return $plan->features['modules'];
                }
            }
        } catch (\Exception $e) {
            // Ignore errors
        }

        // Default: all modules available
        return ['*'];
    }

    /**
     * Get all module definitions.
     */
    protected function getAllModuleDefinitions(): array
    {
        return [
            // Core Modules (always active)
            'core' => [
                'name' => __('core::core.module_info.core'),
                'description' => __('core::core.module_info.core_description'),
                'icon' => 'heroicon-o-cog-6-tooth',
                'category' => __('core::core.module_categories.core'),
                'is_core' => true,
            ],
            'auth' => [
                'name' => __('core::core.module_info.auth'),
                'description' => __('core::core.module_info.auth_description'),
                'icon' => 'heroicon-o-shield-check',
                'category' => __('core::core.module_categories.core'),
                'is_core' => true,
            ],

            // CRM Modules
            'patients' => [
                'name' => __('core::core.module_info.patients'),
                'description' => __('core::core.module_info.patients_description'),
                'icon' => 'heroicon-o-users',
                'category' => __('core::core.module_categories.crm'),
            ],

            // Operations Modules
            'treatments' => [
                'name' => __('core::core.module_info.treatments'),
                'description' => __('core::core.module_info.treatments_description'),
                'icon' => 'heroicon-o-beaker',
                'category' => __('core::core.module_categories.operations'),
            ],
            'booking' => [
                'name' => __('core::core.module_info.booking'),
                'description' => __('core::core.module_info.booking_description'),
                'icon' => 'heroicon-o-calendar',
                'category' => __('core::core.module_categories.operations'),
                'dependencies' => ['patients', 'treatments'],
            ],
            'equipment' => [
                'name' => __('core::core.module_info.equipment'),
                'description' => __('core::core.module_info.equipment_description'),
                'icon' => 'heroicon-o-wrench-screwdriver',
                'category' => __('core::core.module_categories.operations'),
            ],

            // Sales Modules
            'packages' => [
                'name' => __('core::core.module_info.packages'),
                'description' => __('core::core.module_info.packages_description'),
                'icon' => 'heroicon-o-cube',
                'category' => __('core::core.module_categories.sales'),
                'dependencies' => ['patients', 'treatments', 'billing'],
            ],
            'giftcards' => [
                'name' => __('core::core.module_info.giftcards'),
                'description' => __('core::core.module_info.giftcards_description'),
                'icon' => 'heroicon-o-gift',
                'category' => __('core::core.module_categories.sales'),
                'dependencies' => ['billing'],
            ],
            'memberships' => [
                'name' => __('core::core.module_info.memberships'),
                'description' => __('core::core.module_info.memberships_description'),
                'icon' => 'heroicon-o-star',
                'category' => __('core::core.module_categories.sales'),
                'dependencies' => ['patients', 'billing'],
            ],

            // Financial Modules
            'billing' => [
                'name' => __('core::core.module_info.billing'),
                'description' => __('core::core.module_info.billing_description'),
                'icon' => 'heroicon-o-banknotes',
                'category' => __('core::core.module_categories.financial'),
                'dependencies' => ['patients'],
            ],
            'accounting' => [
                'name' => __('core::core.module_info.accounting'),
                'description' => __('core::core.module_info.accounting_description'),
                'icon' => 'heroicon-o-calculator',
                'category' => __('core::core.module_categories.financial'),
                'dependencies' => ['billing'],
            ],

            // Inventory & HR Modules
            'inventory' => [
                'name' => __('core::core.module_info.inventory'),
                'description' => __('core::core.module_info.inventory_description'),
                'icon' => 'heroicon-o-archive-box',
                'category' => __('core::core.module_categories.inventory'),
            ],
            'staff' => [
                'name' => __('core::core.module_info.staff'),
                'description' => __('core::core.module_info.staff_description'),
                'icon' => 'heroicon-o-user-group',
                'category' => __('core::core.module_categories.hr'),
            ],
            'payroll' => [
                'name' => __('core::core.module_info.payroll'),
                'description' => __('core::core.module_info.payroll_description'),
                'icon' => 'heroicon-o-currency-dollar',
                'category' => __('core::core.module_categories.hr'),
                'dependencies' => ['staff', 'accounting'],
            ],

            // Marketing Modules
            'marketing' => [
                'name' => __('core::core.module_info.marketing'),
                'description' => __('core::core.module_info.marketing_description'),
                'icon' => 'heroicon-o-megaphone',
                'category' => __('core::core.module_categories.marketing'),
                'dependencies' => ['patients'],
            ],
            'loyalty' => [
                'name' => __('core::core.module_info.loyalty'),
                'description' => __('core::core.module_info.loyalty_description'),
                'icon' => 'heroicon-o-heart',
                'category' => __('core::core.module_categories.marketing'),
                'dependencies' => ['patients', 'billing'],
            ],

            // Reporting
            'reporting' => [
                'name' => __('core::core.module_info.reporting'),
                'description' => __('core::core.module_info.reporting_description'),
                'icon' => 'heroicon-o-chart-bar',
                'category' => __('core::core.module_categories.reporting'),
            ],

            // External
            'patient_portal' => [
                'name' => __('core::core.module_info.patient_portal'),
                'description' => __('core::core.module_info.patient_portal_description'),
                'icon' => 'heroicon-o-device-phone-mobile',
                'category' => __('core::core.module_categories.external'),
                'dependencies' => ['patients', 'booking'],
            ],
            'api' => [
                'name' => __('core::core.module_info.api'),
                'description' => __('core::core.module_info.api_description'),
                'icon' => 'heroicon-o-code-bracket',
                'category' => __('core::core.module_categories.external'),
            ],
        ];
    }

    /**
     * Get count of active modules.
     */
    public function getActiveModulesCount(): int
    {
        return TenantModule::query()->active()->valid()->count();
    }

    /**
     * Get total modules count.
     */
    public function getTotalModulesCount(): int
    {
        return count($this->getAllModuleDefinitions());
    }
}
