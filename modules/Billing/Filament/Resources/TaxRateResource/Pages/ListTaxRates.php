<?php

namespace Modules\Billing\Filament\Resources\TaxRateResource\Pages;

use Modules\Billing\Filament\Resources\TaxRateResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListTaxRates extends BaseListRecords
{
    protected static string $resource = TaxRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
