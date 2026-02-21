<?php

namespace App\Filament\SuperAdmin\Resources\ModuleResource\Pages;

use App\Filament\SuperAdmin\Resources\ModuleResource;
use App\Models\Module;
use Filament\Actions;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
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
                ->modalDescription(__('This will scan the modules directory and create/update module records in the database.'))
                ->action(fn () => $this->syncModulesFromFilesystem()),

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
        if (empty($added) && empty($updated) && empty($removed) && empty($errors)) {
            $messages[] = __('No changes detected.');
        }

        Notification::make()
            ->title(__('Modules synced from filesystem'))
            ->body(implode("\n", $messages))
            ->success()
            ->send();
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
