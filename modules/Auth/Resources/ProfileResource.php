<?php

namespace Modules\Auth\Resources;

use Filament\Resources\Resource;
use Modules\Auth\Resources\ProfileResource\Pages;

class ProfileResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationGroup = 'Account';
    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('My Profile');
    }

    public static function getModelLabel(): string
    {
        return __('Profile');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Profile');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageProfile::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    protected static ?string $slug = 'profile';
}