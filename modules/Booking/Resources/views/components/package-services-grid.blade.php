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

<div class="space-y-3">
    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
        {{ __('booking::booking.labels.select_services_to_book') }}
    </label>

    @if(empty($services))
        <div class="text-sm text-gray-400 text-center py-4 border-2 border-dashed border-gray-200 rounded-lg">
            {{ __('booking::booking.messages.select_package_first') }}
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($services as $svc)
                @php
                    $svcIdStr = (string) $svc['id'];
                    $isSelected = (string) $selectedServiceId === $svcIdStr;
                    $isBooked = isset($bookedServices[$svcIdStr]);
                    $bookedInfo = $bookedServices[$svcIdStr] ?? null;

                    if ($isBooked) {
                        $borderClasses = 'border-green-500 ring-2 ring-green-200';
                        $bgClasses = 'bg-green-50';
                    } elseif ($isSelected) {
                        $borderClasses = 'border-primary-500 ring-2 ring-primary-200';
                        $bgClasses = 'bg-primary-50';
                    } else {
                        $borderClasses = 'border-gray-200 hover:border-primary-300';
                        $bgClasses = 'bg-white';
                    }
                @endphp

                <div
                    wire:click="$set('data.{{ $svc['field'] }}', '{{ $svc['id'] }}')"
                    class="cursor-pointer rounded-lg border-2 {{ $borderClasses }} {{ $bgClasses }} p-4 transition-all hover:shadow-md"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h5 class="font-medium text-gray-900 dark:text-white">{{ $svc['name'] }}</h5>
                            <div class="mt-1 flex items-center gap-2 text-sm text-gray-500">
                                <span class="inline-flex items-center">
                                    <x-heroicon-o-clock class="mr-1 h-4 w-4" />
                                    {{ $svc['duration'] }} {{ __('booking::booking.minutes') }}
                                </span>
                            </div>
                        </div>
                        @if($isBooked)
                            <span class="flex h-6 w-6 items-center justify-center rounded-full flex-shrink-0" style="background-color: #22c55e;">
                                <x-heroicon-s-check class="h-4 w-4 text-white" />
                            </span>
                        @elseif($isSelected)
                            <span class="flex h-6 w-6 items-center justify-center rounded-full flex-shrink-0 bg-primary-500">
                                <x-heroicon-o-cursor-arrow-rays class="h-4 w-4 text-white" />
                            </span>
                        @endif
                    </div>

                    {{-- Sessions badge --}}
                    @php
                        $badgeColor = $svc['remaining'] > 2 ? 'bg-green-100 text-green-700' : ($svc['remaining'] > 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700');
                    @endphp
                    <div class="mt-3">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeColor }}">
                            {{ $svc['remaining'] }} {{ __('booking::booking.labels.sessions') }} {{ __('booking::booking.labels.remaining') }}
                        </span>
                    </div>

                    {{-- Booked slot info --}}
                    @if($isBooked && $bookedInfo)
                        <div class="mt-3 rounded-md bg-green-100 dark:bg-green-900/30 p-2">
                            <div class="flex items-center gap-2 text-sm text-green-800 dark:text-green-300">
                                <x-heroicon-s-calendar class="h-4 w-4 flex-shrink-0" />
                                <span class="font-medium">
                                    {{ \Carbon\Carbon::parse($bookedInfo['date'])->format('M d, Y') }}
                                </span>
                                <span>•</span>
                                <span>{{ $bookedInfo['start_time'] }} - {{ $bookedInfo['end_time'] }}</span>
                            </div>
                            @if($bookedInfo['practitioner_name'])
                                <div class="mt-1 flex items-center gap-2 text-xs text-green-700 dark:text-green-400">
                                    <x-heroicon-o-user class="h-3 w-3 flex-shrink-0" />
                                    <span>{{ $bookedInfo['practitioner_name'] }}</span>
                                </div>
                            @endif
                        </div>
                    @elseif($isSelected)
                        <div class="mt-3 text-xs text-primary-600 dark:text-primary-400">
                            <span class="inline-flex items-center gap-1">
                                <x-heroicon-o-arrow-down class="h-3 w-3" />
                                {{ __('booking::booking.messages.select_slot_below') }}
                            </span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
