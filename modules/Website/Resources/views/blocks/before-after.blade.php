@php
    $layout = $settings['layout'] ?? 'slider';
    $items = $content['items'] ?? [];

    // Normalize image paths in items (handle Builder UUID keys)
    foreach ($items as &$item) {
        if (isset($item['before_image']) && is_array($item['before_image'])) {
            $item['before_image'] = reset($item['before_image']) ?: null;
        }
        if (isset($item['after_image']) && is_array($item['after_image'])) {
            $item['after_image'] = reset($item['after_image']) ?: null;
        }
    }
    unset($item);
@endphp

<section class="py-16 lg:py-24 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Section Header --}}
        <div class="text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                {{ $content['title'][$locale] ?? $content['title']['en'] ?? ($locale === 'ar' ? 'النتائج' : 'Results') }}
            </h2>
        </div>

        {{-- Before/After Items --}}
        @if($layout === 'slider')
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($items as $item)
                    <div class="bg-white rounded-xl overflow-hidden shadow-sm">
                        {{-- Before/After Slider --}}
                        <div x-data="{ position: 50 }" class="relative aspect-square overflow-hidden">
                            {{-- After Image (bottom layer) --}}
                            <img src="{{ website_asset($item['after_image']) }}"
                                 alt="{{ $locale === 'ar' ? 'بعد' : 'After' }}"
                                 class="absolute inset-0 w-full h-full object-cover">

                            {{-- Before Image (top layer with clip) --}}
                            <div class="absolute inset-0 overflow-hidden" :style="'clip-path: inset(0 ' + (100 - position) + '% 0 0)'">
                                <img src="{{ website_asset($item['before_image']) }}"
                                     alt="{{ $locale === 'ar' ? 'قبل' : 'Before' }}"
                                     class="absolute inset-0 w-full h-full object-cover">
                            </div>

                            {{-- Slider Handle --}}
                            <div class="absolute inset-y-0 z-10" :style="'left: ' + position + '%'">
                                <div class="absolute inset-y-0 w-0.5 bg-white shadow-lg" style="transform: translateX(-50%);"></div>
                                <div class="absolute top-1/2 transform -translate-x-1/2 -translate-y-1/2 w-10 h-10 bg-white rounded-full shadow-lg flex items-center justify-center cursor-grab active:cursor-grabbing"
                                     @mousedown.prevent="
                                         const container = $el.closest('.aspect-square');
                                         const rect = container.getBoundingClientRect();
                                         const onMove = (e) => {
                                             const x = Math.max(0, Math.min(rect.width, e.clientX - rect.left));
                                             position = (x / rect.width) * 100;
                                         };
                                         const onUp = () => {
                                             document.removeEventListener('mousemove', onMove);
                                             document.removeEventListener('mouseup', onUp);
                                         };
                                         document.addEventListener('mousemove', onMove);
                                         document.addEventListener('mouseup', onUp);
                                     "
                                     @touchstart.prevent="
                                         const container = $el.closest('.aspect-square');
                                         const rect = container.getBoundingClientRect();
                                         const onMove = (e) => {
                                             const x = Math.max(0, Math.min(rect.width, e.touches[0].clientX - rect.left));
                                             position = (x / rect.width) * 100;
                                         };
                                         const onEnd = () => {
                                             document.removeEventListener('touchmove', onMove);
                                             document.removeEventListener('touchend', onEnd);
                                         };
                                         document.addEventListener('touchmove', onMove, { passive: false });
                                         document.addEventListener('touchend', onEnd);
                                     ">
                                    <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
                                    </svg>
                                </div>
                            </div>

                            {{-- Labels --}}
                            <div class="absolute top-4 {{ $locale === 'ar' ? 'right-4' : 'left-4' }} bg-black/50 text-white text-xs font-semibold px-2 py-1 rounded">
                                {{ $locale === 'ar' ? 'قبل' : 'Before' }}
                            </div>
                            <div class="absolute top-4 {{ $locale === 'ar' ? 'left-4' : 'right-4' }} bg-black/50 text-white text-xs font-semibold px-2 py-1 rounded">
                                {{ $locale === 'ar' ? 'بعد' : 'After' }}
                            </div>
                        </div>

                        {{-- Title --}}
                        @if($item['title'][$locale] ?? $item['title']['en'] ?? false)
                            <div class="p-4 text-center">
                                <h3 class="font-semibold text-gray-900">
                                    {{ $item['title'][$locale] ?? $item['title']['en'] }}
                                </h3>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            {{-- Grid Layout --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                @foreach($items as $item)
                    <div class="bg-white rounded-xl overflow-hidden shadow-sm">
                        <div class="grid grid-cols-2 gap-1">
                            <div class="relative">
                                <img src="{{ website_asset($item['before_image']) }}"
                                     alt="{{ $locale === 'ar' ? 'قبل' : 'Before' }}"
                                     class="w-full aspect-square object-cover">
                                <div class="absolute bottom-4 {{ $locale === 'ar' ? 'right-4' : 'left-4' }} bg-black/50 text-white text-sm font-semibold px-3 py-1 rounded">
                                    {{ $locale === 'ar' ? 'قبل' : 'Before' }}
                                </div>
                            </div>
                            <div class="relative">
                                <img src="{{ website_asset($item['after_image']) }}"
                                     alt="{{ $locale === 'ar' ? 'بعد' : 'After' }}"
                                     class="w-full aspect-square object-cover">
                                <div class="absolute bottom-4 {{ $locale === 'ar' ? 'left-4' : 'right-4' }} bg-black/50 text-white text-sm font-semibold px-3 py-1 rounded">
                                    {{ $locale === 'ar' ? 'بعد' : 'After' }}
                                </div>
                            </div>
                        </div>

                        @if($item['title'][$locale] ?? $item['title']['en'] ?? false)
                            <div class="p-4 text-center">
                                <h3 class="font-semibold text-gray-900">
                                    {{ $item['title'][$locale] ?? $item['title']['en'] }}
                                </h3>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
