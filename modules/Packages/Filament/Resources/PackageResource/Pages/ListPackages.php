<?php

namespace Modules\Packages\Filament\Resources\PackageResource\Pages;

use Modules\Packages\Filament\Resources\PackageResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListPackages extends BaseListRecords
{
    protected static string $resource = PackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
