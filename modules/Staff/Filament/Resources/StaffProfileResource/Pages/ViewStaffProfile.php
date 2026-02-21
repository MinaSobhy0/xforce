<?php

namespace Modules\Staff\Filament\Resources\StaffProfileResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Modules\Staff\Filament\Resources\StaffProfileResource;

class ViewStaffProfile extends BaseViewRecord
{
    protected static string $resource = StaffProfileResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
