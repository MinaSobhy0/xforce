<?php

namespace Modules\Inventory\Filament\Resources\UomCategoryResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Modules\Inventory\Filament\Resources\UomCategoryResource;

class EditUomCategory extends BaseEditRecord
{
    protected static string $resource = UomCategoryResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->uoms()->count() === 0),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
