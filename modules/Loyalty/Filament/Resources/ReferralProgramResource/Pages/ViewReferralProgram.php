<?php

namespace Modules\Loyalty\Filament\Resources\ReferralProgramResource\Pages;

use Modules\Loyalty\Filament\Resources\ReferralProgramResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;

class ViewReferralProgram extends BaseViewRecord
{
    protected static string $resource = ReferralProgramResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
