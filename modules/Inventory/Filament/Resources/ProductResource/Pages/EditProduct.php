<?php

namespace Modules\Inventory\Filament\Resources\ProductResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Modules\Inventory\Filament\Resources\ProductResource;

class EditProduct extends BaseEditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
