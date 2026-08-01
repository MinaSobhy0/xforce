<?php

namespace App\Filament\OwnerPortal\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class SubscriptionStatusWidget extends Widget
{
    protected static string $view = 'filament.owner-portal.widgets.subscription-status';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public function getSubscriptionData(): array
    {
        $user = Auth::user();
        $tenant = $user?->tenant;

        if (! $tenant) {
            return [
                'plan_name' => 'No Plan',
                'status' => 'inactive',
                'billing_cycle' => '-',
                'next_invoice' => '-',
                'days_remaining' => 0,
                'is_trial' => false,
            ];
        }

        $plan = $tenant->plan;
        $isTrial = $tenant->subscription_status === 'trial';
        $trialEndsAt = $tenant->trial_ends_at;
        $subscriptionExpiresAt = $tenant->subscription_expires_at;

        $daysRemaining = 0;
        if ($isTrial && $trialEndsAt) {
            $daysRemaining = now()->diffInDays($trialEndsAt, false);
        } elseif ($subscriptionExpiresAt) {
            $daysRemaining = now()->diffInDays($subscriptionExpiresAt, false);
        }

        return [
            'plan_name' => $plan?->name ?? 'Free',
            'status' => $tenant->subscription_status ?? 'active',
            // Default billing cycle is annual (yearly) for all clients; a real
            // TenantSubscription cycle overrides it when one exists.
            'billing_cycle' => $tenant->subscription?->billing_cycle?->value ?? 'yearly',
            'next_invoice' => $subscriptionExpiresAt?->format('M d, Y') ?? '-',
            'days_remaining' => max(0, $daysRemaining),
            'is_trial' => $isTrial,
            'trial_ends_at' => $trialEndsAt?->format('M d, Y'),
        ];
    }
}
