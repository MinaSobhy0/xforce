<?php

namespace App\Filament\SuperAdmin\Resources\AddOnResource\Pages;

use App\Filament\SuperAdmin\Resources\AddOnResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAddOns extends ListRecords
{
    protected static string $resource = AddOnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
