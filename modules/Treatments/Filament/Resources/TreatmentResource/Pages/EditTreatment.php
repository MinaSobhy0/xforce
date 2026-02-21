<?php

namespace Modules\Treatments\Filament\Resources\TreatmentResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Modules\Treatments\Filament\Resources\TreatmentResource;

class EditTreatment extends BaseEditRecord
{
    protected static string $resource = TreatmentResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
