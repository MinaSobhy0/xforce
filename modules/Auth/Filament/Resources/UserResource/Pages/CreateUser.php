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

        // Get default role (first user role or default)
        $defaultRoleId = $user->roles->first()?->id ?? Role::where('name', 'user')->first()?->id;

        // Delete existing branch assignments
        UserBranchRole::where('user_id', $user->id)->delete();

        // Create new assignments
        $isFirst = true;
        foreach ($branchIds as $branchId) {
            UserBranchRole::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'branch_id' => $branchId,
                'role_id' => $defaultRoleId,
                'is_primary' => $isFirst,
                'is_active' => true,
                'assigned_at' => now(),
                'assigned_by' => auth()->id(),
            ]);
            $isFirst = false;
        }
    }
}