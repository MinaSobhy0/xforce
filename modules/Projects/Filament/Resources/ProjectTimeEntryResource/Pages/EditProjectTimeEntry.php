<?php

namespace Modules\Projects\Filament\Resources\ProjectTimeEntryResource\Pages;

use Modules\Projects\Filament\Resources\ProjectTimeEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProjectTimeEntry extends EditRecord
{
    protected static string $resource = ProjectTimeEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
