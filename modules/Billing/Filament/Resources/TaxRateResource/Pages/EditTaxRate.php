<?php

namespace Modules\Billing\Filament\Resources\TaxRateResource\Pages;

use Modules\Billing\Filament\Resources\TaxRateResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditTaxRate extends BaseEditRecord
{
    protected static string $resource = TaxRateResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
