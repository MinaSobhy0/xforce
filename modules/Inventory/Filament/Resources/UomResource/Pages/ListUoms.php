<?php

namespace Modules\Inventory\Filament\Resources\UomResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Inventory\Filament\Resources\UomResource;

class ListUoms extends BaseListRecords
{
    protected static string $resource = UomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
