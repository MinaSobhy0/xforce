<?php

namespace Modules\Staff\Filament\Resources\StaffProfileResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Modules\Staff\Filament\Resources\StaffProfileResource;

class ViewStaffProfile extends ViewRecord
{
    protected static string $resource = StaffProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
