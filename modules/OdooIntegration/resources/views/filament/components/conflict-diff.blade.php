<div class="space-y-6">
    <div class="grid grid-cols-2 gap-6">
        {{-- Local Data --}}
        <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center">
                <x-heroicon-o-computer-desktop class="h-5 w-5 mr-2" />
                {{ __('odoo-integration::odoo.labels.local_data') }}
            </h3>
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 overflow-auto max-h-96">
                @if($conflict->local_data)
                    <dl class="space-y-2">
                        @foreach($conflict->local_data as $key => $value)
                            <div class="flex justify-between {{ isset($conflict->diff[$key]) ? 'bg-yellow-50 dark:bg-yellow-900/20 -mx-2 px-2 py-1 rounded' : '' }}">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $key }}</dt>
                                <dd class="text-sm text-gray-900 dark:text-white">
                                    @if(is_array($value))
                                        <code class="text-xs">{{ json_encode($value) }}</code>
                                    @elseif(is_bool($value))
                                        {{ $value ? 'true' : 'false' }}
                                    @else
                                        {{ $value ?? '-' }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400 italic">
                        {{ __('odoo-integration::odoo.labels.record_deleted') }}
                    </p>
                @endif
            </div>
        </div>

        {{-- Odoo Data --}}
        <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center">
                <x-heroicon-o-cloud class="h-5 w-5 mr-2" />
                {{ __('odoo-integration::odoo.labels.odoo_data') }}
            </h3>
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 overflow-auto max-h-96">
                @if($conflict->odoo_data)
                    <dl class="space-y-2">
                        @foreach($conflict->odoo_data as $key => $value)
                            <div class="flex justify-between {{ isset($conflict->diff[$key]) ? 'bg-yellow-50 dark:bg-yellow-900/20 -mx-2 px-2 py-1 rounded' : '' }}">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $key }}</dt>
                                <dd class="text-sm text-gray-900 dark:text-white">
                                    @if(is_array($value))
                                        <code class="text-xs">{{ json_encode($value) }}</code>
                                    @elseif(is_bool($value))
                                        {{ $value ? 'true' : 'false' }}
                                    @else
                                        {{ $value ?? '-' }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400 italic">
                        {{ __('odoo-integration::odoo.labels.record_deleted') }}
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- Changed Fields Summary --}}
    @if($conflict->diff && count($conflict->diff) > 0)
        <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                {{ __('odoo-integration::odoo.labels.changed_fields') }}
            </h3>
            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
                <ul class="space-y-2">
                    @foreach($conflict->diff as $field => $changes)
                        <li class="flex items-start space-x-2">
                            <x-heroicon-o-arrow-right class="h-4 w-4 text-yellow-600 mt-0.5 flex-shrink-0" />
                            <div>
                                <span class="font-medium">{{ $field }}:</span>
                                <span class="text-gray-600 dark:text-gray-300">
                                    @if(is_array($changes['local'] ?? null))
                                        {{ json_encode($changes['local']) }}
                                    @else
                                        {{ $changes['local'] ?? 'null' }}
                                    @endif
                                </span>
                                <span class="text-gray-400 mx-1">→</span>
                                <span class="text-gray-600 dark:text-gray-300">
                                    @if(is_array($changes['odoo'] ?? null))
                                        {{ json_encode($changes['odoo']) }}
                                    @else
                                        {{ $changes['odoo'] ?? 'null' }}
                                    @endif
                                </span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</div>
