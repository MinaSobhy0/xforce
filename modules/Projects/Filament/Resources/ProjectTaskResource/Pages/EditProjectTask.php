<?php

namespace Modules\Projects\Filament\Resources\ProjectTaskResource\Pages;

use Modules\Projects\Filament\Resources\ProjectTaskResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProjectTask extends EditRecord
{
    protected static string $resource = ProjectTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
