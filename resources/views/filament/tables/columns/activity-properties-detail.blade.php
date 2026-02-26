@php
    $properties = $getState();
@endphp

<div class="text-sm">
    @if(!empty($properties))
        @if(isset($properties['old']) && isset($properties['attributes']))
            <div class="space-y-3">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="py-2 px-3 font-medium text-gray-700 dark:text-gray-300">{{ __('core::activity.changes_display.field') }}</th>
                            <th class="py-2 px-3 font-medium text-gray-700 dark:text-gray-300">{{ __('core::activity.changes_display.old_value') }}</th>
                            <th class="py-2 px-3 font-medium text-gray-700 dark:text-gray-300">{{ __('core::activity.changes_display.new_value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($properties['attributes'] as $key => $newValue)
                            @php
                                $oldValue = $properties['old'][$key] ?? null;
                            @endphp
                            @if($oldValue !== $newValue)
                                <tr class="border-b dark:border-gray-700">
                                    <td class="py-2 px-3 font-medium text-gray-600 dark:text-gray-400">
                                        {{ str($key)->headline() }}
                                    </td>
                                    <td class="py-2 px-3">
                                        <span class="text-red-600 dark:text-red-400">
                                            @if(is_null($oldValue))
                                                <span class="italic text-gray-400">{{ __('empty') }}</span>
                                            @elseif(is_array($oldValue))
                                                <code class="text-xs bg-red-50 dark:bg-red-900/30 px-1 py-0.5 rounded">{{ json_encode($oldValue) }}</code>
                                            @elseif(is_bool($oldValue))
                                                {{ $oldValue ? __('Yes') : __('No') }}
                                            @else
                                                {{ $oldValue }}
                                            @endif
                                        </span>
                                    </td>
                                    <td class="py-2 px-3">
                                        <span class="text-green-600 dark:text-green-400">
                                            @if(is_null($newValue))
                                                <span class="italic text-gray-400">{{ __('empty') }}</span>
                                            @elseif(is_array($newValue))
                                                <code class="text-xs bg-green-50 dark:bg-green-900/30 px-1 py-0.5 rounded">{{ json_encode($newValue) }}</code>
                                            @elseif(is_bool($newValue))
                                                {{ $newValue ? __('Yes') : __('No') }}
                                            @else
                                                {{ $newValue }}
                                            @endif
                                        </span>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif(isset($properties['attributes']))
            <div class="space-y-2">
                <p class="text-gray-500 dark:text-gray-400 text-sm mb-2">{{ __('New values:') }}</p>
                <table class="w-full text-left border-collapse">
                    <tbody>
                        @foreach($properties['attributes'] as $key => $value)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2 px-3 font-medium text-gray-600 dark:text-gray-400 w-1/3">
                                    {{ str($key)->headline() }}
                                </td>
                                <td class="py-2 px-3 text-green-600 dark:text-green-400">
                                    @if(is_null($value))
                                        <span class="italic text-gray-400">{{ __('empty') }}</span>
                                    @elseif(is_array($value))
                                        <code class="text-xs bg-gray-50 dark:bg-gray-900/30 px-1 py-0.5 rounded">{{ json_encode($value) }}</code>
                                    @elseif(is_bool($value))
                                        {{ $value ? __('Yes') : __('No') }}
                                    @else
                                        {{ $value }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                <code class="text-xs whitespace-pre-wrap">{{ json_encode($properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code>
            </div>
        @endif
    @else
        <p class="text-gray-400 italic">{{ __('core::activity.changes_display.no_changes') }}</p>
    @endif
</div>
