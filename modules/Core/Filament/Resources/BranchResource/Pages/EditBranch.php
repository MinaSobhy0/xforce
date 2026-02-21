<?php

namespace Modules\Core\Filament\Resources\BranchResource\Pages;

use Modules\Core\Filament\Resources\BranchResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditBranch extends BaseEditRecord
{
    protected static string $resource = BranchResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->before(function () {
                    if ($this->record->is_main) {
                        $this->halt();
                        $this->notify('danger', __('core::core.cannot_delete_main_branch'));
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
