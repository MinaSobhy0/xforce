<?php

namespace Modules\Packages\Filament\Resources\PackageResource\Pages;

use Modules\Packages\Filament\Resources\PackageResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;

class ViewPackage extends BaseViewRecord
{
    protected static string $resource = PackageResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
