<?php

namespace Modules\Services\Filament\Resources\ParameterTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Modules\Services\Filament\Resources\ParameterTemplateResource;

class ViewParameterTemplate extends ViewRecord
{
    protected static string $resource = ParameterTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => !$this->record->is_system),
        ];
    }
}
