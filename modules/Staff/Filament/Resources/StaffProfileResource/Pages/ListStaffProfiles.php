<?php

namespace Modules\Staff\Filament\Resources\StaffProfileResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Staff\Filament\Resources\StaffProfileResource;

class ListStaffProfiles extends ListRecords
{
    protected static string $resource = StaffProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
