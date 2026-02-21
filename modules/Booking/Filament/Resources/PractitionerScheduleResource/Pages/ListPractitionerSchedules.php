<?php

namespace Modules\Booking\Filament\Resources\PractitionerScheduleResource\Pages;

use Modules\Booking\Filament\Resources\PractitionerScheduleResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListPractitionerSchedules extends BaseListRecords
{
    protected static string $resource = PractitionerScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
