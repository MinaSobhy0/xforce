<?php

namespace Modules\Core\Filament\Resources\DepartmentResource\Pages;

use Modules\Core\Filament\Resources\DepartmentResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;

class ViewDepartment extends BaseViewRecord
{
    protected static string $resource = DepartmentResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
