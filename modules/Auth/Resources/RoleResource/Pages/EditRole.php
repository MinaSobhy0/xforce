<?php

namespace Modules\Auth\Resources\RoleResource\Pages;

use Modules\Auth\Resources\RoleResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditRole extends BaseEditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => !$this->getRecord()->is_system),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}