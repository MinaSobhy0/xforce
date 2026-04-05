@php
    use Modules\Services\Models\Service;

    $layout = $settings['layout'] ?? 'grid';
    $columns = $settings['columns'] ?? 3;
    $showPrices = $content['show_prices'] ?? false;
    $showBooking = $content['show_booking'] ?? true;
    $limit = $content['limit'] ?? 6;

    // Fetch services from database
    $services = Service::query()
        ->active()
        ->bookableOnline()
        ->ordered()
        ->limit($limit)
        ->get();

    $gridClass = match($columns) {
        2 => 'md:grid-cols-2',
        4 => 'md:grid-cols-2 lg:grid-cols-4',
        default => 'md:grid-cols-2 lg:grid-cols-3',
    };
@endphp

<section class="py-16 lg:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Section Header --}}
        <div class="text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                {{ $content['title'][$locale] ?? $content['title']['en'] ?? ($locale === 'ar' ? 'خدماتنا' : 'Our Services') }}
            </h2>
            @if($content['subtitle'][$locale] ?? $content['subtitle']['en'] ?? false)
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    {{ $content['subtitle'][$locale] ?? $content['subtitle']['en'] }}
                </p>
            @endif
        </div>

        {{-- Services Grid --}}
        <div class="grid grid-cols-1 {{ $gridClass }} gap-8">
            @foreach($services as $service)
                <div class="group bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-lg transition-all duration-200 border border-gray-100">
                    @if($service->image_url)
                        <div class="aspect-video overflow-hidden">
                            <img src="{{ Storage::disk('tenant')->url($service->image_url) }}"
                                 alt="{{ $service->translated_name }}"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        </div>
                    @else
                        <div class="aspect-video bg-gradient-to-br from-primary/10 to-secondary/10 flex items-center justify-center">
                            <svg class="w-16 h-16 text-primary/30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                            </svg>
                        </div>
                    @endif

                    <div class="p-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">
                            {{ $service->translated_name }}
                        </h3>

                        @if($service->short_description)
                            <p class="text-gray-600 mb-4 line-clamp-2">
                                {{ $service->getTranslation('short_description', $locale) ?? $service->getTranslation('short_description', 'en') }}
                            </p>
                        @endif

                        <div class="flex items-center justify-between">
                            @if($showPrices && $service->base_price_minor)
                                <span class="text-lg font-bold text-primary">
                                    {{ number_format($service->base_price_minor / 100, 0) }} {{ current_currency() }}
                                </span>
                            @else
                                <span></span>
                            @endif

                            @if($showBooking)
                                <a href="/book?service={{ $service->id }}"
                                   class="inline-flex items-center text-primary font-semibold hover:underline">
                                    {{ $locale === 'ar' ? 'احجز الآن' : 'Book Now' }}
                                    <svg class="w-4 h-4 {{ $locale === 'ar' ? 'mr-1 rotate-180' : 'ml-1' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- View All Button --}}
        @if($services->count() >= $limit)
            <div class="text-center mt-12">
                <a href="/services" class="inline-flex items-center px-6 py-3 border-2 border-primary text-primary font-semibold rounded-lg hover:bg-primary hover:text-white transition-all duration-200">
                    {{ $locale === 'ar' ? 'عرض جميع الخدمات' : 'View All Services' }}
                </a>
            </div>
        @endif
    </div>
</section>
