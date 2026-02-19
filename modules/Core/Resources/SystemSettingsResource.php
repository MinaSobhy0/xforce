<?php

namespace Modules\Core\Resources;

use Filament\Resources\Resource;
use Modules\Core\Resources\SystemSettingsResource\Pages;

class SystemSettingsResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 90;

    public static function getNavigationLabel(): string
    {
        return __('System Settings');
    }

    public static function getModelLabel(): string
    {
        return __('System Settings');
    }

    public static function getPluralModelLabel(): string
    {
        return __('System Settings');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSystemSettings::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['super_admin', 'admin']) ?? false;
    }

    protected static ?string $slug = 'system-settings';
}
