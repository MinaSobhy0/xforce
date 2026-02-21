<?php

namespace Modules\Auth\Filament\Resources\UserResource\Pages;

use Modules\Auth\Filament\Resources\UserResource;
use Modules\Auth\Models\UserBranchRole;
use Modules\Auth\Models\Role;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Notifications\Notification;

class EditUser extends BaseEditRecord
{
    protected static string $resource = UserResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Update password change timestamp if password was changed
        if (!empty($data['password'])) {
            $data['password_changed_at'] = now();
        }

        // Update the updated_by field
        $data['updated_by'] = auth()->id();

        return $data;
    }

    protected function afterSave(): void
    {
        // Sync branch assignments
        $this->syncBranches($this->getRecord());

        $changes = $this->getRecord()->getChanges();

        // Log significant changes
        $significantChanges = array_intersect_key($changes, array_flip([
            'first_name', 'last_name', 'email', 'status', 'email_verified_at', 'two_factor_enabled'
        ]));

        if (!empty($significantChanges)) {
            activity()
                ->causedBy(auth()->user())
                ->performedOn($this->getRecord())
                ->withProperties(['changes' => $significantChanges])
                ->log('User profile updated');
        }

        // Send notifications for specific changes
        if (array_key_exists('status', $changes)) {
            $status = $changes['status'] === 'active' ? 'activated' : 'deactivated';

            Notification::make()
                ->title(__('User account :status', ['status' => $status]))
                ->success()
                ->send();
        }

        if (array_key_exists('email', $changes)) {
            // If email changed, mark as unverified
            $this->getRecord()->update([
                'email_verified_at' => null,
            ]);

            Notification::make()
                ->title(__('Email changed'))
                ->body(__('User email has been changed. Email verification reset.'))
                ->warning()
                ->send();
        }

        if (array_key_exists('password', $changes)) {
            Notification::make()
                ->title(__('Password updated'))
                ->body(__('User password has been changed successfully.'))
                ->success()
                ->send();
        }
    }

    /**
     * Sync branch assignments for the user.
     */
    protected function syncBranches($user): void
    {
        $branchIds = $this->data['branch_ids'] ?? [];

        // Get existing primary branch
        $existingPrimaryBranchId = UserBranchRole::where('user_id', $user->id)
            ->where('is_primary', true)
            ->value('branch_id');

        // Get default role (first user role or default)
        $defaultRoleId = $user->roles->first()?->id ?? Role::where('name', 'user')->first()?->id;

        // Delete branch assignments that are no longer selected
        UserBranchRole::where('user_id', $user->id)
            ->whereNotIn('branch_id', $branchIds)
            ->delete();

        // Get existing branch IDs
        $existingBranchIds = UserBranchRole::where('user_id', $user->id)
            ->pluck('branch_id')
            ->toArray();

        // Create new assignments for branches that don't exist yet
        $newBranchIds = array_diff($branchIds, $existingBranchIds);

        foreach ($newBranchIds as $branchId) {
            // Set as primary if it was the old primary or if there's no primary yet
            $isPrimary = ($branchId === $existingPrimaryBranchId) ||
                         (!$existingPrimaryBranchId && empty($existingBranchIds) && $branchId === reset($branchIds));

            UserBranchRole::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'branch_id' => $branchId,
                'role_id' => $defaultRoleId,
                'is_primary' => $isPrimary,
                'is_active' => true,
                'assigned_at' => now(),
                'assigned_by' => auth()->id(),
            ]);
        }

        // Ensure at least one branch is primary if branches exist
        if (!empty($branchIds)) {
            $hasPrimary = UserBranchRole::where('user_id', $user->id)
                ->where('is_primary', true)
                ->exists();

            if (!$hasPrimary) {
                UserBranchRole::where('user_id', $user->id)
                    ->where('branch_id', $branchIds[0])
                    ->update(['is_primary' => true]);
            }
        }
    }
}