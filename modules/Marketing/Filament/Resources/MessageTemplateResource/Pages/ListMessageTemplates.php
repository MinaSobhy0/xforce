<?php

namespace Modules\Marketing\Filament\Resources\MessageTemplateResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Marketing\Filament\Resources\MessageTemplateResource;

class ListMessageTemplates extends BaseListRecords
{
    protected static string $resource = MessageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
