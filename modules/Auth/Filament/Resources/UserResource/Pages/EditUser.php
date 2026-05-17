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

        // Pick the role this branch assignment should use. Order:
        //   1. The user's own role (re-queried — the relationship may have
        //      been hydrated before Filament saved the roles pivot).
        //   2. A role named "staff" or "user" (common low-privilege defaults).
        //   3. The lowest-privilege existing role.
        $defaultRoleId = $user->roles()->value('roles.id')
            ?? Role::whereIn('name', ['staff', 'user'])->orderBy('id')->value('id')
            ?? Role::query()->orderByDesc('level')->orderByDesc('id')->value('id');

        if (! $defaultRoleId) {
            \Filament\Notifications\Notification::make()
                ->title(__('auth::auth.errors.no_role_for_branch_assignment') ?: 'No role available to assign with branch')
                ->body('Assign a role to the user (or create one in Roles) before linking branches.')
                ->danger()
                ->send();

            return;
        }

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

            // role_id / is_primary / is_active / assigned_at / assigned_by are
            // $guarded on UserBranchRole — mass-assign via create() drops them.
            // Assign directly: this admin path is the authorized surface those
            // restrictions are meant to protect against, not us.
            $assignment = new UserBranchRole();
            $assignment->tenant_id = $user->tenant_id;
            $assignment->user_id = $user->id;
            $assignment->branch_id = $branchId;
            $assignment->role_id = $defaultRoleId;
            $assignment->is_primary = $isPrimary;
            $assignment->is_active = true;
            $assignment->assigned_at = now();
            $assignment->assigned_by = auth()->id();
            $assignment->save();
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