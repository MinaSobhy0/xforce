@php
    $columns = $settings['columns'] ?? 3;
    $lightbox = $settings['lightbox'] ?? true;
    $images = $content['images'] ?? [];

    // Flatten if images is nested (Builder component stores with UUID keys)
    if (!empty($images) && is_array($images)) {
        $firstValue = reset($images);
        // If first value is an array, this is nested structure - flatten it
        if (is_array($firstValue)) {
            $images = array_values(array_filter(array_map(fn($item) => is_string($item) ? $item : reset($item), $images)));
        }
    }

    $gridClass = match($columns) {
        2 => 'md:grid-cols-2',
        4 => 'md:grid-cols-2 lg:grid-cols-4',
        default => 'md:grid-cols-2 lg:grid-cols-3',
    };
@endphp

<section class="py-16 lg:py-24" x-data="{ lightboxOpen: false, currentImage: '' }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Section Header --}}
        @if($content['title'][$locale] ?? $content['title']['en'] ?? false)
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    {{ $content['title'][$locale] ?? $content['title']['en'] }}
                </h2>
            </div>
        @endif

        {{-- Gallery Grid --}}
        <div class="grid grid-cols-1 {{ $gridClass }} gap-4">
            @foreach($images as $image)
                <div class="relative group overflow-hidden rounded-lg aspect-square cursor-pointer"
                     @if($lightbox) @click="lightboxOpen = true; currentImage = '{{ website_asset($image) }}'" @endif>
                    <img src="{{ website_asset($image) }}"
                         alt=""
                         class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110">
                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors duration-300 flex items-center justify-center">
                        @if($lightbox)
                            <svg class="w-10 h-10 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                            </svg>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Lightbox --}}
    @if($lightbox)
        <div x-show="lightboxOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/90"
             @click="lightboxOpen = false"
             @keydown.escape.window="lightboxOpen = false">
            <button @click="lightboxOpen = false"
                    class="absolute top-4 {{ $locale === 'ar' ? 'left-4' : 'right-4' }} text-white hover:text-gray-300">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <img :src="currentImage" alt="" class="max-w-full max-h-full object-contain p-4" @click.stop>
        </div>
    @endif
</section>
