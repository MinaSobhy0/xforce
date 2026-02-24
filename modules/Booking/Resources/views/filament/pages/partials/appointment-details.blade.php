<div class="space-y-4">
    {{-- Patient Info --}}
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-2">{{ __('booking::calendar.modal.patient') }}</h3>
        <div class="text-lg font-bold text-gray-900 dark:text-white">{{ $appointment->patient?->full_name ?? '-' }}</div>
        @if($appointment->patient?->phone)
            <div class="text-sm text-gray-600 dark:text-gray-300">{{ $appointment->patient->phone }}</div>
        @endif
        @if($appointment->patient?->email)
            <div class="text-sm text-gray-600 dark:text-gray-300">{{ $appointment->patient->email }}</div>
        @endif
    </div>

    {{-- Appointment Info --}}
    <div class="grid grid-cols-2 gap-4">
        <div>
            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ __('booking::calendar.modal.date') }}</h4>
            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $appointment->date->format('l, M d, Y') }}</div>
        </div>
        <div>
            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ __('booking::calendar.modal.time') }}</h4>
            <div class="text-sm font-medium text-gray-900 dark:text-white">
                {{ $appointment->start_time->format('H:i') }} - {{ $appointment->end_time ? $appointment->end_time->format('H:i') : $appointment->start_time->addMinutes($appointment->duration_minutes)->format('H:i') }}
                <span class="text-gray-500">({{ $appointment->duration_minutes }} min)</span>
            </div>
        </div>
    </div>

    {{-- Service --}}
    <div>
        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ __('booking::calendar.modal.service') }}</h4>
        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $appointment->service?->name ?? '-' }}</div>
        @if($appointment->service?->category)
            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $appointment->service->category->translated_name }}</div>
        @endif
    </div>

    {{-- Practitioner & Room --}}
    <div class="grid grid-cols-2 gap-4">
        <div>
            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ __('booking::calendar.modal.practitioner') }}</h4>
            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $appointment->practitioner?->full_name ?? '-' }}</div>
        </div>
        <div>
            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ __('booking::calendar.modal.room') }}</h4>
            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $appointment->room?->name ?? '-' }}</div>
        </div>
    </div>

    {{-- Branch --}}
    <div>
        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ __('booking::calendar.modal.branch') }}</h4>
        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $appointment->branch?->name ?? '-' }}</div>
    </div>

    {{-- Status --}}
    <div>
        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ __('booking::calendar.modal.status') }}</h4>
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
        <div>
            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ __('booking::calendar.modal.notes') }}</h4>
            <div class="text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800 rounded p-2 mt-1">{{ $appointment->notes }}</div>
        </div>
    @endif
</div>
