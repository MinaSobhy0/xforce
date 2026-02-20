<?php

namespace Modules\Packages\Filament\Resources\PackageResource\Pages;

use Modules\Packages\Filament\Resources\PackageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPackage extends ViewRecord
{
    protected static string $resource = PackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
