<?php

namespace Modules\Booking\Filament\Resources\WaitlistResource\Pages;

use Modules\Booking\Filament\Resources\WaitlistResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWaitlist extends CreateRecord
{
    protected static string $resource = WaitlistResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
