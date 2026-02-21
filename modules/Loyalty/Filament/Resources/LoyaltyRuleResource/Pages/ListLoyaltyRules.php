<?php

namespace Modules\Loyalty\Filament\Resources\LoyaltyRuleResource\Pages;

use Modules\Loyalty\Filament\Resources\LoyaltyRuleResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListLoyaltyRules extends BaseListRecords
{
    protected static string $resource = LoyaltyRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
