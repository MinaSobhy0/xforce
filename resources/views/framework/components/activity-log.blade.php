@props([
    'model',
    'activities' => null,
    'showActions' => true,
    'maxHeight' => '400px'
])

@php
    $activities = $activities ?? ($model?->activities()->with('causer')->latest()->get() ?? collect());
    $isRtl = app()->getLocale() === 'ar';
@endphp

<div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
    <!-- Header -->
    <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
            {{ __('Activity Log') }}
        </h3>

        @if($showActions)
            <div class="flex gap-2">
                <button
                    type="button"
                    wire:click="$emit('openLogNoteModal')"
                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600"
                >
                    <x-heroicon-s-pencil class="w-3 h-3 {{ $isRtl ? 'ml-1' : 'mr-1' }}"/>
                    {{ __('Log Note') }}
                </button>

                <button
                    type="button"
                    wire:click="$emit('openScheduleActivityModal')"
                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"
                >
                    <x-heroicon-s-calendar class="w-3 h-3 {{ $isRtl ? 'ml-1' : 'mr-1' }}"/>
                    {{ __('Schedule Activity') }}
                </button>
            </div>
        @endif
    </div>

    <!-- Activities Timeline -->
    <div class="p-4" style="max-height: {{ $maxHeight }}; overflow-y: auto;">
        @if($activities->count())
            <div class="flow-root">
                <ul role="list" class="space-y-6">
                    @foreach($activities as $activity)
                        <li class="relative flex gap-x-4">
                            <!-- Activity Icon/Avatar -->
                            <div class="relative flex h-8 w-8 flex-none items-center justify-center">
                                @if($activity->causer?->avatar_url)
                                    <img
                                        class="h-8 w-8 rounded-full"
                                        src="{{ $activity->causer->avatar_url }}"
                                        alt="{{ $activity->causer->display_name }}"
                                    >
                                @else
                                    <div class="h-8 w-8 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                        @php
                                            $iconClass = match($activity->description) {
                                                'created' => 'text-green-600 dark:text-green-400',
                                                'updated' => 'text-blue-600 dark:text-blue-400',
                                                'deleted' => 'text-red-600 dark:text-red-400',
                                                default => 'text-gray-600 dark:text-gray-400'
                                            };
                                            $icon = match($activity->description) {
                                                'created' => 'plus',
                                                'updated' => 'pencil',
                                                'deleted' => 'trash',
                                                default => 'chat-bubble-left-ellipsis'
                                            };
                                        @endphp
                                        <x-dynamic-component
                                            :component="'heroicon-s-' . $icon"
                                            class="h-4 w-4 {{ $iconClass }}"
                                        />
                                    </div>
                                @endif

                                @if(!$loop->last)
                                    <div class="absolute left-4 top-8 -ml-px mt-0.5 h-full w-0.5 bg-gray-200 dark:bg-gray-700"></div>
                                @endif
                            </div>

                            <!-- Activity Content -->
                            <div class="flex-auto rounded-md p-3 ring-1 ring-inset ring-gray-200 dark:ring-gray-700 bg-gray-50 dark:bg-gray-900/50">
                                <div class="flex justify-between gap-x-4">
                                    <div class="flex-auto">
                                        <!-- Activity Description -->
                                        <div class="text-sm leading-6 text-gray-900 dark:text-gray-100">
                                            <strong>{{ $activity->causer?->display_name ?? __('System') }}</strong>
                                            {{ $this->formatActivityDescription($activity) }}
                                        </div>

                                        <!-- Activity Properties -->
                                        @if($activity->properties && $activity->properties->count())
                                            <div class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                                                @foreach($activity->properties as $key => $value)
                                                    @if(is_scalar($value))
                                                        <div>
                                                            <span class="font-medium">{{ ucfirst($key) }}:</span>
                                                            <span class="font-mono bg-gray-200 dark:bg-gray-700 px-1 rounded">{{ $value }}</span>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif

                                        <!-- Changes (for updates) -->
                                        @if($activity->description === 'updated' && $activity->changes)
                                            <div class="mt-2 text-xs">
                                                @foreach($activity->changes['attributes'] ?? [] as $field => $newValue)
                                                    @php
                                                        $oldValue = $activity->changes['old'][$field] ?? null;
                                                    @endphp
                                                    <div class="flex items-center gap-2 py-1">
                                                        <span class="font-medium text-gray-600 dark:text-gray-400">
                                                            {{ ucfirst(str_replace('_', ' ', $field)) }}:
                                                        </span>
                                                        @if($oldValue)
                                                            <span class="bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300 px-1 rounded text-xs line-through">
                                                                {{ $oldValue }}
                                                            </span>
                                                        @endif
                                                        <span class="bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 px-1 rounded text-xs">
                                                            {{ $newValue }}
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Timestamp -->
                                    <time
                                        class="flex-none text-xs leading-5 text-gray-500 dark:text-gray-400"
                                        datetime="{{ $activity->created_at->toISOString() }}"
                                        title="{{ $activity->created_at->format('Y-m-d H:i:s') }}"
                                    >
                                        {{ $activity->created_at->diffForHumans() }}
                                    </time>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <!-- Empty State -->
            <div class="text-center py-8">
                <x-heroicon-o-chat-bubble-left-ellipsis class="mx-auto h-8 w-8 text-gray-400"/>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                    {{ __('No activities yet') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Activities and notes will appear here as they happen.') }}
                </p>
            </div>
        @endif
    </div>
</div>

@script
<script>
window.formatActivityDescription = function(activity) {
    const descriptions = {
        'created': '{{ __("created this record") }}',
        'updated': '{{ __("updated this record") }}',
        'deleted': '{{ __("deleted this record") }}',
        'restored': '{{ __("restored this record") }}',
        'note': '{{ __("added a note") }}',
        'email': '{{ __("sent an email") }}',
        'call': '{{ __("made a call") }}',
        'meeting': '{{ __("scheduled a meeting") }}'
    };

    return descriptions[activity.description] || activity.description;
};
</script>
@endscript