<?php

namespace Modules\Staff\Filament\Resources\CommissionRecordResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Staff\Filament\Resources\CommissionRecordResource;

class ListCommissionRecords extends ListRecords
{
    protected static string $resource = CommissionRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
