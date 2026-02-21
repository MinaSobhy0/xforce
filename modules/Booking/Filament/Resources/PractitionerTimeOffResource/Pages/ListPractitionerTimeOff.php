<?php

namespace Modules\Booking\Filament\Resources\PractitionerTimeOffResource\Pages;

use Modules\Booking\Filament\Resources\PractitionerTimeOffResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListPractitionerTimeOff extends BaseListRecords
{
    protected static string $resource = PractitionerTimeOffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
