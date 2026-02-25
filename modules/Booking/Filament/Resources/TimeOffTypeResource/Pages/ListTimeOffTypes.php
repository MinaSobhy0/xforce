<?php

namespace Modules\Booking\Filament\Resources\TimeOffTypeResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Booking\Filament\Resources\TimeOffTypeResource;

class ListTimeOffTypes extends BaseListRecords
{
    protected static string $resource = TimeOffTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
