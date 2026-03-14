@php
    $tenant = current_tenant();
    $showBanner = $tenant && $tenant->isInUserOverage();
    $daysRemaining = $showBanner ? $tenant->getUserOverageGraceDaysRemaining() : null;
    $isExpired = $showBanner && $tenant->isUserOverageGraceExpired();
    $currentUsers = $tenant?->usage?->users ?? 0;
    $limit = $tenant?->getEffectiveLimit('users') ?? 0;
@endphp

@if($showBanner)
    <div class="w-full {{ $isExpired ? 'bg-red-600' : 'bg-amber-500' }} text-white px-4 py-2 text-center text-sm font-medium">
        <div class="flex items-center justify-center gap-2 flex-wrap">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
            <span>
                @if($isExpired)
                    {{ __('auth::limits.banner.expired', ['current' => $currentUsers, 'limit' => $limit]) }}
                @else
                    {{ __('auth::limits.banner.warning', ['current' => $currentUsers, 'limit' => $limit, 'days' => max(0, $daysRemaining)]) }}
                @endif
            </span>
            <a href="{{ url('/admin/settings/subscription') }}"
               class="ml-2 inline-flex items-center gap-1 px-3 py-1 bg-white/20 hover:bg-white/30 rounded-md text-white text-xs font-semibold transition-colors">
                {{ __('auth::limits.banner.action') }}
                <x-heroicon-o-arrow-right class="w-4 h-4" />
            </a>
        </div>
    </div>
@endif
