<?php

namespace App\Filament\SuperAdmin\Resources\AddOnResource\Pages;

use App\Filament\SuperAdmin\Resources\AddOnResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListAddOns extends BaseListRecords
{
    protected static string $resource = AddOnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
