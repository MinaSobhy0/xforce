<?php

namespace Modules\Loyalty\Filament\Resources\LoyaltyTransactionResource\Pages;

use Modules\Loyalty\Filament\Resources\LoyaltyTransactionResource;
use App\Filament\Resources\Pages\BaseListRecords;

class ListLoyaltyTransactions extends BaseListRecords
{
    protected static string $resource = LoyaltyTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
        ];
    }
}
