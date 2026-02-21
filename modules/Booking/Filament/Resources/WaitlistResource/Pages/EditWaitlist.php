<?php

namespace Modules\Booking\Filament\Resources\WaitlistResource\Pages;

use Modules\Booking\Filament\Resources\WaitlistResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditWaitlist extends BaseEditRecord
{
    protected static string $resource = WaitlistResource::class;

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
