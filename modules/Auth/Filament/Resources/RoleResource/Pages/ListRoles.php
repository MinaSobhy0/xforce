<?php

namespace Modules\Auth\Filament\Resources\RoleResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Auth\Filament\Resources\RoleResource;

class ListRoles extends BaseListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ...parent::getHeaderActions(),
        ];
    }
}
