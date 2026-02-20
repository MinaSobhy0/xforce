<?php

namespace Modules\Billing\Filament\Resources\TaxRateResource\Pages;

use Modules\Billing\Filament\Resources\TaxRateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTaxRates extends ListRecords
{
    protected static string $resource = TaxRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
