<?php

namespace Modules\Assets\Filament\Resources\AssetTypeResource\Pages;

use Modules\Assets\Filament\Resources\AssetTypeResource;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Actions;

class ListAssetTypes extends BaseListRecords
{
    protected static string $resource = AssetTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
