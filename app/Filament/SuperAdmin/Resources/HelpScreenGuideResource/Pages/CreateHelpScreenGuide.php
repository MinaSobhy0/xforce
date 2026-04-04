<?php

namespace App\Filament\SuperAdmin\Resources\HelpScreenGuideResource\Pages;

use App\Filament\SuperAdmin\Resources\HelpScreenGuideResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHelpScreenGuide extends CreateRecord
{
    protected static string $resource = HelpScreenGuideResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
