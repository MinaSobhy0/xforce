<x-filament-panels::page>
    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach($stats as $stat)
            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-lg bg-{{ $stat['color'] ?? 'primary' }}-500/10">
                        <x-dynamic-component
                            :component="$stat['icon']"
                            class="w-6 h-6 text-{{ $stat['color'] ?? 'primary' }}-500"
                        />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stat['value'] }}</p>
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Upcoming Appointments --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between">
                    <span>{{ __('patientportal::portal.upcoming_appointments') }}</span>
                    <x-filament::link :href="\Modules\PatientPortal\Filament\Pages\BookAppointment::getUrl()" class="text-sm">
                        {{ __('patientportal::portal.book_new') }}
                    </x-filament::link>
                </div>
            </x-slot>

            @if($upcomingAppointments->count() > 0)
                <div class="space-y-4">
                    @foreach($upcomingAppointments as $appointment)
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="flex items-center gap-4">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-primary-600">{{ \Carbon\Carbon::parse($appointment->date)->format('d') }}</div>
                                    <div class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($appointment->date)->format('M') }}</div>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ is_array(json_decode($appointment->treatment->name, true)) ? json_decode($appointment->treatment->name, true)[app()->getLocale()] ?? $appointment->treatment->name : $appointment->treatment->name }}
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        {{ \Carbon\Carbon::parse($appointment->start_time)->format('h:i A') }} - {{ $appointment->branch->name }}
                                    </p>
                                </div>
                            </div>
                            <x-filament::badge :color="$appointment->status === 'confirmed' ? 'success' : 'warning'">
                                {{ __('booking::booking.statuses.' . $appointment->status) }}
                            </x-filament::badge>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 text-gray-500">
                    <x-heroicon-o-calendar class="w-12 h-12 mx-auto mb-2 opacity-50" />
                    <p>{{ __('patientportal::portal.no_upcoming_appointments') }}</p>
                    <x-filament::link :href="\Modules\PatientPortal\Filament\Pages\BookAppointment::getUrl()" class="mt-2">
                        {{ __('patientportal::portal.book_now') }}
                    </x-filament::link>
                </div>
            @endif
        </x-filament::section>

        {{-- Recent Invoices --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between">
                    <span>{{ __('patientportal::portal.recent_invoices') }}</span>
                    <x-filament::link :href="\Modules\PatientPortal\Filament\Pages\MyInvoices::getUrl()" class="text-sm">
                        {{ __('patientportal::portal.view_all') }}
                    </x-filament::link>
                </div>
            </x-slot>

            @if($recentInvoices->count() > 0)
                <div class="space-y-4">
                    @foreach($recentInvoices as $invoice)
                        @php
                            $balance = $invoice->total_minor - $invoice->paid_amount_minor;
                        @endphp
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $invoice->invoice_number }}</p>
                                <p class="text-sm text-gray-500">{{ $invoice->created_at->format('M d, Y') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-medium {{ $balance > 0 ? 'text-danger-600' : 'text-success-600' }}">
                                    {{ number_format($invoice->total_minor / 100, 2) }} EGP
                                </p>
                                @if($balance > 0)
                                    <x-filament::link :href="\Modules\PatientPortal\Filament\Pages\MyInvoices::getUrl()" class="text-xs">
                                        {{ __('patientportal::portal.pay_now') }}
                                    </x-filament::link>
                                @else
                                    <span class="text-xs text-success-600">{{ __('patientportal::portal.paid') }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 text-gray-500">
                    <x-heroicon-o-document-text class="w-12 h-12 mx-auto mb-2 opacity-50" />
                    <p>{{ __('patientportal::portal.no_invoices') }}</p>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
