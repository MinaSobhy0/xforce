<?php

namespace Modules\Booking\Filament\Resources\PractitionerTimeOffResource\Pages;

use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Modules\Booking\Filament\Resources\PractitionerTimeOffResource;
use Modules\Booking\Models\PractitionerTimeOff;

class ListPractitionerTimeOff extends BaseListRecords
{
    protected static string $resource = PractitionerTimeOffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $user = auth()->user();
        $canViewAll = $user->can('practitioner_time_off.view') || $user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner', 'admin', 'manager']);

        // Resolve current user's staff profile id once for the "my time off" tab.
        $myStaffProfileId = \Modules\Staff\Models\StaffProfile::query()
            ->where('user_id', $user->id)
            ->value('id');

        $tabs = [];

        // My Time Off - always visible
        $tabs['my_time_off'] = Tab::make(__('booking::time_off.tabs.my_time_off'))
            ->modifyQueryUsing(fn (Builder $query) => $query->where('staff_profile_id', $myStaffProfileId))
            ->badge($myStaffProfileId ? $this->getModel()::where('staff_profile_id', $myStaffProfileId)->count() : 0)
            ->icon('heroicon-o-user');

        // Pending Approval - for managers/approvers
        if ($canViewAll) {
            $tabs['pending'] = Tab::make(__('booking::time_off.tabs.pending'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PractitionerTimeOff::STATUS_PENDING))
                ->badge($this->getModel()::where('status', PractitionerTimeOff::STATUS_PENDING)->count())
                ->badgeColor('warning')
                ->icon('heroicon-o-clock');
        }

        // Approved - for managers
        if ($canViewAll) {
            $tabs['approved'] = Tab::make(__('booking::time_off.tabs.approved'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PractitionerTimeOff::STATUS_APPROVED))
                ->badge($this->getModel()::where('status', PractitionerTimeOff::STATUS_APPROVED)->count())
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle');
        }

        // All Requests - for managers
        if ($canViewAll) {
            $tabs['all'] = Tab::make(__('booking::time_off.tabs.all'))
                ->badge($this->getModel()::count())
                ->icon('heroicon-o-queue-list');
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        $user = auth()->user();
        $canViewAll = $user->can('practitioner_time_off.view') || $user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner', 'admin', 'manager']);

        // For managers, default to pending; for regular users, default to my time off
        return $canViewAll ? 'pending' : 'my_time_off';
    }
}
