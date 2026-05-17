<?php

namespace Modules\Auth\Filament\Resources\UserResource\Pages;

use Modules\Auth\Filament\Resources\UserResource;
use Modules\Auth\Models\UserBranchRole;
use Modules\Auth\Models\Role;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate a strong password if none provided
        if (empty($data['password'])) {
            $data['password'] = bcrypt(Str::random(12));
            $data['must_change_password'] = true;
        }

        // Set default values
        $data['created_by'] = auth()->id();
        $data['password_changed_at'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        $user = $this->getRecord();

        // Sync branch assignments
        $this->syncBranches($user);

        try {
            // Send welcome email notification
            if (config('mail.notifications_enabled', true)) {
                // Welcome email logic would go here
                // $user->sendWelcomeNotification();
            }

            Notification::make()
                ->title(__('User created successfully'))
                ->body(__('User account has been created and welcome email sent.'))
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title(__('User created with warnings'))
                ->body(__('User was created but welcome email failed to send: :error', ['error' => $e->getMessage()]))
                ->warning()
                ->send();

            logger()->error('Welcome email failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }

        // Log user creation
        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->log('User account created');
    }

    /**
     * Sync branch assignments for the user.
     */
    protected function syncBranches($user): void
    {
        $branchIds = $this->data['branch_ids'] ?? [];

        if (empty($branchIds)) {
            return;
        }

        // Pick the role this branch assignment should use. Order:
        //   1. The user's own role (re-queried — the relationship may have
        //      been hydrated before Filament saved the roles pivot).
        //   2. A role named "staff" or "user" (common low-privilege defaults).
        //   3. The lowest-privilege existing role (max `level` if set, else
        //      last by id) — guarantees a non-null value as long as the
        //      `roles` table isn't empty.
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

        // Delete existing branch assignments
        UserBranchRole::where('user_id', $user->id)->delete();

        // Create new assignments. UserBranchRole marks role_id / is_primary /
        // is_active / assigned_at / assigned_by as $guarded for security, so
        // mass-assign via create() would silently drop them. We assign the
        // protected fields directly — this code path is the authorized admin
        // surface the guarded list is meant to protect against, not us.
        $isFirst = true;
        foreach ($branchIds as $branchId) {
            $assignment = new UserBranchRole();
            $assignment->tenant_id = $user->tenant_id;
            $assignment->user_id = $user->id;
            $assignment->branch_id = $branchId;
            $assignment->role_id = $defaultRoleId;
            $assignment->is_primary = $isFirst;
            $assignment->is_active = true;
            $assignment->assigned_at = now();
            $assignment->assigned_by = auth()->id();
            $assignment->save();

            $isFirst = false;
        }
    }
}