<?php

namespace Modules\Booking\Filament\Resources\TimeOffAllocationResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Booking\Filament\Resources\TimeOffAllocationResource;

class EditTimeOffAllocation extends EditRecord
{
    protected static string $resource = TimeOffAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
