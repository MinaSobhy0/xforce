<?php

namespace Modules\Booking\Filament\Resources\BookingBlackoutDateResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Booking\Filament\Resources\BookingBlackoutDateResource;

class CreateBookingBlackoutDate extends CreateRecord
{
    protected static string $resource = BookingBlackoutDateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
