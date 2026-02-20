<?php

namespace Modules\GiftCards\Filament\Resources\GiftCardResource\Pages;

use Modules\GiftCards\Filament\Resources\GiftCardResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGiftCard extends CreateRecord
{
    protected static string $resource = GiftCardResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
