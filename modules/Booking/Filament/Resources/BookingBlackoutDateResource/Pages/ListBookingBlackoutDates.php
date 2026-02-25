<?php

namespace Modules\Booking\Filament\Resources\BookingBlackoutDateResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Booking\Filament\Resources\BookingBlackoutDateResource;

class ListBookingBlackoutDates extends BaseListRecords
{
    protected static string $resource = BookingBlackoutDateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
