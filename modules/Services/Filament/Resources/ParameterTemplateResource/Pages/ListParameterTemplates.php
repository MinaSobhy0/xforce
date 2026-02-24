<?php

namespace Modules\Services\Filament\Resources\ParameterTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Services\Filament\Resources\ParameterTemplateResource;

class ListParameterTemplates extends ListRecords
{
    protected static string $resource = ParameterTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
