<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            My Restore Requests
        </x-slot>

        @php
            $requests = $this->getRestoreRequests();
        @endphp

        @if($requests->isEmpty())
            <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                <x-heroicon-o-inbox class="w-8 h-8 mx-auto mb-2 opacity-50" />
                <p class="text-sm">No restore requests yet</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($requests as $request)
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ $request->backup?->name ?? 'Unknown Backup' }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Requested {{ $request->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-filament::badge :color="$this->getStatusColor($request->status)">
                                {{ \App\Models\RestoreRequest::STATUSES[$request->status] ?? $request->status }}
                            </x-filament::badge>
                        </div>
                    </div>

                    @if($request->admin_notes && in_array($request->status, ['rejected', 'failed']))
                        <div class="ml-4 p-2 bg-red-50 dark:bg-red-900/20 rounded text-sm text-red-700 dark:text-red-300">
                            <strong>Admin Note:</strong> {{ $request->admin_notes }}
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
