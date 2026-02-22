<?php

namespace Modules\Auth\Filament\Resources\RoleResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Auth\Filament\Resources\RoleResource;
use Spatie\Permission\Models\Permission;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['guard_name'] = 'web';
        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncPermissions();
    }

    protected function syncPermissions(): void
    {
        $permissions = $this->data['permissions'] ?? [];
        $permissionNames = [];

        foreach ($permissions as $resource => $actions) {
            foreach ($actions as $action => $granted) {
                if ($granted) {
                    $permName = "{$resource}.{$action}";
                    // Create permission if doesn't exist
                    Permission::firstOrCreate(
                        ['name' => $permName, 'guard_name' => 'web']
                    );
                    $permissionNames[] = $permName;
                }
            }
        }

        $this->record->syncPermissions($permissionNames);
    }
}
