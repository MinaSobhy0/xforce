<?php

namespace Modules\Packages\Filament\Resources\PackageResource\Pages;

use Modules\Packages\Filament\Resources\PackageResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditPackage extends BaseEditRecord
{
    protected static string $resource = PackageResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
