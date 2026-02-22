<?php

namespace Modules\Core\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Modules\Core\Filament\Resources\ModuleManagementResource\Pages;
use XLinic\Framework\Core\Module\ModuleManager;
use XLinic\Framework\Core\Module\ModuleRegistry;

class ModuleManagementResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 80;
    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return __('Modules');
    }

    public static function getModelLabel(): string
    {
        return __('Module');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Modules');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModules::route('/'),
            'view' => Pages\ViewModule::route('/{record}'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['super_admin', 'admin']) ?? false;
    }

    protected static ?string $slug = 'modules';
}
