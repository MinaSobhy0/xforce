@props(['visits', 'patientId'])

<div class="space-y-4">
    @forelse($visits as $visit)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-md transition-shadow">
            {{-- Visit Header --}}
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center
                            @if($visit->status === 'invoiced') bg-green-100 dark:bg-green-900/30
                            @elseif($visit->status === 'open') bg-amber-100 dark:bg-amber-900/30
                            @elseif($visit->status === 'cancelled') bg-red-100 dark:bg-red-900/30
                            @else bg-blue-100 dark:bg-blue-900/30
                            @endif">
                            @if($visit->status === 'invoiced')
                                <x-heroicon-o-check-circle class="w-5 h-5 text-green-600 dark:text-green-400" />
                            @elseif($visit->status === 'open')
                                <x-heroicon-o-clock class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                            @elseif($visit->status === 'cancelled')
                                <x-heroicon-o-x-circle class="w-5 h-5 text-red-600 dark:text-red-400" />
                            @else
                                <x-heroicon-o-ticket class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                            @endif
                        </div>
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">
                                {{ $visit->code }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $visit->check_in_at->format('M d, Y - H:i') }}
                                @if($visit->check_out_at)
                                    <span class="mx-1">→</span>
                                    {{ $visit->check_out_at->format('H:i') }}
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="px-2.5 py-1 text-xs font-medium rounded-full
                            @if($visit->status === 'invoiced') bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300
                            @elseif($visit->status === 'open') bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300
                            @elseif($visit->status === 'cancelled') bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300
                            @else bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300
                            @endif">
                            {{ __('booking::visits.statuses.' . $visit->status) }}
                        </span>
                        @if($visit->total_minor > 0)
                            <span class="font-semibold text-gray-900 dark:text-white">
                                {{ number_format($visit->total_minor / 100, 2) }} {{ current_currency() }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Visit Content --}}
            <div class="p-4 space-y-3">
                {{-- Services --}}
                @if($visit->appointments->isNotEmpty())
                    <div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                            {{ __('booking::visits.fields.appointments') }}
                        </div>
                        <div class="space-y-1.5">
                            @foreach($visit->appointments as $appointment)
                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex items-center gap-2">
                                        <x-heroicon-o-sparkles class="w-4 h-4 text-blue-500" />
                                        <span class="text-gray-700 dark:text-gray-300">
                                            {{ $appointment->service?->translated_name ?? '-' }}
                                        </span>
                                        @if($appointment->practitioner)
                                            <span class="text-gray-400 dark:text-gray-500">
                                                ({{ $appointment->practitioner->full_name }})
                                            </span>
                                        @endif
                                        @if($appointment->is_package_session)
                                            <span class="px-1.5 py-0.5 text-xs bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 rounded">
                                                {{ __('booking::checkout.package_covered') }}
                                            </span>
                                        @endif
                                    </div>
                                    <span class="text-gray-600 dark:text-gray-400">
                                        @if(!$appointment->is_package_session)
                                            {{ number_format(($appointment->net_price ?? 0) / 100, 2) }}
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Products --}}
                @php
                    $soldProducts = $visit->products->where('usage_type', 'sold');
                @endphp
                @if($soldProducts->isNotEmpty())
                    <div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                            {{ __('booking::visits.fields.products') }}
                        </div>
                        <div class="space-y-1.5">
                            @foreach($soldProducts as $product)
                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex items-center gap-2">
                                        <x-heroicon-o-shopping-bag class="w-4 h-4 text-purple-500" />
                                        <span class="text-gray-700 dark:text-gray-300">
                                            {{ $product->product?->translated_name ?? $product->product?->name ?? '-' }}
                                        </span>
                                        <span class="text-gray-400">x{{ $product->quantity }}</span>
                                    </div>
                                    <span class="text-gray-600 dark:text-gray-400">
                                        {{ number_format(($product->total_price_minor ?? 0) / 100, 2) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Practitioners --}}
                @php
                    $practitioners = $visit->appointments->pluck('practitioner')->filter()->unique('id');
                @endphp
                @if($practitioners->isNotEmpty())
                    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <x-heroicon-o-user-circle class="w-4 h-4" />
                        <span>{{ $practitioners->pluck('full_name')->join(', ') }}</span>
                    </div>
                @endif

                {{-- Actions --}}
                <div class="flex items-center gap-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                    @if($visit->invoice)
                        <a href="{{ \Modules\Billing\Filament\Resources\InvoiceResource::getUrl('view', ['record' => $visit->invoice_id]) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/30 rounded-lg transition-colors">
                            <x-heroicon-o-document-text class="w-4 h-4" />
                            {{ $visit->invoice->code }}
                        </a>
                    @endif
                    @if($visit->status === 'open')
                        <a href="{{ $visit->checkout_url }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-green-600 hover:text-green-700 dark:text-green-400 dark:hover:text-green-300 bg-green-50 hover:bg-green-100 dark:bg-green-900/20 dark:hover:bg-green-900/30 rounded-lg transition-colors">
                            <x-heroicon-o-shopping-cart class="w-4 h-4" />
                            {{ __('booking::visits.actions.checkout') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <x-heroicon-o-ticket class="w-12 h-12 mx-auto mb-3 opacity-50" />
            <p>{{ __('booking::visits.messages.no_visits') }}</p>
        </div>
    @endforelse
</div>
