<?php

namespace App\Filament\SuperAdmin\Resources\ModuleResource\Pages;

use App\Filament\SuperAdmin\Resources\ModuleResource;
use App\Models\Module;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantModule;
use Filament\Actions;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ListModules extends BaseListRecords
{
    use Translatable;

    protected static string $resource = ModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            \Filament\Actions\LocaleSwitcher::make(),

            Actions\Action::make('syncFromFilesystem')
                ->label(__('Sync from Filesystem'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('Sync Modules from Filesystem'))
                ->modalDescription(__('This will scan the modules directory and create/update module records in the database. It will also create tenant module records for all active tenants.'))
                ->action(fn () => $this->syncModulesFromFilesystem()),

            Actions\Action::make('syncToAllTenants')
                ->label(__('Sync to All Tenants'))
                ->icon('heroicon-o-users')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading(__('Sync Modules to All Tenants'))
                ->modalDescription(__('This will create tenant module records for all active tenants based on the current module registry.'))
                ->action(fn () => $this->syncModulesToAllTenants()),

            Actions\CreateAction::make()
                ->label('Register Module'),
        ];
    }

    protected function syncModulesFromFilesystem(): void
    {
        $modulesPath = base_path('modules');
        $added = [];
        $updated = [];
        $removed = [];
        $errors = [];
        $filesystemCodes = [];

        if (!File::isDirectory($modulesPath)) {
            Notification::make()
                ->title(__('Error'))
                ->body(__('Modules directory not found'))
                ->danger()
                ->send();
            return;
        }

        $directories = File::directories($modulesPath);

        // First pass: collect all module codes from filesystem and sync
        foreach ($directories as $directory) {
            $moduleName = basename($directory);
            $moduleJsonPath = $directory . '/module.json';

            if (!File::exists($moduleJsonPath)) {
                continue;
            }

            try {
                $json = json_decode(File::get($moduleJsonPath), true);

                if (!$json) {
                    $errors[] = "{$moduleName}: Invalid JSON";
                    continue;
                }

                $code = strtolower($json['alias'] ?? $json['name'] ?? $moduleName);
                $filesystemCodes[] = $code;

                // Map category from module.json to database categories
                $categoryMap = [
                    'finance' => 'financial',
                    'core' => 'core',
                    'operations' => 'operations',
                    'sales' => 'sales',
                    'marketing' => 'marketing',
                    'advanced' => 'advanced',
                ];
                $category = $categoryMap[$json['category'] ?? 'operations'] ?? 'operations';

                // Check if module exists
                $existingModule = Module::where('code', $code)->first();

                $data = [
                    'code' => $code,
                    'name' => ['en' => $json['name'] ?? $moduleName, 'ar' => $json['name'] ?? $moduleName],
                    'description' => [
                        'en' => $json['description'] ?? '',
                        'ar' => $json['description'] ?? ''
                    ],
                    'category' => $category,
                    'icon_emoji' => $this->mapIconToEmoji($json['icon'] ?? null),
                    'dependencies' => $json['dependencies'] ?? [],
                    'settings_schema' => $json['settings'] ?? [],
                    'sort_order' => $json['priority'] ?? 50,
                    'is_active' => $json['active'] ?? true,
                    'is_core' => in_array($code, ['core', 'auth']),
                    'tier' => $this->determineTier($code, $json),
                ];

                if ($existingModule) {
                    // Update existing - but preserve some fields
                    $existingModule->update([
                        'description' => $data['description'],
                        'category' => $data['category'],
                        'dependencies' => $data['dependencies'],
                        'settings_schema' => $data['settings_schema'],
                        'sort_order' => $data['sort_order'],
                    ]);
                    $updated[] = $moduleName;
                } else {
                    // Create new using DB facade to avoid model issues
                    \DB::connection('central')->table('public.modules')->insert([
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'code' => $data['code'],
                        'name' => json_encode($data['name']),
                        'description' => json_encode($data['description']),
                        'category' => $data['category'],
                        'icon_emoji' => $data['icon_emoji'],
                        'tier' => $data['tier'],
                        'is_core' => $data['is_core'],
                        'is_active' => $data['is_active'],
                        'is_beta' => false,
                        'sort_order' => $data['sort_order'],
                        'dependencies' => json_encode($data['dependencies']),
                        'settings_schema' => json_encode($data['settings_schema']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $added[] = $moduleName;
                }

            } catch (\Exception $e) {
                $errors[] = "{$moduleName}: " . $e->getMessage();
            }
        }

        // Second pass: remove modules that no longer exist in filesystem
        if (!empty($filesystemCodes)) {
            $orphanedModules = Module::whereNotIn('code', $filesystemCodes)->get();

            foreach ($orphanedModules as $orphan) {
                $removed[] = $orphan->code;
                $orphan->forceDelete();
            }
        }

        // Sync to all tenants if new modules were added
        $tenantSyncResult = null;
        if (!empty($added)) {
            $tenantSyncResult = $this->syncModulesToAllTenants(false);
        }

        // Build notification
        $messages = [];
        if (!empty($added)) {
            $messages[] = __('Added: :modules', ['modules' => implode(', ', $added)]);
        }
        if (!empty($updated)) {
            $messages[] = __('Updated: :modules', ['modules' => implode(', ', $updated)]);
        }
        if (!empty($removed)) {
            $messages[] = __('Removed: :modules', ['modules' => implode(', ', $removed)]);
        }
        if (!empty($errors)) {
            $messages[] = __('Errors: :errors', ['errors' => implode('; ', $errors)]);
        }
        if ($tenantSyncResult) {
            $messages[] = $tenantSyncResult;
        }
        if (empty($added) && empty($updated) && empty($removed) && empty($errors)) {
            $messages[] = __('No changes detected.');
        }

        Notification::make()
            ->title(__('Modules synced from filesystem'))
            ->body(implode("\n", $messages))
            ->success()
            ->send();
    }

    /**
     * Sync modules to all active tenants.
     * Creates TenantModule records for modules that don't have one.
     */
    protected function syncModulesToAllTenants(bool $showNotification = true): ?string
    {
        try {
            // Get all module codes from central registry
            $moduleCodes = Module::pluck('code')->toArray();

            if (empty($moduleCodes)) {
                if ($showNotification) {
                    Notification::make()
                        ->title(__('No modules found'))
                        ->body(__('No modules in the registry to sync.'))
                        ->warning()
                        ->send();
                }
                return null;
            }

            // Get all active tenants
            $tenants = Tenant::where('status', 'active')->get();
            $tenantsUpdated = 0;
            $recordsCreated = 0;

            foreach ($tenants as $tenant) {
                try {
                    // Switch to tenant's database/schema
                    $tenantConnection = $this->getTenantConnection($tenant);

                    if (!$tenantConnection) {
                        continue;
                    }

                    // Get existing module codes for this tenant
                    $existingModules = DB::connection($tenantConnection)
                        ->table('tenant_modules')
                        ->where('tenant_id', $tenant->id)
                        ->pluck('module_code')
                        ->toArray();

                    // Find modules that need to be created
                    $missingModules = array_diff($moduleCodes, $existingModules);

                    if (!empty($missingModules)) {
                        $tenantsUpdated++;

                        foreach ($missingModules as $moduleCode) {
                            DB::connection($tenantConnection)
                                ->table('tenant_modules')
                                ->insert([
                                    'id' => (string) \Illuminate\Support\Str::uuid(),
                                    'tenant_id' => $tenant->id,
                                    'module_code' => $moduleCode,
                                    'is_active' => true,
                                    'activated_at' => now(),
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            $recordsCreated++;
                        }
                    }
                } catch (\Exception $e) {
                    // Log error but continue with other tenants
                    \Log::warning("Failed to sync modules to tenant {$tenant->id}: " . $e->getMessage());
                }
            }

            $message = __('Synced to :tenants tenants, created :records records', [
                'tenants' => $tenantsUpdated,
                'records' => $recordsCreated,
            ]);

            if ($showNotification) {
                Notification::make()
                    ->title(__('Modules synced to tenants'))
                    ->body($message)
                    ->success()
                    ->send();
            }

            return $message;

        } catch (\Exception $e) {
            $errorMessage = __('Error syncing to tenants: :error', ['error' => $e->getMessage()]);

            if ($showNotification) {
                Notification::make()
                    ->title(__('Error'))
                    ->body($errorMessage)
                    ->danger()
                    ->send();
            }

            return $errorMessage;
        }
    }

    /**
     * Get the database connection name for a tenant.
     */
    protected function getTenantConnection(Tenant $tenant): ?string
    {
        // If using schema-based multi-tenancy
        if ($tenant->database_name) {
            // Configure a dynamic connection for this tenant
            $connectionName = 'tenant_' . $tenant->id;

            config([
                "database.connections.{$connectionName}" => [
                    'driver' => 'pgsql',
                    'host' => $tenant->database_host ?? config('database.connections.pgsql.host'),
                    'port' => $tenant->database_port ?? config('database.connections.pgsql.port'),
                    'database' => $tenant->database_name,
                    'username' => $tenant->database_username ?? config('database.connections.pgsql.username'),
                    'password' => $tenant->database_password ?? config('database.connections.pgsql.password'),
                    'charset' => 'utf8',
                    'prefix' => '',
                    'schema' => 'public',
                ],
            ]);

            return $connectionName;
        }

        // If using same database with schema separation
        // Just use the default tenant connection
        return 'tenant';
    }

    protected function mapIconToEmoji(?string $icon): string
    {
        // Map heroicon names to emojis
        $iconMap = [
            'heroicon-o-puzzle-piece' => '🧩',
            'heroicon-o-document-text' => '📄',
            'heroicon-o-banknotes' => '💵',
            'heroicon-o-sparkles' => '✨',
            'heroicon-o-calendar' => '📅',
            'heroicon-o-users' => '👥',
            'heroicon-o-user' => '👤',
            'heroicon-o-building-office' => '🏢',
            'heroicon-o-cog-6-tooth' => '⚙️',
            'heroicon-o-chart-bar' => '📊',
            'heroicon-o-gift' => '🎁',
            'heroicon-o-credit-card' => '💳',
            'heroicon-o-cube' => '📦',
            'heroicon-o-truck' => '🚚',
            'heroicon-o-megaphone' => '📢',
            'heroicon-o-shopping-cart' => '🛒',
            'heroicon-o-receipt-percent' => '🧾',
            'heroicon-o-clipboard-document-list' => '📋',
            'heroicon-o-identification' => '🪪',
            'heroicon-o-heart' => '❤️',
            'heroicon-o-shield-check' => '🛡️',
        ];

        return $iconMap[$icon] ?? '📦';
    }

    protected function determineTier(string $code, array $json): string
    {
        // Core modules are free
        if (in_array($code, ['core', 'auth'])) {
            return 'free';
        }

        // Check for tier in module.json
        if (isset($json['tier'])) {
            return $json['tier'];
        }

        // Default based on category
        $categoryTiers = [
            'core' => 'free',
            'operations' => 'starter',
            'finance' => 'starter',
            'financial' => 'starter',
            'sales' => 'professional',
            'marketing' => 'professional',
            'advanced' => 'enterprise',
        ];

        return $categoryTiers[$json['category'] ?? 'operations'] ?? 'starter';
    }
}
