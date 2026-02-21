<?php

namespace App\Filament\SuperAdmin\Resources\ModuleResource\Pages;

use App\Filament\SuperAdmin\Resources\ModuleResource;
use App\Models\Module;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditModule extends BaseEditRecord
{
    use Translatable;

    protected static string $resource = ModuleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load dependencies directly from the model since Translatable might filter it
        $record = $this->getRecord();

        if ($record instanceof Module) {
            $data['dependencies'] = $record->dependencies ?? [];
        }

        // Also handle if it comes as string
        if (isset($data['dependencies']) && is_string($data['dependencies'])) {
            $data['dependencies'] = json_decode($data['dependencies'], true) ?? [];
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
