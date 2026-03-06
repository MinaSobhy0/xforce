<?php

namespace Modules\Booking\Filament\Resources\VisitResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Booking\Filament\Resources\VisitResource;

class ListVisits extends ListRecords
{
    protected static string $resource = VisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - visits are created automatically on check-in
        ];
    }
}
