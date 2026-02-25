<?php

namespace Modules\Auth\Resources\RoleResource\Pages;

use Modules\Auth\Resources\RoleResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListRoles extends BaseListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('New Role')),
            ...parent::getHeaderActions(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RoleResource\Widgets\RoleStatsWidget::class,
        ];
    }
}