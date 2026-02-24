<?php

namespace Modules\Core\Filament\Resources\ModuleManagementResource\Pages;

use Modules\Core\Filament\Resources\ModuleManagementResource;
use Modules\Core\Models\TenantModule;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Facades\Module;
use XLinic\Framework\Core\Tenancy\TenantManager;

class ListModules extends Page
{
    protected static string $resource = ModuleManagementResource::class;

    protected static string $view = 'filament.pages.list-modules';

    /**
     * Core modules that cannot be disabled.
     */
    protected const CORE_MODULES = ['core', 'auth'];

    public function getTitle(): string
    {
        return __('Modules');
    }

    public function getHeading(): string
    {
        return __('Module Management');
    }

    public function getModules(): array
    {
        // Ensure modules are synced to database for current tenant
        $this->syncModulesToDatabase();

        $modules = [];
        $tenantStatuses = $this->getTenantModuleStatuses();

        // Primary: Use nwidart/laravel-modules for module discovery
        if (class_exists(\Nwidart\Modules\Facades\Module::class)) {
            try {
                $allModules = Module::all();

                foreach ($allModules as $module) {
                    $moduleJson = $this->getModuleJson($module->getName());
                    $code = $module->getLowerName();

                    // Core modules are always enabled
                    $isEnabled = in_array($code, self::CORE_MODULES)
                        ? true
                        : ($tenantStatuses[$code] ?? true);

                    $modules[] = [
                        'code' => $code,
                        'name' => $module->getName(),
                        'description' => $this->getTranslatedValue($moduleJson['description'] ?? ''),
                        'version' => $moduleJson['version'] ?? '1.0.0',
                        'enabled' => $isEnabled,
                        'author' => $this->getTranslatedValue($moduleJson['author'] ?? 'XLinic'),
                        'category' => $moduleJson['category'] ?? 'general',
                        'icon' => $moduleJson['icon'] ?? 'heroicon-o-puzzle-piece',
                        'is_core' => in_array($code, self::CORE_MODULES),
                    ];
                }

                // Sort by name
                usort($modules, fn($a, $b) => strcasecmp($a['name'], $b['name']));

                return $modules;
            } catch (\Exception $e) {
                // Fall through to fallback
            }
        }

        // Fallback: Scan modules directory directly
        $modulesPath = base_path('modules');
        if (File::isDirectory($modulesPath)) {
            $directories = File::directories($modulesPath);

            foreach ($directories as $directory) {
                $moduleName = basename($directory);
                $moduleJsonPath = $directory . '/module.json';

                if (File::exists($moduleJsonPath)) {
                    $moduleJson = json_decode(File::get($moduleJsonPath), true) ?? [];
                    $code = strtolower($moduleName);

                    // Core modules are always enabled
                    $isEnabled = in_array($code, self::CORE_MODULES)
                        ? true
                        : ($tenantStatuses[$code] ?? true);

                    $modules[] = [
                        'code' => $code,
                        'name' => $moduleJson['name'] ?? $moduleName,
                        'description' => $this->getTranslatedValue($moduleJson['description'] ?? ''),
                        'version' => $moduleJson['version'] ?? '1.0.0',
                        'enabled' => $isEnabled,
                        'author' => $this->getTranslatedValue($moduleJson['author'] ?? 'XLinic'),
                        'category' => $moduleJson['category'] ?? 'general',
                        'icon' => $moduleJson['icon'] ?? 'heroicon-o-puzzle-piece',
                        'is_core' => in_array($code, self::CORE_MODULES),
                    ];
                }
            }

            // Sort by name
            usort($modules, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        }

        return $modules;
    }

    /**
     * Get module statuses for the current tenant from the database.
     */
    protected function getTenantModuleStatuses(): array
    {
        $tenant = app(TenantManager::class)->current();

        if (!$tenant) {
            return [];
        }

        return TenantModule::where('tenant_id', $tenant->id)
            ->pluck('is_active', 'module_code')
            ->toArray();
    }

    /**
     * Sync all discovered modules to the database for the current tenant.
     * Creates TenantModule records for any modules that don't have one.
     */
    protected function syncModulesToDatabase(): void
    {
        $tenant = app(TenantManager::class)->current();

        if (!$tenant) {
            return;
        }

        $discoveredModules = $this->discoverModuleCodes();
        $existingModules = TenantModule::where('tenant_id', $tenant->id)
            ->pluck('module_code')
            ->toArray();

        foreach ($discoveredModules as $moduleCode) {
            if (!in_array($moduleCode, $existingModules)) {
                TenantModule::create([
                    'tenant_id' => $tenant->id,
                    'module_code' => $moduleCode,
                    'is_active' => true, // Default new modules to active
                    'activated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Discover all module codes from the filesystem.
     */
    protected function discoverModuleCodes(): array
    {
        $codes = [];

        // Try nwidart first
        if (class_exists(\Nwidart\Modules\Facades\Module::class)) {
            try {
                foreach (Module::all() as $module) {
                    $codes[] = $module->getLowerName();
                }
                return $codes;
            } catch (\Exception $e) {
                // Fall through
            }
        }

        // Fallback: scan directory
        $modulesPath = base_path('modules');
        if (File::isDirectory($modulesPath)) {
            foreach (File::directories($modulesPath) as $directory) {
                $moduleName = basename($directory);
                if (File::exists($directory . '/module.json')) {
                    $codes[] = strtolower($moduleName);
                }
            }
        }

        return $codes;
    }

    protected function getModuleJson(string $moduleName): array
    {
        $moduleJsonPath = base_path("modules/{$moduleName}/module.json");

        if (File::exists($moduleJsonPath)) {
            return json_decode(File::get($moduleJsonPath), true) ?? [];
        }

        return [];
    }

    /**
     * Get translated value from a string or array.
     * If array, returns the value for current locale or fallback to 'en'.
     */
    protected function getTranslatedValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            $locale = app()->getLocale();
            return $value[$locale] ?? $value['en'] ?? reset($value) ?: '';
        }

        return '';
    }

    public function toggleModule(string $code): void
    {
        $moduleName = ucfirst($code);

        // Prevent disabling core modules
        if (in_array($code, self::CORE_MODULES)) {
            Notification::make()
                ->title(__('Cannot disable core module'))
                ->body(__(':module is a core module and cannot be disabled.', ['module' => $moduleName]))
                ->warning()
                ->send();
            return;
        }

        try {
            $tenant = app(TenantManager::class)->current();

            if (!$tenant) {
                Notification::make()
                    ->title(__('No tenant context'))
                    ->body(__('Unable to determine current tenant.'))
                    ->danger()
                    ->send();
                return;
            }

            // Find or create the TenantModule record
            $tenantModule = TenantModule::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'module_code' => $code,
                ],
                [
                    'is_active' => true,
                    'activated_at' => now(),
                ]
            );

            // Toggle the status using model methods
            if ($tenantModule->is_active) {
                $tenantModule->deactivate();
                Notification::make()
                    ->title(__('Module disabled'))
                    ->body(__(':module has been disabled.', ['module' => $moduleName]))
                    ->success()
                    ->send();
            } else {
                $tenantModule->activate(auth()->id());
                Notification::make()
                    ->title(__('Module enabled'))
                    ->body(__(':module has been enabled.', ['module' => $moduleName]))
                    ->success()
                    ->send();
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title(__('Error'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function rescanModules(): void
    {
        try {
            // Clear all caches
            Artisan::call('cache:clear');
            Artisan::call('config:clear');

            $tenant = app(TenantManager::class)->current();

            if (!$tenant) {
                Notification::make()
                    ->title(__('No tenant context'))
                    ->body(__('Unable to determine current tenant.'))
                    ->danger()
                    ->send();
                return;
            }

            // Get currently discovered modules
            $discoveredModules = $this->discoverModuleCodes();

            // Get existing database records
            $existingRecords = TenantModule::where('tenant_id', $tenant->id)
                ->pluck('module_code')
                ->toArray();

            $added = [];
            $removed = [];

            // Add new modules to database
            foreach ($discoveredModules as $moduleCode) {
                if (!in_array($moduleCode, $existingRecords)) {
                    TenantModule::create([
                        'tenant_id' => $tenant->id,
                        'module_code' => $moduleCode,
                        'is_active' => true,
                        'activated_at' => now(),
                    ]);
                    $added[] = ucfirst($moduleCode);
                }
            }

            // Find modules that no longer exist (for informational purposes)
            foreach ($existingRecords as $moduleCode) {
                if (!in_array($moduleCode, $discoveredModules)) {
                    $removed[] = ucfirst($moduleCode);
                    // Optionally clean up orphaned records
                    TenantModule::where('tenant_id', $tenant->id)
                        ->where('module_code', $moduleCode)
                        ->delete();
                }
            }

            // Build notification message
            $message = [];
            if (!empty($added)) {
                $message[] = __('Added: :modules', ['modules' => implode(', ', $added)]);
            }
            if (!empty($removed)) {
                $message[] = __('Removed: :modules', ['modules' => implode(', ', $removed)]);
            }
            if (empty($message)) {
                $message[] = __('No changes detected.');
            }

            Notification::make()
                ->title(__('Modules rescanned'))
                ->body(implode("\n", $message))
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title(__('Error rescanning modules'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('rescan')
                ->label(__('Rescan Modules'))
                ->icon('heroicon-o-magnifying-glass')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('Rescan Modules'))
                ->modalDescription(__('This will scan the modules directory and update the available modules list. New modules will be auto-enabled.'))
                ->action(fn () => $this->rescanModules()),

            Actions\Action::make('refresh')
                ->label(__('Refresh'))
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->dispatch('$refresh')),
        ];
    }
}
