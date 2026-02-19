<?php

namespace Modules\Auth\Resources;

use Filament\Resources\Resource;
use Modules\Auth\Resources\TwoFactorSetupResource\Pages;

class TwoFactorSetupResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Account';
    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('Two-Factor Authentication');
    }

    public static function getModelLabel(): string
    {
        return __('Two-Factor Authentication');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Two-Factor Authentication');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTwoFactorSetup::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    protected static ?string $slug = 'two-factor-auth';
}
