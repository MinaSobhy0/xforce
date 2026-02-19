<div class="space-y-6">
    {{-- Summary Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                {{ number_format($totalSent) }}
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Total Sent</div>
        </div>

        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-success-600 dark:text-success-400">
                {{ number_format($totalRead) }}
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Total Read</div>
        </div>

        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-info-600 dark:text-info-400">
                {{ $readRate }}%
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Read Rate</div>
        </div>
    </div>

    {{-- Timeline --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Timeline</h4>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
                <dt class="text-gray-500 dark:text-gray-400">Created</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $record->created_at->format('M d, Y H:i') }}</dd>
            </div>
            @if($record->scheduled_at)
            <div class="flex justify-between">
                <dt class="text-gray-500 dark:text-gray-400">Scheduled For</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $record->scheduled_at->format('M d, Y H:i') }}</dd>
            </div>
            @endif
            @if($record->sent_at)
            <div class="flex justify-between">
                <dt class="text-gray-500 dark:text-gray-400">Sent At</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $record->sent_at->format('M d, Y H:i') }}</dd>
            </div>
            @endif
        </dl>
    </div>

    {{-- Delivery Breakdown --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Delivery Method</h4>
        <div class="flex items-center gap-2">
            @php
                $methodIcon = match($record->delivery_method) {
                    'in_app' => 'heroicon-o-bell',
                    'email' => 'heroicon-o-envelope',
                    'both' => 'heroicon-o-globe-alt',
                    default => 'heroicon-o-megaphone',
                };
                $methodLabel = \App\Models\Announcement::DELIVERY_METHODS[$record->delivery_method] ?? $record->delivery_method;
            @endphp
            <x-filament::icon :icon="$methodIcon" class="w-5 h-5 text-gray-400" />
            <span class="text-gray-900 dark:text-gray-100">{{ $methodLabel }}</span>
        </div>
    </div>

    {{-- Target Plans --}}
    @if(!empty($record->target_plans))
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Targeted Plans</h4>
        <div class="flex flex-wrap gap-2">
            @foreach($record->target_plans as $planId)
                @php
                    $plan = \App\Models\SubscriptionPlan::find($planId);
                @endphp
                @if($plan)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-info-100 text-info-800 dark:bg-info-800 dark:text-info-100">
                    {{ $plan->code }}
                </span>
                @endif
            @endforeach
        </div>
    </div>
    @else
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Targeted Plans</h4>
        <span class="text-gray-500 dark:text-gray-400 text-sm">All plans</span>
    </div>
    @endif
</div>
