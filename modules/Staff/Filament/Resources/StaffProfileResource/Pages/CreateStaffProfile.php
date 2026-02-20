<?php

namespace Modules\Staff\Filament\Resources\StaffProfileResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Staff\Filament\Resources\StaffProfileResource;

class CreateStaffProfile extends CreateRecord
{
    protected static string $resource = StaffProfileResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
