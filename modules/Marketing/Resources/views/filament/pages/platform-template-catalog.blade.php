@php
    /** @var \Illuminate\Support\Collection<\App\Models\PlatformWhatsAppTemplate> $catalog */
    /** @var \Illuminate\Support\Collection<\Modules\Marketing\Models\MessageTemplate> $adopted */
    $locale = app()->getLocale();
@endphp

<x-filament-panels::page>
    @if ($catalog->isEmpty())
        <x-filament::section>
            <x-slot name="heading">{{ __('marketing::whatsapp.catalog.empty_heading') }}</x-slot>
            <x-slot name="description">{{ __('marketing::whatsapp.catalog.empty_description') }}</x-slot>
        </x-filament::section>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($catalog as $tpl)
                @php
                    $isAdopted = $adopted->has($tpl->id);
                    $local = $isAdopted ? $adopted->get($tpl->id) : null;
                    $statusColor = match ($local?->meta_template_status) {
                        \Modules\Marketing\Models\MessageTemplate::META_STATUS_APPROVED => 'success',
                        \Modules\Marketing\Models\MessageTemplate::META_STATUS_PENDING => 'warning',
                        \Modules\Marketing\Models\MessageTemplate::META_STATUS_REJECTED => 'danger',
                        default => 'gray',
                    };
                @endphp

                <x-filament::section>
                    <div class="space-y-3">
                        {{-- Header: name + category badge --}}
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="font-medium text-gray-900 dark:text-gray-100 truncate">
                                    {{ $tpl->getTranslation('name', $locale) ?: $tpl->code }}
                                </div>
                                <div class="text-xs font-mono text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ $tpl->code }}
                                </div>
                            </div>
                            <x-filament::badge :color="match($tpl->category) {
                                'UTILITY' => 'info',
                                'MARKETING' => 'warning',
                                'AUTHENTICATION' => 'success',
                                default => 'gray',
                            }">
                                {{ $tpl->category }}
                            </x-filament::badge>
                        </div>

                        {{-- Body preview with variable highlighting --}}
                        @php
                            $body = $tpl->getTranslation('body', $locale) ?: '';
                            $bodyHighlighted = preg_replace(
                                '/\{\{([a-zA-Z0-9_]+)\}\}/',
                                '<span class="rounded bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300 px-1 font-mono text-xs">{{$1}}</span>',
                                e($body)
                            );
                        @endphp
                        <div class="text-sm whitespace-pre-wrap text-gray-700 dark:text-gray-300">{!! $bodyHighlighted !!}</div>

                        {{-- Footer (if present) --}}
                        @if ($footer = $tpl->getTranslation('footer', $locale))
                            <div class="text-xs italic text-gray-500 dark:text-gray-400 border-t pt-2">{{ $footer }}</div>
                        @endif

                        {{-- Buttons preview --}}
                        @if (! empty($tpl->buttons_json))
                            <div class="flex flex-wrap gap-1">
                                @foreach (array_slice($tpl->buttons_json, 0, 3) as $btn)
                                    <span class="px-2 py-0.5 rounded border border-gray-200 dark:border-gray-700 text-xs text-gray-600 dark:text-gray-400">
                                        {{ $btn['label'] ?? '—' }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        {{-- Action row --}}
                        <div class="flex items-center justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                            <div>
                                @if ($isAdopted)
                                    <div class="flex items-center gap-2">
                                        <x-filament::badge color="success" icon="heroicon-o-check-circle">
                                            {{ __('marketing::whatsapp.catalog.status_adopted') }}
                                        </x-filament::badge>
                                        @if ($local?->meta_template_status)
                                            <x-filament::badge :color="$statusColor" size="sm">
                                                {{ $local->meta_template_status }}
                                            </x-filament::badge>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $tpl->default_language }}</span>
                                @endif
                            </div>

                            @if (! $isAdopted)
                                {{ ($this->adoptAction)(['platform_template_id' => $tpl->id]) }}
                            @endif
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
