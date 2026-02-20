<?php

namespace Modules\Accounting\Filament\Resources\FiscalPeriodResource\Pages;

use Modules\Accounting\Filament\Resources\FiscalPeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFiscalPeriod extends EditRecord
{
    protected static string $resource = FiscalPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->isOpen()),
        ];
    }
}
