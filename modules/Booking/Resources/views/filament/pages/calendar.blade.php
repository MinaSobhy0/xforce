<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Legend --}}
        <div class="flex items-center gap-3 text-xs">
            <div class="flex items-center gap-1">
                <span class="w-3 h-3 rounded bg-blue-500"></span>
                <span class="text-gray-600 dark:text-gray-400">{{ __('booking::room_calendar.legend.scheduled') }}</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-3 h-3 rounded bg-amber-500"></span>
                <span class="text-gray-600 dark:text-gray-400">{{ __('booking::room_calendar.legend.checked_in') }}</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-3 h-3 rounded bg-indigo-500"></span>
                <span class="text-gray-600 dark:text-gray-400">{{ __('booking::room_calendar.legend.in_progress') }}</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-3 h-3 rounded bg-green-500"></span>
                <span class="text-gray-600 dark:text-gray-400">{{ __('booking::room_calendar.legend.completed') }}</span>
            </div>
        </div>
    </div>

    <style>
        /* Calendar toolbar buttons - gray style */
        .fc .fc-button-primary {
            background-color: #f3f4f6 !important;
            border-color: #d1d5db !important;
            color: #374151 !important;
        }
        .fc .fc-button-primary:hover {
            background-color: #e5e7eb !important;
            border-color: #9ca3af !important;
            color: #1f2937 !important;
        }
        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            background-color: #d1d5db !important;
            border-color: #9ca3af !important;
            color: #1f2937 !important;
        }
        .fc .fc-button-primary:focus {
            box-shadow: 0 0 0 2px rgba(156, 163, 175, 0.5) !important;
        }
        /* Dark mode */
        .dark .fc .fc-button-primary {
            background-color: #374151 !important;
            border-color: #4b5563 !important;
            color: #e5e7eb !important;
        }
        .dark .fc .fc-button-primary:hover {
            background-color: #4b5563 !important;
            border-color: #6b7280 !important;
            color: #f9fafb !important;
        }
        .dark .fc .fc-button-primary:not(:disabled).fc-button-active,
        .dark .fc .fc-button-primary:not(:disabled):active {
            background-color: #6b7280 !important;
            border-color: #9ca3af !important;
            color: #f9fafb !important;
        }
    </style>
</x-filament-panels::page>
