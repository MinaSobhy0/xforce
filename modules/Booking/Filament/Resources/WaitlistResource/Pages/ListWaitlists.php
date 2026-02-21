<?php

namespace Modules\Booking\Filament\Resources\WaitlistResource\Pages;

use Modules\Booking\Filament\Resources\WaitlistResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListWaitlists extends BaseListRecords
{
    protected static string $resource = WaitlistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
