<?php

namespace App\Filament\SuperAdmin\Resources\PlatformAdminResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformAdminResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditPlatformAdmin extends BaseEditRecord
{
    protected static string $resource = PlatformAdminResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->id !== auth()->id()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Split name into first_name and last_name
        if (isset($data['name'])) {
            $nameParts = explode(' ', trim($data['name']), 2);
            $data['first_name'] = $nameParts[0];
            $data['last_name'] = $nameParts[1] ?? '';
            unset($data['name']);
        }

        return $data;
    }
}
