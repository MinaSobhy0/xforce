<?php

namespace Modules\Loyalty\Filament\Resources\LoyaltyRuleResource\Pages;

use Modules\Loyalty\Filament\Resources\LoyaltyRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLoyaltyRules extends ListRecords
{
    protected static string $resource = LoyaltyRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
