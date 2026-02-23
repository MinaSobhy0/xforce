<?php

namespace Modules\Services\Filament\Resources\ConsentTemplateResource\Pages;

use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Actions;
use Modules\Services\Filament\Resources\ConsentTemplateResource;

class ViewConsentTemplate extends BaseViewRecord
{
    protected static string $resource = ConsentTemplateResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
