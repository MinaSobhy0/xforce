<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Status Banner --}}
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                <span class="font-medium text-green-700 dark:text-green-400">STATUS: Running</span>
            </div>
            <div class="flex gap-2">
                <x-filament::button size="sm" color="warning" wire:click="pauseQueue" icon="heroicon-o-pause">
                    Pause Queue
                </x-filament::button>
            </div>
        </div>

        {{-- Stats Cards --}}
        @php $stats = $this->getQueueStats(); @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['jobs_per_minute'] }}</div>
                <div class="text-sm text-gray-500">Jobs/min</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['pending'] }}</div>
                <div class="text-sm text-gray-500">Pending</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold {{ $stats['failed_24h'] > 0 ? 'text-red-500' : 'text-gray-900 dark:text-white' }}">
                    {{ $stats['failed_24h'] }}
                </div>
                <div class="text-sm text-gray-500">Failed (24h)</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['completed_24h']) }}</div>
                <div class="text-sm text-gray-500">Completed (24h)</div>
            </x-filament::section>
        </div>

        {{-- Queues Table --}}
        <x-filament::section>
            <x-slot name="heading">Queues</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="text-left py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Queue</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Pending</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Completed</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Failed</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Throughput</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->getQueues() as $queue)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-3 px-4 font-medium text-gray-900 dark:text-white">{{ $queue['name'] }}</td>
                                <td class="text-right py-3 px-4 font-mono">{{ $queue['pending'] }}</td>
                                <td class="text-right py-3 px-4 font-mono text-green-600">{{ number_format($queue['completed']) }}</td>
                                <td class="text-right py-3 px-4 font-mono {{ $queue['failed'] > 0 ? 'text-red-500' : '' }}">
                                    {{ $queue['failed'] }}
                                </td>
                                <td class="text-right py-3 px-4 font-mono text-gray-500">{{ $queue['throughput'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Recent Failed Jobs --}}
        <x-filament::section>
            <x-slot name="heading">Recent Failed Jobs</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="text-left py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Job</th>
                            <th class="text-left py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Tenant</th>
                            <th class="text-left py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Error</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Failed At</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->getRecentFailedJobs() as $job)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-3 px-4 font-medium text-gray-900 dark:text-white">{{ $job['job'] }}</td>
                                <td class="py-3 px-4 text-gray-600 dark:text-gray-400">{{ $job['tenant'] }}</td>
                                <td class="py-3 px-4 text-red-500">{{ $job['error'] }}</td>
                                <td class="text-right py-3 px-4 text-gray-500">{{ $job['failed_at']->diffForHumans() }}</td>
                                <td class="text-right py-3 px-4">
                                    <x-filament::button size="xs" color="warning" icon="heroicon-o-arrow-path">
                                        Retry
                                    </x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
