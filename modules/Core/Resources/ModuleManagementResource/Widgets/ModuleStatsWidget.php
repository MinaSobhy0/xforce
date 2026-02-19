<?php

namespace Modules\Core\Resources\ModuleManagementResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use XLinic\Framework\Core\Module\ModuleRegistry;

class ModuleStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $moduleRegistry = app(ModuleRegistry::class);
        $modules = $moduleRegistry->getAllModules();

        $totalModules = count($modules);
        $enabledModules = count(array_filter($modules, fn($module) => $module->isEnabled()));
        $disabledModules = $totalModules - $enabledModules;

        $coreModules = count(array_filter($modules, function($module) {
            return in_array($module->getCode(), ['core', 'auth']);
        }));

        $customModules = $totalModules - $coreModules;

        $enabledPercentage = $totalModules > 0 ? round(($enabledModules / $totalModules) * 100, 1) : 0;

        return [
            Stat::make(__('Total Modules'), $totalModules)
                ->description(__('All installed modules'))
                ->descriptionIcon('heroicon-m-puzzle-piece')
                ->color('primary'),

            Stat::make(__('Enabled Modules'), $enabledModules)
                ->description($enabledPercentage . '% ' . __('of total'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart([3, 5, 8, 6, 9, 12, $enabledModules]),

            Stat::make(__('Disabled Modules'), $disabledModules)
                ->description(__('Inactive modules'))
                ->descriptionIcon('heroicon-m-pause-circle')
                ->color('warning'),

            Stat::make(__('Core Modules'), $coreModules)
                ->description(__('System modules'))
                ->descriptionIcon('heroicon-m-cog-6-tooth')
                ->color('info'),

            Stat::make(__('Custom Modules'), $customModules)
                ->description(__('Third-party modules'))
                ->descriptionIcon('heroicon-m-cube')
                ->color('gray'),
        ];
    }
}