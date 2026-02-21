<?php

namespace Modules\Loyalty\Filament\Resources\ReferralProgramResource\Pages;

use Modules\Loyalty\Filament\Resources\ReferralProgramResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListReferralPrograms extends BaseListRecords
{
    protected static string $resource = ReferralProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
