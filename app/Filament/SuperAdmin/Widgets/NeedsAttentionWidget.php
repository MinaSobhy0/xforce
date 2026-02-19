<?php

namespace App\Filament\SuperAdmin\Widgets;

use Modules\Core\Models\Tenant;
use App\Models\SupportTicket;
use Filament\Widgets\Widget;

class NeedsAttentionWidget extends Widget
{
    protected static string $view = 'filament.super-admin.widgets.needs-attention';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    public function getAlerts(): array
    {
        $alerts = [];

        // Overdue invoices / past due tenants
        $pastDue = Tenant::where('subscription_status', 'past_due')->count();
        if ($pastDue > 0) {
            $alerts[] = [
                'icon' => 'warning',
                'text' => "{$pastDue} invoices overdue",
                'color' => 'warning',
            ];
        }

        // Expiring trials
        $expiringTrials = Tenant::where('subscription_status', 'trial')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(3)])
            ->count();
        if ($expiringTrials > 0) {
            $alerts[] = [
                'icon' => 'clock',
                'text' => "{$expiringTrials} trials expiring in 3 days",
                'color' => 'warning',
            ];
        }

        // Suspended accounts
        $suspended = Tenant::where('subscription_status', 'suspended')
            ->orWhere('status', 'suspended')
            ->count();
        if ($suspended > 0) {
            $alerts[] = [
                'icon' => 'danger',
                'text' => "{$suspended} tenant(s) suspended",
                'color' => 'danger',
            ];
        }

        // Open support tickets
        $openTickets = SupportTicket::where('status', 'open')->count();
        if ($openTickets > 0) {
            $alerts[] = [
                'icon' => 'ticket',
                'text' => "{$openTickets} support tickets unresolved",
                'color' => 'warning',
            ];
        }

        return $alerts;
    }
}
