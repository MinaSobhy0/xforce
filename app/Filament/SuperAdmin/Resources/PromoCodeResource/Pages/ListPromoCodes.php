<?php

namespace App\Filament\SuperAdmin\Resources\PromoCodeResource\Pages;

use App\Filament\SuperAdmin\Resources\PromoCodeResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListPromoCodes extends BaseListRecords
{
    protected static string $resource = PromoCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make()
                ->label('Create Promo Code'),
        ];
    }
}
