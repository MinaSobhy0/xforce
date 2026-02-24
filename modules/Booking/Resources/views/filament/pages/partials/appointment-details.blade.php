<div class="space-y-4">
    {{-- Patient Info Card --}}
    <div class="bg-primary-50 dark:bg-primary-900/20 rounded-lg p-4 border border-primary-200 dark:border-primary-800">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0">
                <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-800 flex items-center justify-center">
                    <x-heroicon-o-user class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-base font-semibold text-gray-900 dark:text-white">{{ $appointment->patient?->full_name ?? '-' }}</div>
                @if($appointment->patient?->phone)
                    <div class="text-sm text-gray-600 dark:text-gray-300 flex items-center gap-1">
                        <x-heroicon-o-phone class="w-3.5 h-3.5" />
                        {{ $appointment->patient->phone }}
                    </div>
                @endif
                @if($appointment->patient?->email)
                    <div class="text-sm text-gray-600 dark:text-gray-300 flex items-center gap-1">
                        <x-heroicon-o-envelope class="w-3.5 h-3.5" />
                        {{ $appointment->patient->email }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Date & Time Row --}}
    <div class="grid grid-cols-2 gap-3">
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-3">
            <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 mb-1">
                <x-heroicon-o-calendar class="w-4 h-4" />
                <span class="text-xs font-medium uppercase">{{ __('booking::calendar.modal.date') }}</span>
            </div>
            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $appointment->date->format('D, M d, Y') }}</div>
        </div>
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-3">
            <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 mb-1">
                <x-heroicon-o-clock class="w-4 h-4" />
                <span class="text-xs font-medium uppercase">{{ __('booking::calendar.modal.time') }}</span>
            </div>
            <div class="text-sm font-medium text-gray-900 dark:text-white">
                {{ $appointment->start_time->format('H:i') }} - {{ $appointment->end_time ? $appointment->end_time->format('H:i') : $appointment->start_time->addMinutes($appointment->duration_minutes)->format('H:i') }}
                <span class="text-xs text-gray-500">({{ $appointment->duration_minutes }} min)</span>
            </div>
        </div>
    </div>

    {{-- Service --}}
    <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-3">
        <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 mb-1">
            <x-heroicon-o-clipboard-document-list class="w-4 h-4" />
            <span class="text-xs font-medium uppercase">{{ __('booking::calendar.modal.service') }}</span>
        </div>
        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $appointment->service?->name ?? '-' }}</div>
        @if($appointment->service?->category)
            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $appointment->service->category->translated_name }}</div>
        @endif
    </div>

    {{-- Practitioner, Room & Branch Row --}}
    <div class="grid grid-cols-3 gap-3">
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-3">
            <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 mb-1">
                <x-heroicon-o-user-circle class="w-4 h-4" />
                <span class="text-xs font-medium uppercase">{{ __('booking::calendar.modal.practitioner') }}</span>
            </div>
            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $appointment->practitioner?->full_name ?? '-' }}</div>
        </div>
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-3">
            <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 mb-1">
                <x-heroicon-o-building-office class="w-4 h-4" />
                <span class="text-xs font-medium uppercase">{{ __('booking::calendar.modal.room') }}</span>
            </div>
            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $appointment->room?->name ?? '-' }}</div>
        </div>
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-3">
            <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 mb-1">
                <x-heroicon-o-map-pin class="w-4 h-4" />
                <span class="text-xs font-medium uppercase">{{ __('booking::calendar.modal.branch') }}</span>
            </div>
            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $appointment->branch?->name ?? '-' }}</div>
        </div>
    </div>

    {{-- Status --}}
    <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-800/50 rounded-lg p-3">
        <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
            <x-heroicon-o-tag class="w-4 h-4" />
            <span class="text-xs font-medium uppercase">{{ __('booking::calendar.modal.status') }}</span>
        </div>
        @php
            $statusColors = match($appointment->status) {
                'scheduled' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                'confirmed' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                'checked_in' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                'in_progress' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
                'completed' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                'no_show' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
            };
        @endphp
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors }}">
            {{ ucfirst(str_replace('_', ' ', $appointment->status)) }}
        </span>
    </div>

    {{-- Notes --}}
    @if($appointment->notes)
        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-3 border border-amber-200 dark:border-amber-800">
            <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400 mb-1">
                <x-heroicon-o-document-text class="w-4 h-4" />
                <span class="text-xs font-medium uppercase">{{ __('booking::calendar.modal.notes') }}</span>
            </div>
            <div class="text-sm text-gray-700 dark:text-gray-300">{{ $appointment->notes }}</div>
        </div>
    @endif
</div>
