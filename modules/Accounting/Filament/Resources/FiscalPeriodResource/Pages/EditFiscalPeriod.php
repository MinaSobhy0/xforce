<?php

namespace Modules\Accounting\Filament\Resources\FiscalPeriodResource\Pages;

use Modules\Accounting\Filament\Resources\FiscalPeriodResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditFiscalPeriod extends BaseEditRecord
{
    protected static string $resource = FiscalPeriodResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->isOpen()),
        ];
    }
}
