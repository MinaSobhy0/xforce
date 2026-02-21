<?php

namespace Modules\Loyalty\Filament\Resources\LoyaltyRuleResource\Pages;

use Modules\Loyalty\Filament\Resources\LoyaltyRuleResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;

class ViewLoyaltyRule extends BaseViewRecord
{
    protected static string $resource = LoyaltyRuleResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
