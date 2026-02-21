<div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
    <h4 class="font-medium text-gray-900 dark:text-white mb-2">
        {{ is_array(json_decode($treatment->name, true)) ? json_decode($treatment->name, true)[app()->getLocale()] ?? $treatment->name : $treatment->name }}
    </h4>

    @if($treatment->description)
        <p class="text-sm text-gray-500 mb-3">
            {{ is_array(json_decode($treatment->description, true)) ? json_decode($treatment->description, true)[app()->getLocale()] ?? $treatment->description : $treatment->description }}
        </p>
    @endif

    <div class="flex gap-4 text-sm">
        <div>
            <span class="text-gray-500">{{ __('patientportal::portal.duration') }}:</span>
            <span class="font-medium">{{ $treatment->duration_minutes }} {{ __('patientportal::portal.minutes') }}</span>
        </div>
        <div>
            <span class="text-gray-500">{{ __('patientportal::portal.price') }}:</span>
            <span class="font-medium">{{ number_format($treatment->price_minor / 100, 2) }} EGP</span>
        </div>
    </div>
</div>
