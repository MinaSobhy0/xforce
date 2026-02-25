<?php

namespace Modules\Booking\Filament\Resources\BookingRuleResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Booking\Filament\Resources\BookingRuleResource;

class ListBookingRules extends BaseListRecords
{
    protected static string $resource = BookingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
