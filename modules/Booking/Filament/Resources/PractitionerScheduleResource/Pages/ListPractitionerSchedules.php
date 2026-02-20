<?php

namespace Modules\Booking\Filament\Resources\PractitionerScheduleResource\Pages;

use Modules\Booking\Filament\Resources\PractitionerScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPractitionerSchedules extends ListRecords
{
    protected static string $resource = PractitionerScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
