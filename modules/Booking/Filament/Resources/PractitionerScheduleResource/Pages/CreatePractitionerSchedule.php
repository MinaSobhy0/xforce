<?php

namespace Modules\Booking\Filament\Resources\PractitionerScheduleResource\Pages;

use Modules\Booking\Filament\Resources\PractitionerScheduleResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePractitionerSchedule extends CreateRecord
{
    protected static string $resource = PractitionerScheduleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
