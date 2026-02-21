<?php

namespace Modules\Core\Resources\ModuleManagementResource\Pages;

use Modules\Core\Resources\ModuleManagementResource;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Facades\Module;

class ListModules extends Page
{
    protected static string $resource = ModuleManagementResource::class;

    protected static string $view = 'filament.pages.list-modules';

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
        $modules = [];

        // Primary: Use nwidart/laravel-modules
        if (class_exists(\Nwidart\Modules\Facades\Module::class)) {
            try {
                $allModules = Module::all();

                foreach ($allModules as $module) {
                    $moduleJson = $this->getModuleJson($module->getName());

                    $modules[] = [
                        'code' => $module->getLowerName(),
                        'name' => $module->getName(),
                        'description' => $moduleJson['description'] ?? '',
                        'version' => $moduleJson['version'] ?? '1.0.0',
                        'enabled' => $module->isEnabled(),
                        'author' => $moduleJson['author'] ?? 'XLinic',
                        'category' => $moduleJson['category'] ?? 'general',
                        'icon' => $moduleJson['icon'] ?? 'heroicon-o-puzzle-piece',
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
                    $statusesPath = base_path('modules_statuses.json');
                    $statuses = File::exists($statusesPath)
                        ? json_decode(File::get($statusesPath), true) ?? []
                        : [];

                    $modules[] = [
                        'code' => strtolower($moduleName),
                        'name' => $moduleJson['name'] ?? $moduleName,
                        'description' => $moduleJson['description'] ?? '',
                        'version' => $moduleJson['version'] ?? '1.0.0',
                        'enabled' => $statuses[$moduleName] ?? false,
                        'author' => $moduleJson['author'] ?? 'XLinic',
                        'category' => $moduleJson['category'] ?? 'general',
                        'icon' => $moduleJson['icon'] ?? 'heroicon-o-puzzle-piece',
                    ];
                }
            }

            // Sort by name
            usort($modules, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        }

        return $modules;
    }

    protected function getModuleJson(string $moduleName): array
    {
        $moduleJsonPath = base_path("modules/{$moduleName}/module.json");

        if (File::exists($moduleJsonPath)) {
            return json_decode(File::get($moduleJsonPath), true) ?? [];
        }

        return [];
    }

    public function toggleModule(string $code): void
    {
        $moduleName = ucfirst($code);

        try {
            // Use nwidart/laravel-modules
            if (class_exists(\Nwidart\Modules\Facades\Module::class)) {
                $module = Module::find($moduleName);

                if (!$module) {
                    Notification::make()
                        ->title(__('Module not found'))
                        ->danger()
                        ->send();
                    return;
                }

                if ($module->isEnabled()) {
                    $module->disable();
                    Notification::make()
                        ->title(__('Module disabled'))
                        ->body(__(':module has been disabled.', ['module' => $moduleName]))
                        ->success()
                        ->send();
                } else {
                    $module->enable();
                    Notification::make()
                        ->title(__('Module enabled'))
                        ->body(__(':module has been enabled.', ['module' => $moduleName]))
                        ->success()
                        ->send();
                }

                return;
            }

            // Fallback: Update modules_statuses.json directly
            $statusesPath = base_path('modules_statuses.json');
            $statuses = File::exists($statusesPath)
                ? json_decode(File::get($statusesPath), true) ?? []
                : [];

            $statuses[$moduleName] = !($statuses[$moduleName] ?? false);
            File::put($statusesPath, json_encode($statuses, JSON_PRETTY_PRINT));

            $status = $statuses[$moduleName] ? __('enabled') : __('disabled');
            Notification::make()
                ->title(__('Module :status', ['status' => $status]))
                ->success()
                ->send();

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

            // Re-scan modules directory and update statuses
            $modulesPath = base_path('modules');
            $statusesPath = base_path('modules_statuses.json');

            $currentStatuses = File::exists($statusesPath)
                ? json_decode(File::get($statusesPath), true) ?? []
                : [];

            $newStatuses = [];
            $added = [];
            $removed = [];

            // Scan existing modules
            if (File::isDirectory($modulesPath)) {
                $directories = File::directories($modulesPath);

                foreach ($directories as $directory) {
                    $moduleName = basename($directory);
                    $moduleJsonPath = $directory . '/module.json';

                    if (File::exists($moduleJsonPath)) {
                        // Keep existing status or default to true for new modules
                        if (isset($currentStatuses[$moduleName])) {
                            $newStatuses[$moduleName] = $currentStatuses[$moduleName];
                        } else {
                            $newStatuses[$moduleName] = true;
                            $added[] = $moduleName;
                        }
                    }
                }
            }

            // Find removed modules
            foreach ($currentStatuses as $moduleName => $status) {
                if (!isset($newStatuses[$moduleName])) {
                    $removed[] = $moduleName;
                }
            }

            // Save updated statuses
            File::put($statusesPath, json_encode($newStatuses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

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
