<?php

namespace Modules\Booking\Filament\Resources\TimeOffTypeResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Booking\Filament\Resources\TimeOffTypeResource;

class CreateTimeOffType extends CreateRecord
{
    protected static string $resource = TimeOffTypeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
