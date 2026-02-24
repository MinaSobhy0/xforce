<?php

namespace Modules\Core\Resources\ModuleManagementResource\Pages;

use Modules\Core\Resources\ModuleManagementResource;
use Modules\Core\Models\TenantModule;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use XLinic\Framework\Core\Module\ModuleManager;
use XLinic\Framework\Core\Module\ModuleRegistry;
use XLinic\Framework\Core\Tenancy\TenantManager;

class ViewModule extends BaseViewRecord
{
    protected static string $resource = ModuleManagementResource::class;

    /**
     * Core modules that cannot be disabled.
     */
    protected const CORE_MODULES = ['core', 'auth'];

    public function getRecord(): \Illuminate\Database\Eloquent\Model
    {
        $moduleCode = $this->getRecordKey();
        $moduleRegistry = app(ModuleRegistry::class);
        $module = $moduleRegistry->getModule($moduleCode);

        if (!$module) {
            abort(404, 'Module not found');
        }

        // Get enabled status from TenantModule for current tenant
        $isEnabled = $this->getModuleEnabledStatus($moduleCode);

        // Create a dynamic model to hold module data
        $dynamicModel = new class extends \Illuminate\Database\Eloquent\Model {
            protected $guarded = [];
            public $timestamps = false;
        };

        return $dynamicModel->forceFill([
            'code' => $module->getCode(),
            'name' => $module->getName(),
            'description' => $module->getDescription(),
            'version' => $module->getVersion(),
            'enabled' => $isEnabled,
            'dependencies' => $module->getDependencies(),
            'author' => $module->getAuthor(),
            'path' => $module->getPath(),
            'services' => $module->getServices(),
            'migrations' => $module->getMigrations(),
            'config' => $module->getConfig(),
            'is_core' => in_array($moduleCode, self::CORE_MODULES),
        ]);
    }

    /**
     * Get the enabled status for a module from the database.
     */
    protected function getModuleEnabledStatus(string $moduleCode): bool
    {
        // Core modules are always enabled
        if (in_array($moduleCode, self::CORE_MODULES)) {
            return true;
        }

        $tenant = app(TenantManager::class)->current();

        if (!$tenant) {
            return true; // Default to enabled if no tenant
        }

        $tenantModule = TenantModule::where('tenant_id', $tenant->id)
            ->where('module_code', $moduleCode)
            ->first();

        // Default to enabled if no record exists
        return $tenantModule ? $tenantModule->is_active : true;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('Module Information'))
                    ->schema([
                        Infolists\Components\Split::make([
                            Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\TextEntry::make('name')
                                        ->label(__('Name'))
                                        ->weight(\Filament\Support\Enums\FontWeight::Bold),

                                    Infolists\Components\TextEntry::make('code')
                                        ->label(__('Code'))
                                        ->badge(),

                                    Infolists\Components\TextEntry::make('version')
                                        ->label(__('Version'))
                                        ->badge()
                                        ->color('info'),

                                    Infolists\Components\IconEntry::make('enabled')
                                        ->label(__('Status'))
                                        ->boolean()
                                        ->trueIcon('heroicon-o-check-circle')
                                        ->falseIcon('heroicon-o-x-circle')
                                        ->trueColor('success')
                                        ->falseColor('danger'),

                                    Infolists\Components\TextEntry::make('author')
                                        ->label(__('Author')),

                                    Infolists\Components\TextEntry::make('path')
                                        ->label(__('Path'))
                                        ->copyable(),
                                ]),
                        ])->from('lg'),

                        Infolists\Components\TextEntry::make('description')
                            ->label(__('Description'))
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make(__('Dependencies'))
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('dependencies')
                            ->hiddenLabel()
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->hiddenLabel()
                                    ->badge()
                                    ->color('primary'),
                            ])
                            ->empty(__('No dependencies')),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make(__('Services'))
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('services')
                            ->hiddenLabel()
                            ->keyLabel(__('Service'))
                            ->valueLabel(__('Class')),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make(__('Configuration'))
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('config')
                            ->hiddenLabel()
                            ->keyLabel(__('Key'))
                            ->valueLabel(__('Value')),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make(__('Migrations'))
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('migrations')
                            ->hiddenLabel()
                            ->schema([
                                Infolists\Components\TextEntry::make('file')
                                    ->hiddenLabel()
                                    ->badge()
                                    ->color('gray'),
                            ])
                            ->empty(__('No migrations found')),
                    ])
                    ->collapsible(),
            ]);
    }

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\Action::make('toggle')
                ->label(fn () => $this->getRecord()->enabled ? __('Disable Module') : __('Enable Module'))
                ->icon(fn () => $this->getRecord()->enabled ? 'heroicon-o-pause' : 'heroicon-o-play')
                ->color(fn () => $this->getRecord()->enabled ? 'warning' : 'success')
                ->requiresConfirmation()
                ->modalDescription(fn () => $this->getRecord()->enabled
                    ? __('Are you sure you want to disable this module? This may affect system functionality.')
                    : __('Are you sure you want to enable this module?'))
                ->hidden(fn () => $this->getRecord()->is_core ?? false)
                ->action(function () {
                    $record = $this->getRecord();

                    // Prevent disabling core modules
                    if (in_array($record->code, self::CORE_MODULES)) {
                        Notification::make()
                            ->title(__('Cannot disable core module'))
                            ->body(__(':module is a core module and cannot be disabled.', ['module' => $record->name]))
                            ->warning()
                            ->send();
                        return;
                    }

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
                            'module_code' => $record->code,
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
                            ->body(__(':module has been disabled.', ['module' => $record->name]))
                            ->success()
                            ->send();
                    } else {
                        $tenantModule->activate(auth()->id());
                        Notification::make()
                            ->title(__('Module enabled'))
                            ->body(__(':module has been enabled.', ['module' => $record->name]))
                            ->success()
                            ->send();
                    }

                    $this->refreshFormData([]);
                }),

            Actions\Action::make('refresh')
                ->label(__('Refresh Module'))
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function () {
                    $moduleManager = app(ModuleManager::class);
                    $moduleManager->refreshModule($this->getRecord()->code);

                    Notification::make()
                        ->title(__('Module refreshed'))
                        ->success()
                        ->send();

                    $this->refreshFormData([]);
                }),

            Actions\Action::make('runMigrations')
                ->label(__('Run Migrations'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn () => !empty($this->getRecord()->migrations))
                ->action(function () {
                    $moduleCode = $this->getRecord()->code;
                    \Illuminate\Support\Facades\Artisan::call('migrate', [
                        '--path' => "modules/{$moduleCode}/Database/Migrations"
                    ]);

                    Notification::make()
                        ->title(__('Migrations completed'))
                        ->body(__('All module migrations have been run successfully.'))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('clearCache')
                ->label(__('Clear Cache'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->action(function () {
                    $moduleManager = app(ModuleManager::class);
                    $moduleManager->clearModuleCache($this->getRecord()->code);

                    Notification::make()
                        ->title(__('Module cache cleared'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
