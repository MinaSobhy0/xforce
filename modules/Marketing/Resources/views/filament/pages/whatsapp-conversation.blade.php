@php
    /** @var \Modules\Marketing\Models\WhatsAppConversation $record */
    // Filament's ViewRecord exposes $record as a public property; the blade
    // can read it directly. Guard for standalone-render tests.
    $record = $record ?? (isset($this) ? $this->record : null);
    $messages = $record ? $record->messages()->orderBy('created_at')->limit(500)->get() : collect();
    $isRtl = app()->getLocale() === 'ar';
    $withinWindow = $record?->isWithinServiceWindow() ?? false;
@endphp

<x-filament-panels::page>
    <div class="space-y-4" wire:poll.5s>
        {{-- Conversation header --}}
        <x-filament::section>
            <div class="flex items-center justify-between gap-4 text-sm">
                <div class="space-y-1">
                    <div class="font-medium text-gray-900 dark:text-gray-100">
                        {{ $record->remote_display_name ?: $record->remote_phone_e164 }}
                    </div>
                    @if ($record->remote_display_name)
                        <div class="text-gray-500 dark:text-gray-400 font-mono text-xs">
                            {{ $record->remote_phone_e164 }}
                        </div>
                    @endif
                </div>
                <div>
                    @if ($withinWindow)
                        <x-filament::badge color="success" icon="heroicon-o-clock">
                            {{ __('marketing::whatsapp.inbox.window_open') }}
                        </x-filament::badge>
                    @else
                        <x-filament::badge color="warning" icon="heroicon-o-lock-closed">
                            {{ __('marketing::whatsapp.inbox.window_closed') }}
                        </x-filament::badge>
                    @endif
                </div>
            </div>
        </x-filament::section>

        {{-- Out-of-window banner --}}
        @unless ($withinWindow)
            <div class="rounded-lg border border-warning-300 bg-warning-50 dark:bg-warning-900/20 dark:border-warning-800 p-4 text-sm">
                <p class="font-medium text-warning-800 dark:text-warning-200">
                    {{ __('marketing::whatsapp.inbox.window_expired') }}
                </p>
                <p class="mt-1 text-warning-700 dark:text-warning-300">
                    {{ __('marketing::whatsapp.inbox.must_use_template') }}
                </p>
            </div>
        @endunless

        {{-- Message thread --}}
        <x-filament::section>
            <div class="flex flex-col gap-3 max-h-[60vh] overflow-y-auto px-1 py-2" {{ $isRtl ? 'dir=rtl' : '' }}>
                @forelse ($messages as $msg)
                    @php
                        $isInbound = $msg->direction === \Modules\Marketing\Models\WhatsAppMessage::DIRECTION_INBOUND;
                        $bubbleAlign = $isInbound ? ($isRtl ? 'self-end' : 'self-start') : ($isRtl ? 'self-start' : 'self-end');
                        $bubbleColor = $isInbound
                            ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100'
                            : 'bg-primary-500 text-white';
                    @endphp

                    <div class="{{ $bubbleAlign }} max-w-[75%] rounded-2xl px-4 py-2 shadow-sm {{ $bubbleColor }}">
                        @if ($msg->hasMedia() && $msg->media_path)
                            <div class="mb-2 text-xs italic opacity-75">
                                📎 {{ $msg->media_filename ?: $msg->type }} ({{ $msg->media_mime }})
                            </div>
                        @elseif ($msg->hasMedia())
                            <div class="mb-2 text-xs italic opacity-75">
                                📎 {{ __('marketing::whatsapp.inbox.media_pending') }}
                            </div>
                        @endif

                        @if ($msg->type === \Modules\Marketing\Models\WhatsAppMessage::TYPE_BUTTON_REPLY)
                            <div class="text-xs italic opacity-75 mb-1">
                                ↩︎ {{ __('marketing::whatsapp.inbox.button_reply') }}
                            </div>
                        @endif

                        @if ($msg->body)
                            <div class="whitespace-pre-wrap break-words text-sm">{{ $msg->body }}</div>
                        @elseif ($msg->type === 'location')
                            <div class="text-xs">📍 {{ __('marketing::whatsapp.inbox.location_shared') }}</div>
                        @elseif ($msg->type === \Modules\Marketing\Models\WhatsAppMessage::TYPE_REACTION)
                            <div class="text-lg">{{ $msg->body }}</div>
                        @endif

                        <div class="mt-1 text-[10px] opacity-60 {{ $isInbound ? '' : 'text-right' }}">
                            {{ $msg->sent_at?->format('H:i') ?: $msg->created_at->format('H:i') }}
                            @if (! $isInbound && $msg->status)
                                · {{ $msg->status }}
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center text-sm text-gray-500 dark:text-gray-400 py-8">
                        {{ __('marketing::whatsapp.inbox.no_messages') }}
                    </div>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
