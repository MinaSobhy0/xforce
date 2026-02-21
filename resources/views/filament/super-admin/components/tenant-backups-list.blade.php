@php
    use Illuminate\Support\Facades\Storage;

    // Get tenant ID from the record
    $record = $getRecord();
    $tenantId = $record->id;
    $backupsPath = "backups/tenants/{$tenantId}";
    $backups = [];

    if (Storage::disk('local')->exists($backupsPath)) {
        $directories = Storage::disk('local')->directories($backupsPath);

        foreach ($directories as $dir) {
            $manifestPath = "{$dir}/manifest.json";
            if (Storage::disk('local')->exists($manifestPath)) {
                $manifest = json_decode(Storage::disk('local')->get($manifestPath), true);

                $totalSize = 0;
                $components = [];
                foreach ($manifest['components'] ?? [] as $name => $info) {
                    $components[] = ucfirst($name);
                    $totalSize += $info['size'] ?? 0;
                }

                // Format size
                $sizeFormatted = $totalSize;
                $units = ['B', 'KB', 'MB', 'GB'];
                $i = 0;
                while ($sizeFormatted >= 1024 && $i < count($units) - 1) {
                    $sizeFormatted /= 1024;
                    $i++;
                }
                $sizeFormatted = round($sizeFormatted, 2) . ' ' . $units[$i];

                $backups[] = [
                    'path' => $dir,
                    'timestamp' => basename($dir),
                    'created_at' => $manifest['created_at'] ?? null,
                    'components' => implode(', ', $components),
                    'size' => $sizeFormatted,
                    'version' => $manifest['version'] ?? 'Unknown',
                ];
            }
        }

        // Sort by timestamp descending
        usort($backups, fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));
    }
@endphp

<style>
    [x-cloak] { display: none !important; }
</style>

<div class="space-y-4" wire:poll.30s>
    @if (count($backups) === 0)
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mx-auto h-12 w-12 text-gray-400">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
            </svg>
            <p class="mt-2 text-sm font-medium">No backups available</p>
            <p class="text-xs">Click "Create Backup Now" to create the first backup.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <table class="w-full text-sm text-left">
                <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Date</th>
                        <th class="px-4 py-3 font-semibold">Components</th>
                        <th class="px-4 py-3 font-semibold">Size</th>
                        <th class="px-4 py-3 font-semibold">Version</th>
                        <th class="px-4 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($backups as $index => $backup)
                        @php
                            $dateFormatted = $backup['created_at']
                                ? \Carbon\Carbon::parse($backup['created_at'])->format('M d, Y H:i')
                                : str_replace('_', ' ', $backup['timestamp']);
                            $uniqueId = 'backup-' . $index;
                        @endphp
                        <tr class="bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                            wire:key="{{ $uniqueId }}">
                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $dateFormatted }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @foreach (explode(', ', $backup['components']) as $component)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 mr-1">
                                        {{ $component }}
                                    </span>
                                @endforeach
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 font-mono text-xs">
                                {{ $backup['size'] }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs">{{ $backup['version'] }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    {{-- Restore Button with Confirmation --}}
                                    <div x-data="{ confirming: false }" class="relative">
                                        <button
                                            x-show="!confirming"
                                            @click="confirming = true"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border transition-all"
                                            style="background-color: #fffbeb; border-color: #fcd34d; color: #b45309;"
                                            onmouseover="this.style.backgroundColor='#fef3c7'"
                                            onmouseout="this.style.backgroundColor='#fffbeb'"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                            Restore
                                        </button>

                                        <div
                                            x-show="confirming"
                                            x-cloak
                                            x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            class="flex items-center gap-1.5 rounded-lg px-2 py-1 border"
                                            style="background-color: #fffbeb; border-color: #fcd34d;"
                                        >
                                            <span class="text-xs font-medium whitespace-nowrap" style="color: #b45309;">Restore?</span>
                                            <button
                                                type="button"
                                                wire:click="restoreBackup('{{ $backup['path'] }}')"
                                                wire:loading.attr="disabled"
                                                @click="confirming = false"
                                                class="px-2.5 py-1 text-xs font-semibold rounded-md transition-colors disabled:opacity-50"
                                                style="background-color: #d97706; color: #ffffff;"
                                                onmouseover="this.style.backgroundColor='#b45309'"
                                                onmouseout="this.style.backgroundColor='#d97706'"
                                            >
                                                <span wire:loading.remove wire:target="restoreBackup('{{ $backup['path'] }}')">Yes</span>
                                                <span wire:loading wire:target="restoreBackup('{{ $backup['path'] }}')">...</span>
                                            </button>
                                            <button
                                                type="button"
                                                @click="confirming = false"
                                                class="px-2.5 py-1 text-xs font-medium rounded-md border transition-colors"
                                                style="background-color: #f3f4f6; color: #374151; border-color: #d1d5db;"
                                            >
                                                No
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Delete Button with Confirmation --}}
                                    <div x-data="{ confirming: false }" class="relative">
                                        <button
                                            x-show="!confirming"
                                            @click="confirming = true"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border transition-all"
                                            style="background-color: #fef2f2; border-color: #fca5a5; color: #b91c1c;"
                                            onmouseover="this.style.backgroundColor='#fee2e2'"
                                            onmouseout="this.style.backgroundColor='#fef2f2'"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            Delete
                                        </button>

                                        <div
                                            x-show="confirming"
                                            x-cloak
                                            x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            class="flex items-center gap-1.5 rounded-lg px-2 py-1 border"
                                            style="background-color: #fef2f2; border-color: #fca5a5;"
                                        >
                                            <span class="text-xs font-medium whitespace-nowrap" style="color: #b91c1c;">Delete?</span>
                                            <button
                                                type="button"
                                                wire:click="deleteBackup('{{ $backup['path'] }}')"
                                                wire:loading.attr="disabled"
                                                @click="confirming = false"
                                                class="px-2.5 py-1 text-xs font-semibold rounded-md transition-colors disabled:opacity-50"
                                                style="background-color: #dc2626; color: #ffffff;"
                                                onmouseover="this.style.backgroundColor='#b91c1c'"
                                                onmouseout="this.style.backgroundColor='#dc2626'"
                                            >
                                                <span wire:loading.remove wire:target="deleteBackup('{{ $backup['path'] }}')">Yes</span>
                                                <span wire:loading wire:target="deleteBackup('{{ $backup['path'] }}')">...</span>
                                            </button>
                                            <button
                                                type="button"
                                                @click="confirming = false"
                                                class="px-2.5 py-1 text-xs font-medium rounded-md border transition-colors"
                                                style="background-color: #f3f4f6; color: #374151; border-color: #d1d5db;"
                                            >
                                                No
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {{ count($backups) }} backup(s) available. Auto-refreshes every 30 seconds.
        </p>
    @endif
</div>
