<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Services\TenantService;
use Illuminate\Support\Facades\Log;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function afterCreate(): void
    {
        $tenant = $this->record;

        try {
            // Use TenantService to create the database schema and run migrations
            $tenantService = app(TenantService::class);
            $tenantService->createTenantDatabase($tenant);

            Notification::make()
                ->title('Clinic created successfully')
                ->body("Database schema '{$tenant->database_name}' has been created and migrations have been run.")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Tenant database provisioning failed', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'database_name' => $tenant->database_name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->title('Clinic created with provisioning error')
                ->body("The clinic was created but database setup failed: {$e->getMessage()}")
                ->danger()
                ->persistent()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
