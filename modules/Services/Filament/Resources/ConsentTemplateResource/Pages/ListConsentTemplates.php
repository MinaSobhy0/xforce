<?php

namespace Modules\Services\Filament\Resources\ConsentTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Services\Filament\Resources\ConsentTemplateResource;

class ListConsentTemplates extends ListRecords
{
    protected static string $resource = ConsentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
