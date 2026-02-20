<?php

namespace Modules\Loyalty\Filament\Resources\LoyaltyTransactionResource\Pages;

use Modules\Loyalty\Filament\Resources\LoyaltyTransactionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewLoyaltyTransaction extends ViewRecord
{
    protected static string $resource = LoyaltyTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
