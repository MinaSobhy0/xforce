<?php

namespace Modules\Loyalty\Filament\Resources\LoyaltyRuleResource\Pages;

use Modules\Loyalty\Filament\Resources\LoyaltyRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLoyaltyRule extends EditRecord
{
    protected static string $resource = LoyaltyRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
