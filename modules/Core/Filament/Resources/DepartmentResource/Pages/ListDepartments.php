<?php

namespace Modules\Core\Filament\Resources\DepartmentResource\Pages;

use Modules\Core\Filament\Resources\DepartmentResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListDepartments extends BaseListRecords
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
