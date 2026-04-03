<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6">
        @if($connections->isEmpty())
            <x-filament::section>
                <div class="text-center py-8">
                    <x-heroicon-o-server-stack class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                        {{ __('odoo-integration::odoo.empty.no_connections') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('odoo-integration::odoo.empty.no_connections_description') }}
                    </p>
                    <div class="mt-6">
                        <x-filament::button
                            :href="route('filament.tenant.resources.odoo-connections.create')"
                            tag="a"
                            icon="heroicon-o-plus"
                        >
                            {{ __('odoo-integration::odoo.actions.create_connection') }}
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('odoo-integration::odoo.sections.recent_activity') }}
                </x-slot>

                <div class="space-y-4">
                    @forelse($recentLogs as $log)
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    @if($log->status->value === 'completed')
                                        <x-heroicon-o-check-circle class="h-6 w-6 text-success-500" />
                                    @elseif($log->status->value === 'failed')
                                        <x-heroicon-o-x-circle class="h-6 w-6 text-danger-500" />
                                    @elseif($log->status->value === 'running')
                                        <x-heroicon-o-arrow-path class="h-6 w-6 text-info-500 animate-spin" />
                                    @else
                                        <x-heroicon-o-clock class="h-6 w-6 text-gray-400" />
                                    @endif
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $log->entityMapping?->name ?? __('odoo-integration::odoo.labels.all_entities') }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $log->sync_type }} · {{ $log->direction }}
                                        · {{ $log->records_processed }} {{ __('odoo-integration::odoo.labels.records') }}
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $log->started_at?->diffForHumans() }}
                                </p>
                                @if($log->duration)
                                    <p class="text-xs text-gray-400">
                                        {{ $log->duration }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                            {{ __('odoo-integration::odoo.empty.no_recent_activity') }}
                        </p>
                    @endforelse
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
