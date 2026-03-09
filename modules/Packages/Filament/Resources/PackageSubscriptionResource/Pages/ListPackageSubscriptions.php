<?php

namespace Modules\Packages\Filament\Resources\PackageSubscriptionResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Packages\Filament\Resources\PackageSubscriptionResource;

class ListPackageSubscriptions extends ListRecords
{
    protected static string $resource = PackageSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
