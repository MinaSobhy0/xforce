<?php

namespace Modules\Booking\Filament\Resources\TimeOffAllocationResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Modules\Booking\Filament\Resources\TimeOffAllocationResource;

class EditTimeOffAllocation extends BaseEditRecord
{
    protected static string $resource = TimeOffAllocationResource::class;

    protected function getEditHeaderActions(): array
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
