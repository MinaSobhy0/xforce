<div class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg space-y-4">
    @if($treatment)
        <div class="flex justify-between items-center pb-4 border-b border-gray-200 dark:border-gray-700">
            <div>
                <p class="text-sm text-gray-500">{{ __('patientportal::portal.treatment') }}</p>
                <p class="font-medium text-gray-900 dark:text-white">
                    {{ is_array(json_decode($treatment->name, true)) ? json_decode($treatment->name, true)[app()->getLocale()] ?? $treatment->name : $treatment->name }}
                </p>
            </div>
            <p class="text-lg font-bold text-primary-600">{{ number_format($treatment->price_minor / 100, 2) }} EGP</p>
        </div>
    @endif

    @if($branch)
        <div class="flex justify-between">
            <p class="text-sm text-gray-500">{{ __('patientportal::portal.branch') }}</p>
            <p class="font-medium">{{ $branch->name }}</p>
        </div>
    @endif

    @if($date)
        <div class="flex justify-between">
            <p class="text-sm text-gray-500">{{ __('patientportal::portal.date') }}</p>
            <p class="font-medium">{{ \Carbon\Carbon::parse($date)->format('l, M d, Y') }}</p>
        </div>
    @endif

    @if($time)
        <div class="flex justify-between">
            <p class="text-sm text-gray-500">{{ __('patientportal::portal.time') }}</p>
            <p class="font-medium">{{ explode('_', $time)[0] ?? $time }}</p>
        </div>
    @endif

    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
        <p class="text-sm text-gray-500 text-center">
            {{ __('patientportal::portal.confirm_booking_note') }}
        </p>
    </div>
</div>
