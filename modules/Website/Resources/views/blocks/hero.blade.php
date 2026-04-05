@php
    $height = $settings['height'] ?? 'full';
    $textAlignment = $settings['text_alignment'] ?? 'center';
    $overlayOpacity = $settings['overlay_opacity'] ?? 0.5;
    $bgImage = $content['background_image'] ?? null;

    $heightClass = match($height) {
        'full' => 'min-h-screen',
        'large' => 'min-h-[80vh]',
        'medium' => 'min-h-[60vh]',
        default => 'min-h-screen',
    };

    $textAlignClass = match($textAlignment) {
        'left' => 'text-left items-start',
        'right' => 'text-right items-end',
        default => 'text-center items-center',
    };
@endphp

<section class="relative {{ $heightClass }} flex items-center justify-center overflow-hidden">
    {{-- Background Image --}}
    @if($bgImage)
        <div class="absolute inset-0">
            <img src="{{ Storage::disk('tenant')->url($bgImage) }}"
                 alt=""
                 class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-black" style="opacity: {{ $overlayOpacity }};"></div>
        </div>
    @else
        <div class="absolute inset-0 bg-gradient-primary"></div>
    @endif

    {{-- Content --}}
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 flex flex-col {{ $textAlignClass }}">
        @if($content['eyebrow'][$locale] ?? $content['eyebrow']['en'] ?? false)
            <span class="text-primary-200 text-sm font-semibold tracking-wider uppercase mb-4">
                {{ $content['eyebrow'][$locale] ?? $content['eyebrow']['en'] }}
            </span>
        @endif

        <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 max-w-4xl">
            {{ $content['title'][$locale] ?? $content['title']['en'] ?? '' }}
        </h1>

        @if($content['subtitle'][$locale] ?? $content['subtitle']['en'] ?? false)
            <p class="text-xl md:text-2xl text-gray-200 mb-8 max-w-2xl">
                {{ $content['subtitle'][$locale] ?? $content['subtitle']['en'] }}
            </p>
        @endif

        <div class="flex flex-wrap gap-4 {{ $textAlignment === 'center' ? 'justify-center' : '' }}">
            @if($content['primary_button']['label'][$locale] ?? $content['primary_button']['label']['en'] ?? false)
                <a href="{{ $content['primary_button']['url'] ?? '#' }}"
                   class="inline-flex items-center px-8 py-4 bg-primary text-white font-semibold rounded-lg shadow-lg hover:bg-primary/90 transition-all duration-200">
                    {{ $content['primary_button']['label'][$locale] ?? $content['primary_button']['label']['en'] }}
                </a>
            @endif

            @if($content['secondary_button']['label'][$locale] ?? $content['secondary_button']['label']['en'] ?? false)
                <a href="{{ $content['secondary_button']['url'] ?? '#' }}"
                   class="inline-flex items-center px-8 py-4 border-2 border-white text-white font-semibold rounded-lg hover:bg-white hover:text-gray-900 transition-all duration-200">
                    {{ $content['secondary_button']['label'][$locale] ?? $content['secondary_button']['label']['en'] }}
                </a>
            @endif
        </div>
    </div>

    {{-- Scroll indicator --}}
    @if($height === 'full')
        <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce">
            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
        </div>
    @endif
</section>
