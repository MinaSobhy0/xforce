@php
    $properties = $getState();
@endphp

<div class="text-xs text-gray-600 dark:text-gray-400">
    @if(!empty($properties))
        @if(isset($properties['old']) && isset($properties['attributes']))
            <div class="space-y-1">
                @foreach($properties['attributes'] as $key => $newValue)
                    @php
                        $oldValue = $properties['old'][$key] ?? null;
                    @endphp
                    @if($oldValue !== $newValue)
                        <div>
                            <span class="font-medium">{{ str($key)->headline() }}:</span>
                            <span class="line-through text-red-500">{{ is_array($oldValue) ? json_encode($oldValue) : $oldValue }}</span>
                            <span class="mx-1">&rarr;</span>
                            <span class="text-green-500">{{ is_array($newValue) ? json_encode($newValue) : $newValue }}</span>
                        </div>
                    @endif
                @endforeach
            </div>
        @elseif(isset($properties['attributes']))
            <div class="space-y-1">
                @foreach($properties['attributes'] as $key => $value)
                    <div>
                        <span class="font-medium">{{ str($key)->headline() }}:</span>
                        <span>{{ is_array($value) ? json_encode($value) : $value }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <code class="text-xs">{{ json_encode($properties, JSON_PRETTY_PRINT) }}</code>
        @endif
    @else
        <span class="text-gray-400">-</span>
    @endif
</div>
