<?php

namespace Modules\Auth\Filament\Resources\RoleResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Auth\Filament\Resources\RoleResource;
use Modules\Auth\Models\Permission;

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
                $permName = "{$resource}.{$action}";
                // Always create permission record so it exists for checking
                Permission::firstOrCreate(
                    ['name' => $permName, 'guard_name' => 'web']
                );
                if ($granted) {
                    $permissionNames[] = $permName;
                }
            }
        }

        $this->record->syncPermissions($permissionNames);

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
