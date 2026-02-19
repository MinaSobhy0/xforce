<div class="space-y-4">
    <div class="flex items-center gap-2">
        @if ($record->type === 'info')
            <span class="text-blue-500">ℹ️</span>
        @elseif ($record->type === 'feature')
            <span class="text-green-500">🆕</span>
        @elseif ($record->type === 'maintenance')
            <span class="text-yellow-500">🔧</span>
        @elseif ($record->type === 'urgent')
            <span class="text-red-500">🚨</span>
        @endif
        <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ \App\Models\Announcement::TYPES[$record->type] ?? $record->type }}
        </span>
    </div>

    <div class="prose dark:prose-invert max-w-none">
        {!! $record->getTranslation('body', 'en') !!}
    </div>

    @if ($record->target_plans)
        <div class="pt-4 border-t dark:border-gray-700">
            <span class="text-sm text-gray-500 dark:text-gray-400">Target Plans:</span>
            <div class="flex gap-2 mt-1">
                @foreach ($record->target_plans as $planId)
                    <span class="px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 rounded">
                        {{ \App\Models\SubscriptionPlan::find($planId)?->code ?? $planId }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif
</div>
