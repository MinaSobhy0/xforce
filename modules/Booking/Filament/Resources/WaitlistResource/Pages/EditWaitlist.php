<?php

namespace Modules\Booking\Filament\Resources\WaitlistResource\Pages;

use Modules\Booking\Filament\Resources\WaitlistResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWaitlist extends EditRecord
{
    protected static string $resource = WaitlistResource::class;

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
