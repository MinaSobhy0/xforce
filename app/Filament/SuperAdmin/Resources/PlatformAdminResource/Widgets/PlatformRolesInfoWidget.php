<?php

namespace App\Filament\SuperAdmin\Resources\PlatformAdminResource\Widgets;

use Filament\Widgets\Widget;

class PlatformRolesInfoWidget extends Widget
{
    protected static string $view = 'filament.super-admin.widgets.platform-roles-info';

    protected int | string | array $columnSpan = 'full';

    public function getRoles(): array
    {
        return [
            [
                'name' => 'Super Admin',
                'description' => 'Full access to everything',
                'color' => 'danger',
            ],
            [
                'name' => 'Support Lead',
                'description' => 'Tenants + tickets + login-as (no billing, no plans)',
                'color' => 'warning',
            ],
            [
                'name' => 'Support',
                'description' => 'Tickets only + read-only tenant info',
                'color' => 'info',
            ],
            [
                'name' => 'Billing',
                'description' => 'Invoices + subscriptions + revenue (no tenant access)',
                'color' => 'success',
            ],
            [
                'name' => 'Read Only',
                'description' => 'Dashboard + analytics (no actions)',
                'color' => 'gray',
            ],
        ];
    }
}
