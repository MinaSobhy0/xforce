<?php

namespace App\Filament\SuperAdmin\Resources\OwnerUserResource\Pages;

use App\Filament\SuperAdmin\Resources\OwnerUserResource;
use App\Models\OwnerUser;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateOwnerUser extends CreateRecord
{
    protected static string $resource = OwnerUserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * The users.username column is NOT NULL UNIQUE but the form doesn't expose
     * it. Derive a stable, unique value from the email local-part and pad with
     * a numeric suffix if it collides — same pattern the tenant-side user
     * creation uses elsewhere.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['username']) && ! empty($data['email'])) {
            $base = Str::slug(Str::before((string) $data['email'], '@'), '_') ?: 'user';
            $candidate = $base;
            $suffix = 1;

            while (OwnerUser::query()->where('username', $candidate)->exists()) {
                $candidate = $base.$suffix;
                $suffix++;
            }

            $data['username'] = $candidate;
        }

        return $data;
    }

    /**
     * OwnerUser marks tenant_id and status as $guarded (security: don't let
     * arbitrary mass-assign reassign owners or reactivate suspended accounts).
     * The platform-admin Create flow is the trusted surface those guards are
     * supposed to protect against, not us — so set them directly after the
     * normal mass-assignment fill, then save.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $tenantId = $data['tenant_id'] ?? null;
        $status = $data['status'] ?? 'active';
        unset($data['tenant_id'], $data['status']);

        $record = new ($this->getModel())();
        $record->fill($data);

        if ($tenantId) {
            $record->tenant_id = $tenantId;
        }
        if ($status) {
            $record->status = $status;
        }

        $record->save();

        return $record;
    }
}
