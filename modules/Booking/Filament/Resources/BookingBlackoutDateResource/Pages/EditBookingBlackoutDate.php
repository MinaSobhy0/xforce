<?php

namespace Modules\Booking\Filament\Resources\BookingBlackoutDateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Booking\Filament\Resources\BookingBlackoutDateResource;

class EditBookingBlackoutDate extends EditRecord
{
    protected static string $resource = BookingBlackoutDateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
