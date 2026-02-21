<?php

namespace Modules\Booking\Filament\Resources\WorkScheduleResource\Pages;

use Modules\Booking\Filament\Resources\WorkScheduleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkSchedule extends CreateRecord
{
    protected static string $resource = WorkScheduleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
