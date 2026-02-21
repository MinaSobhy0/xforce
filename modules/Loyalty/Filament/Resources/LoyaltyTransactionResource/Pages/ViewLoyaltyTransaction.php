<?php

namespace Modules\Loyalty\Filament\Resources\LoyaltyTransactionResource\Pages;

use Modules\Loyalty\Filament\Resources\LoyaltyTransactionResource;
use App\Filament\Resources\Pages\BaseViewRecord;

class ViewLoyaltyTransaction extends BaseViewRecord
{
    protected static string $resource = LoyaltyTransactionResource::class;

    protected function getViewHeaderActions(): array
    {
        return [];
    }
}
