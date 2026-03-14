@php
    $tenant = current_tenant();
    $showBanner = false;
    $daysRemaining = null;
    $isExpired = false;
    $currentUsers = 0;
    $limit = 0;

    if ($tenant) {
        $limit = $tenant->getEffectiveLimit('users');

        // Count users using raw query to bypass any model scopes
        // This ensures accurate count regardless of context
        $currentUsers = \Illuminate\Support\Facades\DB::connection('tenant')
            ->table('users')
            ->whereNull('deleted_at')
            ->count();

        // Show banner if over limit (real-time check)
        if ($limit !== null && $currentUsers > $limit) {
            $showBanner = true;

            // Check grace period if overage is being tracked
            if ($tenant->users_overage_at) {
                $daysRemaining = $tenant->getUserOverageGraceDaysRemaining();
                $isExpired = $tenant->isUserOverageGraceExpired();
            } else {
                // First time detecting overage - set it now
                $tenant->update([
                    'users_overage_at' => now(),
                    'users_overage_notified' => false,
                ]);
                $daysRemaining = 14;
            }
        }
    }
@endphp

@if($showBanner)
    <div class="w-full {{ $isExpired ? 'bg-red-600' : 'bg-amber-500' }} text-white px-4 py-2 text-center text-sm font-medium" style="z-index: 50;">
        <div class="flex items-center justify-center gap-2 flex-wrap">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <span>
                @if($isExpired)
                    {{ __('auth::limits.banner.expired', ['current' => $currentUsers, 'limit' => $limit]) }}
                @else
                    {{ __('auth::limits.banner.warning', ['current' => $currentUsers, 'limit' => $limit, 'days' => max(0, $daysRemaining ?? 14)]) }}
                @endif
            </span>
            <a href="{{ url('/admin/settings/subscription') }}"
               class="ml-2 inline-flex items-center gap-1 px-3 py-1 bg-white/20 hover:bg-white/30 rounded-md text-white text-xs font-semibold transition-colors">
                {{ __('auth::limits.banner.action') }}
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                </svg>
            </a>
        </div>
    </div>
@endif
