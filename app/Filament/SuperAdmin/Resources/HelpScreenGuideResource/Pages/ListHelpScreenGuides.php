<?php

namespace App\Filament\SuperAdmin\Resources\HelpScreenGuideResource\Pages;

use App\Filament\SuperAdmin\Resources\HelpScreenGuideResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHelpScreenGuides extends ListRecords
{
    protected static string $resource = HelpScreenGuideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
