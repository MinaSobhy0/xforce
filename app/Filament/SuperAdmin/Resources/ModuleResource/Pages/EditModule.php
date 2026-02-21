<?php

namespace App\Filament\SuperAdmin\Resources\ModuleResource\Pages;

use App\Filament\SuperAdmin\Resources\ModuleResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditModule extends BaseEditRecord
{
    use Translatable;

    protected static string $resource = ModuleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Ensure dependencies is always an array
        if (isset($data['dependencies'])) {
            if (is_string($data['dependencies'])) {
                $data['dependencies'] = json_decode($data['dependencies'], true) ?? [];
            }
        } else {
            $data['dependencies'] = [];
        }

        return $data;
    }

    protected function getEditHeaderActions(): array
    {
        return [
            \Filament\Actions\LocaleSwitcher::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
