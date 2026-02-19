<?php

namespace Modules\Auth\Resources\RoleResource\Pages;

use Modules\Auth\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('New Role')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RoleResource\Widgets\RoleStatsWidget::class,
        ];
    }
}