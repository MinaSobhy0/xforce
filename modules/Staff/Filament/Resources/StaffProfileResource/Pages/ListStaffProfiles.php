<?php

namespace Modules\Staff\Filament\Resources\StaffProfileResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Staff\Filament\Resources\StaffProfileResource;

class ListStaffProfiles extends BaseListRecords
{
    use \App\Filament\Traits\HasExtraHeaderActions;

    protected static string $resource = StaffProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
            ...static::resolveExtraHeaderActions(),
        ];
    }
}
