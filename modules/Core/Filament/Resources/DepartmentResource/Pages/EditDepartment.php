<?php

namespace Modules\Core\Filament\Resources\DepartmentResource\Pages;

use Modules\Core\Filament\Resources\DepartmentResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditDepartment extends BaseEditRecord
{
    protected static string $resource = DepartmentResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->before(function () {
                    if ($this->record->hasChildren()) {
                        $this->halt();
                        $this->notify('danger', __('core::core.cannot_delete_department_with_children'));
                    }
                    if ($this->record->hasStaff()) {
                        $this->halt();
                        $this->notify('danger', __('core::core.cannot_delete_department_with_staff'));
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
