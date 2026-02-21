<?php

namespace App\Filament\SuperAdmin\Resources\PromoCodeResource\Pages;

use App\Filament\SuperAdmin\Resources\PromoCodeResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditPromoCode extends BaseEditRecord
{
    protected static string $resource = PromoCodeResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
