<?php

namespace Modules\Loyalty\Filament\Resources\LoyaltyRuleResource\Pages;

use Modules\Loyalty\Filament\Resources\LoyaltyRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewLoyaltyRule extends ViewRecord
{
    protected static string $resource = LoyaltyRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
