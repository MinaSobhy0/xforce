<?php

namespace Modules\GiftCards\Filament\Resources\GiftCardResource\Pages;

use Modules\GiftCards\Filament\Resources\GiftCardResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListGiftCards extends BaseListRecords
{
    protected static string $resource = GiftCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
