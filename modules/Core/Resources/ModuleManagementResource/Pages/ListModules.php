<?php

namespace Modules\Core\Resources\ModuleManagementResource\Pages;

use Modules\Core\Resources\ModuleManagementResource;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;

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
        // Return empty array if module classes don't exist
        if (!class_exists(\XLinic\Framework\Core\Module\ModuleRegistry::class)) {
            return [];
        }

        try {
            $moduleRegistry = app(\XLinic\Framework\Core\Module\ModuleRegistry::class);
            $modules = $moduleRegistry->getAllModules();

            return collect($modules)->map(function ($module) {
                return [
                    'code' => $module->getCode(),
                    'name' => $module->getName(),
                    'description' => $module->getDescription(),
                    'version' => $module->getVersion(),
                    'enabled' => $module->isEnabled(),
                    'author' => $module->getAuthor(),
                ];
            })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function toggleModule(string $code): void
    {
        if (!class_exists(\XLinic\Framework\Core\Module\ModuleManager::class)) {
            Notification::make()
                ->title(__('Module manager not available'))
                ->danger()
                ->send();
            return;
        }

        try {
            $moduleManager = app(\XLinic\Framework\Core\Module\ModuleManager::class);
            $moduleRegistry = app(\XLinic\Framework\Core\Module\ModuleRegistry::class);
            $module = $moduleRegistry->getModule($code);

            if (!$module) {
                Notification::make()
                    ->title(__('Module not found'))
                    ->danger()
                    ->send();
                return;
            }

            if ($module->isEnabled()) {
                $moduleManager->disable($code);
                Notification::make()
                    ->title(__('Module disabled'))
                    ->success()
                    ->send();
            } else {
                $moduleManager->enable($code);
                Notification::make()
                    ->title(__('Module enabled'))
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label(__('Refresh'))
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->dispatch('$refresh')),
        ];
    }
}
