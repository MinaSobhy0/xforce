<?php

namespace App\Filament\SuperAdmin\Resources\PromoCodeResource\Pages;

use App\Filament\SuperAdmin\Resources\PromoCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPromoCodes extends ListRecords
{
    protected static string $resource = PromoCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Promo Code'),
        ];
    }
}
