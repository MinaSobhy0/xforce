<?php

namespace Modules\Assets\Filament\Resources\AssetTypeResource\Pages;

use Modules\Assets\Filament\Resources\AssetTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssetType extends CreateRecord
{
    protected static string $resource = AssetTypeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
