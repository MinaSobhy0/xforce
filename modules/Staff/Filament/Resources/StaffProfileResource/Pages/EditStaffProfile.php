<?php

namespace Modules\Staff\Filament\Resources\StaffProfileResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Staff\Filament\Resources\StaffProfileResource;

class EditStaffProfile extends EditRecord
{
    protected static string $resource = StaffProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
