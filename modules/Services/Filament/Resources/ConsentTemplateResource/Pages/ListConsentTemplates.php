<?php

namespace Modules\Services\Filament\Resources\ConsentTemplateResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Services\Filament\Resources\ConsentTemplateResource;

class ListConsentTemplates extends BaseListRecords
{
    protected static string $resource = ConsentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ...parent::getHeaderActions(),
        ];
    }
}
