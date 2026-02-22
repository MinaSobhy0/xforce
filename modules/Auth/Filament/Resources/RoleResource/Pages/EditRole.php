<?php

namespace Modules\Auth\Filament\Resources\RoleResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Auth\Filament\Resources\RoleResource;
use Spatie\Permission\Models\Permission;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->hidden(fn () => $this->record->is_system),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $permissions = $this->record->permissions->pluck('name')->toArray();

        foreach (RoleResource::getResourcePermissions() as $resource => $label) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $permName = "{$resource}.{$action}";
                $data['permissions'][$resource][$action] = in_array($permName, $permissions);
            }
        }

        return $data;
    }

    protected function afterSave(): void
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
