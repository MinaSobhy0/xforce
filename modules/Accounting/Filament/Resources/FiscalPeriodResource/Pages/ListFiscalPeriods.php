<?php

namespace Modules\Accounting\Filament\Resources\FiscalPeriodResource\Pages;

use Modules\Accounting\Filament\Resources\FiscalPeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFiscalPeriods extends ListRecords
{
    protected static string $resource = FiscalPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
