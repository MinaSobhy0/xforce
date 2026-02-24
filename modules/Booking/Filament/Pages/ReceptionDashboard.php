<?php

namespace Modules\Booking\Filament\Pages;

use App\Services\BranchContext;
use App\Traits\ChecksResourcePermissions;
use Filament\Pages\Page;
use Modules\Booking\Services\ReceptionService;

class ReceptionDashboard extends Page
{
    use ChecksResourcePermissions;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $permissionKey = 'appointments';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'reception';

    protected static string $view = 'booking::filament.pages.reception-dashboard';

    public static function getNavigationLabel(): string
    {
        return __('booking::reception.navigation');
    }

    public function getTitle(): string
    {
        return __('booking::reception.title');
    }

    public function getHeading(): string
    {
        return __('booking::reception.heading') . ' - ' . today()->format('l, M d, Y');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \Modules\Booking\Filament\Widgets\ReceptionStatsWidget::class,
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        return $user->hasAnyRole([
            'receptionist',
            'admin',
            'manager',
            'super-admin',
            'super_admin',
            'owner',
            'tenant-owner',
            'tenant_owner',
            'doctor',
            'nurse',
        ]);
    }
}
