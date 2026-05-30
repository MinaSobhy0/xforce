<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Tenant;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * The tenant form exposes platform-only fields (subscription_plan_id,
     * subscription_status, status, trial_ends_at, *_expires_at, limits)
     * that live in Tenant::$guarded for billing/security safety. Filament's
     * default record creation uses mass-assignment which silently drops
     * them — operators saw the dropdown apparently work but nothing
     * persisted. We're the SuperAdmin form, so it's safe to bypass the
     * guard for this one save; the model's creating hook still gets to
     * see the assigned plan and resolve tenant.features from it.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return Tenant::unguarded(fn () => Tenant::create($data));
    }

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
}
