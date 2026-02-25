<?php

namespace Modules\Booking\Filament\Resources\TimeOffAllocationResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Booking\Filament\Resources\TimeOffAllocationResource;

class CreateTimeOffAllocation extends CreateRecord
{
    protected static string $resource = TimeOffAllocationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
