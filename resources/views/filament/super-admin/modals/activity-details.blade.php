<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Action</div>
            <div class="font-medium text-gray-900 dark:text-white">{{ $record->description }}</div>
        </div>

        <div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Admin</div>
            <div class="font-medium text-gray-900 dark:text-white">{{ $record->causer?->name ?? 'System' }}</div>
        </div>

        <div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Subject Type</div>
            <div class="font-medium text-gray-900 dark:text-white">{{ class_basename($record->subject_type) }}</div>
        </div>

        <div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Subject ID</div>
            <div class="font-medium text-gray-900 dark:text-white font-mono text-xs">{{ $record->subject_id }}</div>
        </div>

        <div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Timestamp</div>
            <div class="font-medium text-gray-900 dark:text-white">{{ $record->created_at->format('M j, Y g:i:s A') }}</div>
        </div>

        <div>
            <div class="text-sm text-gray-500 dark:text-gray-400">IP Address</div>
            <div class="font-medium text-gray-900 dark:text-white font-mono">{{ $record->properties['ip'] ?? '-' }}</div>
        </div>
    </div>

    @if ($record->properties)
        <div class="pt-4 border-t dark:border-gray-700">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">Properties</div>
            <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded-lg text-xs overflow-auto max-h-64">{{ json_encode($record->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    @endif
</div>
