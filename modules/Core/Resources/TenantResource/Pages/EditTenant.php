<?php

namespace Modules\Core\Resources\TenantResource\Pages;

use Modules\Core\Resources\TenantResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Notifications\Notification;
use Modules\Core\Services\TenantService;

class EditTenant extends BaseEditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),

            Actions\Action::make('syncDatabase')
                ->label(__('Sync Database'))
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription(__('This will run migrations and sync the tenant database with the latest schema.'))
                ->action(function () {
                    try {
                        $tenantService = app(TenantService::class);
                        $tenantService->syncTenantDatabase($this->getRecord());

                        Notification::make()
                            ->title(__('Database synchronized'))
                            ->body(__('Tenant database has been updated with the latest schema.'))
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title(__('Sync failed'))
                            ->body(__('Failed to sync tenant database: :error', ['error' => $e->getMessage()]))
                            ->danger()
                            ->send();
                    }
                }),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function afterSave(): void
    {
        // If slug was changed, we might need to update the database schema name
        if ($this->getRecord()->wasChanged('slug')) {
            Notification::make()
                ->title(__('Slug changed'))
                ->body(__('The tenant slug has been updated. Consider running database sync if needed.'))
                ->warning()
                ->persistent()
                ->send();
        }

        // If status changed to suspended, log the action
        if ($this->getRecord()->wasChanged('status') && $this->getRecord()->status->value === 'suspended') {
            logger()->info('Tenant suspended via edit', [
                'tenant_id' => $this->getRecord()->id,
                'admin_user_id' => auth()->id(),
            ]);
        }
    }
}