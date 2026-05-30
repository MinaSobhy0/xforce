<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Tenant;

class EditTenant extends BaseEditRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * Same rationale as CreateTenant::handleRecordCreation — Tenant::$guarded
     * blocks subscription_plan_id and friends from mass-assignment so the
     * default Filament update silently drops them. Wrap the update in
     * Tenant::unguarded so the form save persists every field the
     * SuperAdmin actually edited, including the plan. The Tenant model's
     * updating hook then syncs tenant.features from the new plan unless
     * the form also touched features explicitly (Manage Modules flow).
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        Tenant::unguarded(function () use ($record, $data) {
            $record->fill($data);
            $record->save();
        });

        return $record;
    }

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
