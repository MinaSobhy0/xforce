<?php

namespace Modules\GiftCards\Filament\Resources\GiftCardTemplateResource\Pages;

use Modules\GiftCards\Filament\Resources\GiftCardTemplateResource;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Actions;

class ListGiftCardTemplates extends BaseListRecords
{
    protected static string $resource = GiftCardTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
