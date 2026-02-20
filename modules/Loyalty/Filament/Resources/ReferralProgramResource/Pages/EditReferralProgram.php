<?php

namespace Modules\Loyalty\Filament\Resources\ReferralProgramResource\Pages;

use Modules\Loyalty\Filament\Resources\ReferralProgramResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReferralProgram extends EditRecord
{
    protected static string $resource = ReferralProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
