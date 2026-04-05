@php
    $layout = $settings['layout'] ?? 'carousel';
    $items = $content['items'] ?? [];
@endphp

<section class="py-16 lg:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Section Header --}}
        <div class="text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                {{ $content['title'][$locale] ?? $content['title']['en'] ?? ($locale === 'ar' ? 'ماذا يقول عملاؤنا' : 'What Our Clients Say') }}
            </h2>
        </div>

        {{-- Testimonials --}}
        @if($layout === 'carousel')
            <div x-data="{ current: 0, total: {{ count($items) }} }" class="relative">
                <div class="overflow-hidden">
                    <div class="flex transition-transform duration-500"
                         :style="'transform: translateX(-' + (current * 100) + '%)'">
                        @foreach($items as $index => $testimonial)
                            <div class="w-full flex-shrink-0 px-4">
                                <div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-lg p-8 md:p-12 text-center">
                                    {{-- Avatar --}}
                                    @if($testimonial['image'] ?? false)
                                        <img src="{{ website_asset($testimonial['image']) }}"
                                             alt="{{ $testimonial['name'] ?? '' }}"
                                             class="w-20 h-20 rounded-full mx-auto mb-6 object-cover">
                                    @else
                                        <div class="w-20 h-20 rounded-full mx-auto mb-6 bg-primary/10 flex items-center justify-center">
                                            <span class="text-2xl font-bold text-primary">
                                                {{ substr($testimonial['name'] ?? 'A', 0, 1) }}
                                            </span>
                                        </div>
                                    @endif

                                    {{-- Rating --}}
                                    @if($testimonial['rating'] ?? false)
                                        <div class="flex justify-center mb-4">
                                            @for($i = 1; $i <= 5; $i++)
                                                <svg class="w-5 h-5 {{ $i <= $testimonial['rating'] ? 'text-yellow-400' : 'text-gray-300' }}"
                                                     fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>
                                            @endfor
                                        </div>
                                    @endif

                                    {{-- Quote --}}
                                    <blockquote class="text-lg md:text-xl text-gray-700 mb-6">
                                        "{{ $testimonial['text'][$locale] ?? $testimonial['text']['en'] ?? '' }}"
                                    </blockquote>

                                    {{-- Name --}}
                                    <p class="font-semibold text-gray-900">
                                        {{ $testimonial['name'] ?? '' }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Navigation --}}
                @if(count($items) > 1)
                    <div class="flex justify-center mt-8 space-x-2 {{ $locale === 'ar' ? 'space-x-reverse' : '' }}">
                        @foreach($items as $index => $testimonial)
                            <button @click="current = {{ $index }}"
                                    :class="current === {{ $index }} ? 'bg-primary' : 'bg-gray-300'"
                                    class="w-3 h-3 rounded-full transition-colors duration-200">
                            </button>
                        @endforeach
                    </div>

                    <button @click="current = current > 0 ? current - 1 : total - 1"
                            class="absolute {{ $locale === 'ar' ? 'right-0' : 'left-0' }} top-1/2 transform -translate-y-1/2 p-2 bg-white rounded-full shadow-lg hover:bg-gray-50">
                        <svg class="w-6 h-6 text-gray-600 {{ $locale === 'ar' ? '' : 'rotate-180' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>

                    <button @click="current = current < total - 1 ? current + 1 : 0"
                            class="absolute {{ $locale === 'ar' ? 'left-0' : 'right-0' }} top-1/2 transform -translate-y-1/2 p-2 bg-white rounded-full shadow-lg hover:bg-gray-50">
                        <svg class="w-6 h-6 text-gray-600 {{ $locale === 'ar' ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                @endif
            </div>
        @else
            {{-- Grid Layout --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($items as $testimonial)
                    <div class="bg-white rounded-xl p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            @if($testimonial['image'] ?? false)
                                <img src="{{ website_asset($testimonial['image']) }}"
                                     alt="{{ $testimonial['name'] ?? '' }}"
                                     class="w-12 h-12 rounded-full object-cover">
                            @else
                                <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center">
                                    <span class="text-lg font-bold text-primary">
                                        {{ substr($testimonial['name'] ?? 'A', 0, 1) }}
                                    </span>
                                </div>
                            @endif
                            <div class="{{ $locale === 'ar' ? 'mr-4' : 'ml-4' }}">
                                <p class="font-semibold text-gray-900">{{ $testimonial['name'] ?? '' }}</p>
                                @if($testimonial['rating'] ?? false)
                                    <div class="flex">
                                        @for($i = 1; $i <= 5; $i++)
                                            <svg class="w-4 h-4 {{ $i <= $testimonial['rating'] ? 'text-yellow-400' : 'text-gray-300' }}"
                                                 fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                        @endfor
                                    </div>
                                @endif
                            </div>
                        </div>
                        <p class="text-gray-600">
                            "{{ $testimonial['text'][$locale] ?? $testimonial['text']['en'] ?? '' }}"
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
