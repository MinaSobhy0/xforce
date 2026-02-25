<?php

namespace Modules\Booking\Filament\Resources\TimeOffAllocationResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Booking\Filament\Resources\TimeOffAllocationResource;

class ListTimeOffAllocations extends BaseListRecords
{
    protected static string $resource = TimeOffAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
