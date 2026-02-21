<?php

namespace Modules\Accounting\Filament\Resources\ChartOfAccountResource\Pages;

use Modules\Accounting\Filament\Resources\ChartOfAccountResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditChartOfAccount extends BaseEditRecord
{
    protected static string $resource = ChartOfAccountResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => !$this->record->is_system),
        ];
    }
}
