<?php

namespace Modules\MobileApi\Filament\Resources\DeviceTokenResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\MobileApi\Filament\Resources\DeviceTokenResource;

class ListDeviceTokens extends ListRecords
{
    protected static string $resource = DeviceTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - devices are registered via API
        ];
    }
}
