<?php

namespace Modules\Assets\Filament\Resources\AssetResource\Pages;

use Modules\Assets\Filament\Resources\AssetResource;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Actions;

class ListAssets extends BaseListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
