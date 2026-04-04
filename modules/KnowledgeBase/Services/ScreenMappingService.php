<?php

namespace Modules\KnowledgeBase\Services;

use Illuminate\Support\Str;

/**
 * Service to map Filament routes and pages to screen keys.
 */
class ScreenMappingService
{
    /**
     * Map of route patterns to screen keys.
     */
    protected array $routeMap = [
        // Dashboard
        'filament.tenant.pages.dashboard' => 'dashboard',

        // Patients module
        'filament.tenant.resources.patients.index' => 'patients.index',
        'filament.tenant.resources.patients.create' => 'patients.create',
        'filament.tenant.resources.patients.edit' => 'patients.edit',
        'filament.tenant.resources.patients.view' => 'patients.view',

        // Appointments module
        'filament.tenant.resources.appointments.index' => 'appointments.index',
        'filament.tenant.resources.appointments.create' => 'appointments.create',
        'filament.tenant.resources.appointments.edit' => 'appointments.edit',
        'filament.tenant.pages.calendar' => 'appointments.calendar',

        // Services module
        'filament.tenant.resources.services.index' => 'services.index',
        'filament.tenant.resources.services.create' => 'services.create',
        'filament.tenant.resources.services.edit' => 'services.edit',
        'filament.tenant.resources.service-categories.index' => 'services.categories',

        // Invoices module
        'filament.tenant.resources.invoices.index' => 'invoices.index',
        'filament.tenant.resources.invoices.create' => 'invoices.create',
        'filament.tenant.resources.invoices.edit' => 'invoices.edit',
        'filament.tenant.resources.invoices.view' => 'invoices.view',

        // Inventory module
        'filament.tenant.resources.products.index' => 'inventory.products',
        'filament.tenant.resources.stock-adjustments.index' => 'inventory.adjustments',
        'filament.tenant.resources.purchase-orders.index' => 'inventory.purchases',

        // Staff module
        'filament.tenant.resources.staff.index' => 'staff.index',
        'filament.tenant.resources.staff.create' => 'staff.create',
        'filament.tenant.resources.staff.edit' => 'staff.edit',

        // Users module
        'filament.tenant.resources.users.index' => 'users.index',
        'filament.tenant.resources.users.create' => 'users.create',
        'filament.tenant.resources.roles.index' => 'users.roles',

        // Settings
        'filament.tenant.resources.branches.index' => 'settings.branches',
        'filament.tenant.pages.general-settings' => 'settings.general',

        // Accounting
        'filament.tenant.resources.journal-entries.index' => 'accounting.journal',
        'filament.tenant.resources.accounts.index' => 'accounting.accounts',
        'filament.tenant.pages.financial-reports' => 'accounting.reports',

        // Marketing
        'filament.tenant.resources.campaigns.index' => 'marketing.campaigns',
        'filament.tenant.resources.message-templates.index' => 'marketing.templates',

        // Reports
        'filament.tenant.pages.reports' => 'reports.index',
        'filament.tenant.pages.sales-report' => 'reports.sales',
        'filament.tenant.pages.patient-report' => 'reports.patients',
    ];

    /**
     * Get screen key from route name.
     */
    public function getScreenKeyFromRoute(string $routeName): string
    {
        // Direct match
        if (isset($this->routeMap[$routeName])) {
            return $this->routeMap[$routeName];
        }

        // Try to extract from route pattern
        return $this->extractScreenKey($routeName);
    }

    /**
     * Extract screen key from route name using patterns.
     */
    protected function extractScreenKey(string $routeName): string
    {
        // Remove panel prefix (filament.tenant., filament.super-admin., etc.)
        $routeName = preg_replace('/^filament\.[a-z-]+\./', '', $routeName);

        // Handle resources
        if (str_starts_with($routeName, 'resources.')) {
            $parts = explode('.', $routeName);
            // resources.{resource}.{action}
            if (count($parts) >= 3) {
                $resource = Str::singular($parts[1]);
                $action = $parts[2] ?? 'index';
                return "{$resource}.{$action}";
            }
        }

        // Handle pages
        if (str_starts_with($routeName, 'pages.')) {
            $pageName = Str::after($routeName, 'pages.');
            return "page.{$pageName}";
        }

        // Fallback: use route name as-is
        return str_replace('.', '_', $routeName);
    }

    /**
     * Get screen key from current request.
     */
    public function getCurrentScreenKey(): ?string
    {
        $route = request()->route();

        if (!$route) {
            return null;
        }

        $routeName = $route->getName();

        if (!$routeName) {
            return null;
        }

        return $this->getScreenKeyFromRoute($routeName);
    }

    /**
     * Get panel from current request.
     */
    public function getCurrentPanel(): string
    {
        $route = request()->route();

        if (!$route) {
            return 'tenant';
        }

        $routeName = $route->getName() ?? '';

        if (str_contains($routeName, 'super-admin')) {
            return 'super-admin';
        }

        if (str_contains($routeName, 'admin') && !str_contains($routeName, 'tenant')) {
            return 'admin';
        }

        return 'tenant';
    }

    /**
     * Get all defined screen keys.
     */
    public function getAllScreenKeys(): array
    {
        return array_unique(array_values($this->routeMap));
    }

    /**
     * Add custom route mapping.
     */
    public function addRouteMapping(string $routeName, string $screenKey): void
    {
        $this->routeMap[$routeName] = $screenKey;
    }

    /**
     * Get human-readable label for a screen key.
     */
    public function getScreenLabel(string $screenKey, string $locale = 'en'): string
    {
        // Try to get from translations
        $translationKey = "knowledgebase::screens.{$screenKey}";
        $translated = __($translationKey);

        if ($translated !== $translationKey) {
            return $translated;
        }

        // Generate from screen key
        $parts = explode('.', $screenKey);
        $parts = array_map(fn ($p) => Str::headline($p), $parts);

        return implode(' - ', $parts);
    }

    /**
     * Get screen keys grouped by module.
     */
    public function getScreenKeysGrouped(): array
    {
        $grouped = [];

        foreach ($this->routeMap as $routeName => $screenKey) {
            $module = explode('.', $screenKey)[0];
            $grouped[$module][] = [
                'route' => $routeName,
                'key' => $screenKey,
                'label' => $this->getScreenLabel($screenKey),
            ];
        }

        ksort($grouped);

        return $grouped;
    }
}
