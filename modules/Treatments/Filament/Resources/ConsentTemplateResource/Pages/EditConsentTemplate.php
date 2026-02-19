<?php

namespace Modules\Treatments\Filament\Resources\ConsentTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Treatments\Filament\Resources\ConsentTemplateResource;

class EditConsentTemplate extends EditRecord
{
    protected static string $resource = ConsentTemplateResource::class;

    protected function getHeaderActions(): array
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
