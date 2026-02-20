<?php

namespace Modules\Loyalty\Filament\Resources\ReferralProgramResource\Pages;

use Modules\Loyalty\Filament\Resources\ReferralProgramResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReferralPrograms extends ListRecords
{
    protected static string $resource = ReferralProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
