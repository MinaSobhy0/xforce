@php
    use Modules\Packages\Models\PackageSubscription;
    use Modules\Packages\Models\Package;

    $services = [];
    // Get booking items passed from viewData
    $bookingItems = $bookingItems ?? [];

    // Build booked services map (use string keys for consistent comparison)
    $bookedServices = [];
    foreach ($bookingItems as $item) {
        $serviceId = (string) ($item['service_id'] ?? '');
        if ($serviceId) {
            $bookedServices[$serviceId] = [
                'date' => $item['date'] ?? null,
                'start_time' => $item['start_time'] ?? null,
                'end_time' => $item['end_time'] ?? null,
                'practitioner_name' => $item['practitioner_name'] ?? null,
            ];
        }
    }

    if ($packageMode === 'existing' && $subscriptionId) {
        try {
            $subscription = PackageSubscription::with('package.items.service')->find($subscriptionId);
            if ($subscription) {
                foreach ($subscription->package->items as $item) {
                    $remaining = $subscription->getSessionsRemainingByService($item->service_id);
                    if ($remaining > 0) {
                        $services[] = [
                            'id' => $item->service_id,
                            'name' => $item->service->translated_name,
                            'duration' => $item->service->duration_minutes,
                            'remaining' => $remaining,
                            'total' => $item->quantity,
                            'field' => 'package_service_id',
                        ];
                    }
                }
            }
        } catch (\Exception $e) {}
    } elseif ($packageMode === 'new' && $packageId) {
        try {
            $package = Package::with('items.service')->find($packageId);
            if ($package) {
                foreach ($package->items as $item) {
                    $services[] = [
                        'id' => $item->service_id,
                        'name' => $item->service->translated_name,
                        'duration' => $item->service->duration_minutes,
                        'remaining' => $item->quantity,
                        'total' => $item->quantity,
                        'field' => 'new_package_service_id',
                    ];
                }
            }
        } catch (\Exception $e) {}
    }
@endphp

<div class="space-y-2">
    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">
        {{ __('booking::booking.labels.select_services_to_book') }}
    </label>

    @if(empty($services))
        <div class="text-xs text-gray-400 py-2">
            {{ __('booking::booking.messages.select_package_first') }}
        </div>
    @else
        <div class="flex flex-wrap gap-1.5">
            @foreach($services as $svc)
                @php
                    $svcIdStr = (string) $svc['id'];
                    $isSelected = (string) $selectedServiceId === $svcIdStr;
                    $isBooked = isset($bookedServices[$svcIdStr]);
                    $bookedInfo = $bookedServices[$svcIdStr] ?? null;

                    if ($isBooked) {
                        $pillStyle = 'background-color: #16a34a; color: white; box-shadow: 0 0 0 2px #4ade80;';
                        $badgeStyle = 'background-color: #22c55e; color: white;';
                        $pillClass = 'ring-2';
                        $badgeClass = '';
                    } elseif ($isSelected) {
                        $pillStyle = 'background-color: #22c55e; color: white; box-shadow: 0 0 0 2px #86efac;';
                        $badgeStyle = 'background-color: #4ade80; color: white;';
                        $pillClass = 'ring-2';
                        $badgeClass = '';
                    } else {
                        $pillStyle = 'background-color: #f3f4f6; color: #374151; border: 1px solid #e5e7eb;';
                        $badgeStyle = 'background-color: #e5e7eb; color: #374151;';
                        $pillClass = 'hover:bg-gray-200';
                        $badgeClass = '';
                    }
                @endphp

                <button
                    type="button"
                    wire:click="$set('data.{{ $svc['field'] }}', '{{ $svc['id'] }}')"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-all cursor-pointer {{ $pillClass }}"
                    style="{{ $pillStyle }}"
                >
                    @if($isBooked)
                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    @elseif($isSelected)
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/>
                        </svg>
                    @endif
                    <span class="truncate max-w-[140px]">{{ $svc['name'] }}</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold" style="{{ $badgeStyle }}">
                        {{ $svc['remaining'] }}/{{ $svc['total'] }}
                    </span>
                </button>
            @endforeach
        </div>

        {{-- Show booked slot info for selected service --}}
        @foreach($services as $svc)
            @php
                $svcIdStr = (string) $svc['id'];
                $isBooked = isset($bookedServices[$svcIdStr]);
                $bookedInfo = $bookedServices[$svcIdStr] ?? null;
            @endphp
            @if($isBooked && $bookedInfo)
                <div class="mt-1 inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] bg-green-100 text-green-700">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="font-medium">{{ $svc['name'] }}:</span>
                    <span>{{ \Carbon\Carbon::parse($bookedInfo['date'])->format('M d') }} {{ $bookedInfo['start_time'] }}</span>
                    @if($bookedInfo['practitioner_name'])
                        <span>- {{ $bookedInfo['practitioner_name'] }}</span>
                    @endif
                </div>
            @endif
        @endforeach
    @endif
</div>
