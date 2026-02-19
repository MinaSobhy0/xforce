<?php

namespace Modules\Core\Resources\TenantResource\Pages;

use Modules\Core\Resources\TenantResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Services\TenantService;
use Filament\Notifications\Notification;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function afterCreate(): void
    {
        $tenant = $this->getRecord();

        try {
            // Use TenantService to properly set up the tenant
            $tenantService = app(TenantService::class);
            $tenantService->createTenantDatabase($tenant);

            Notification::make()
                ->title(__('Tenant created successfully'))
                ->body(__('Database schema has been created and migrations have been run.'))
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title(__('Tenant created with warnings'))
                ->body(__('Tenant was created but database setup failed: :error', ['error' => $e->getMessage()]))
                ->warning()
                ->send();

            logger()->error('Tenant database setup failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}