<?php

namespace App\Filament\Traits;

use App\Filament\Actions\ExportTableAction;

trait HasExportAction
{
    protected function getHeaderActions(): array
    {
        return [
            ExportTableAction::make(),
            ...parent::getHeaderActions(),
        ];
    }
}
