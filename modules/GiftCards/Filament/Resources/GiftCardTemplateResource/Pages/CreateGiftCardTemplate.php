<?php

namespace Modules\GiftCards\Filament\Resources\GiftCardTemplateResource\Pages;

use Modules\GiftCards\Filament\Resources\GiftCardTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGiftCardTemplate extends CreateRecord
{
    protected static string $resource = GiftCardTemplateResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
