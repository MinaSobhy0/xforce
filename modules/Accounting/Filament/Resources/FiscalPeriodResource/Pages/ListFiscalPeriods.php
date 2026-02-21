<?php

namespace Modules\Accounting\Filament\Resources\FiscalPeriodResource\Pages;

use Modules\Accounting\Filament\Resources\FiscalPeriodResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListFiscalPeriods extends BaseListRecords
{
    protected static string $resource = FiscalPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
