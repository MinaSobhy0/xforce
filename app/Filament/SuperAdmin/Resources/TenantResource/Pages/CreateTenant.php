<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Clinic created successfully')
            ->body("Tenant '{$this->record->name}' has been created. Use 'Provision Database' action to create the database schema.")
            ->info()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $model = static::getModel();
        $record = new $model();
        $record->forceFill($data)->save();

        return $record;
    }
}
