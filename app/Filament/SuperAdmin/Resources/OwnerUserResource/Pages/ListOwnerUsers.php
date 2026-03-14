<?php

namespace App\Filament\SuperAdmin\Resources\OwnerUserResource\Pages;

use App\Filament\SuperAdmin\Resources\OwnerUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOwnerUsers extends ListRecords
{
    protected static string $resource = OwnerUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
