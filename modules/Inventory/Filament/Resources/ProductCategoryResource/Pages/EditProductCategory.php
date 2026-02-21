<?php

namespace Modules\Inventory\Filament\Resources\ProductCategoryResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Modules\Inventory\Filament\Resources\ProductCategoryResource;

class EditProductCategory extends BaseEditRecord
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->products()->count() === 0 && $this->record->children()->count() === 0),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
