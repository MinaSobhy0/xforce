<div class="space-y-4">
    {{-- Patient Info --}}
    @if($patient)
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
            <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ __('booking::booking.labels.patient') }}
            </h4>
            <p class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ $patient['name'] }}
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ $patient['phone'] }}
            </p>
        </div>
    @endif

    {{-- Appointments List --}}
    <div class="space-y-3">
        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">
            {{ __('booking::booking.labels.appointments') }} ({{ count($items) }})
        </h4>

        @foreach($items as $index => $item)
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h5 class="font-semibold text-gray-900 dark:text-white">
                            {{ $item['service_name'] }}
                        </h5>
                        <div class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                            <p class="flex items-center gap-2">
                                <x-heroicon-o-calendar class="h-4 w-4" />
                                {{ \Carbon\Carbon::parse($item['date'])->format('l, M d, Y') }}
                            </p>
                            <p class="flex items-center gap-2">
                                <x-heroicon-o-clock class="h-4 w-4" />
                                {{ $item['start_time'] }} - {{ $item['end_time'] }}
                                <span class="text-gray-400">({{ $item['duration'] }} {{ __('booking::booking.minutes') }})</span>
                            </p>
                            @if($item['practitioner_name'])
                                <p class="flex items-center gap-2">
                                    <x-heroicon-o-user class="h-4 w-4" />
                                    {{ $item['practitioner_name'] }}
                                </p>
                            @endif
                            @if($item['room_name'])
                                <p class="flex items-center gap-2">
                                    <x-heroicon-o-building-office class="h-4 w-4" />
                                    {{ $item['room_name'] }}
                                </p>
                            @endif
                            @if($item['equipment_name'])
                                <p class="flex items-center gap-2">
                                    <x-heroicon-o-wrench class="h-4 w-4" />
                                    {{ $item['equipment_name'] }}
                                </p>
                            @endif
                        </div>
                    </div>

                    @if(isset($item['from_package']) && $item['from_package'])
                        <span class="inline-flex items-center rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-800 dark:bg-primary-900 dark:text-primary-300">
                            <x-heroicon-o-gift class="mr-1 h-3 w-3" />
                            {{ __('booking::booking.labels.from_package') }}
                        </span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Total --}}
    <div class="rounded-lg border border-primary-200 bg-primary-50 p-4 dark:border-primary-700 dark:bg-primary-900/20">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
                {{ __('booking::booking.labels.total_duration') }}
            </span>
            <span class="text-lg font-semibold text-primary-600 dark:text-primary-400">
                {{ $totalDuration }} {{ __('booking::booking.minutes') }}
            </span>
        </div>
    </div>
</div>
