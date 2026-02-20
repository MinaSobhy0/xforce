<?php

namespace Modules\Marketing\Filament\Resources\MessageTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Marketing\Filament\Resources\MessageTemplateResource;

class ListMessageTemplates extends ListRecords
{
    protected static string $resource = MessageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
