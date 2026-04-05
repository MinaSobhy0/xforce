@php
    $style = $settings['style'] ?? 'gradient';
    $bgColor = $settings['background_color'] ?? null;

    $bgClass = match($style) {
        'gradient' => 'bg-gradient-to-r from-primary to-secondary',
        'solid' => 'bg-primary',
        'outline' => 'bg-transparent border-2 border-primary',
        default => 'bg-gradient-to-r from-primary to-secondary',
    };

    $textClass = $style === 'outline' ? 'text-primary' : 'text-white';
    $buttonClass = $style === 'outline'
        ? 'bg-primary text-white hover:bg-primary/90'
        : 'bg-white text-primary hover:bg-gray-100';
@endphp

<section class="py-16 lg:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="{{ $bgClass }} rounded-2xl px-8 py-16 lg:px-16 text-center"
             @if($bgColor && $style === 'solid') style="background-color: {{ $bgColor }};" @endif>
            <h2 class="text-3xl md:text-4xl font-bold {{ $textClass }} mb-4">
                {{ $content['title'][$locale] ?? $content['title']['en'] ?? '' }}
            </h2>

            @if($content['subtitle'][$locale] ?? $content['subtitle']['en'] ?? false)
                <p class="text-lg md:text-xl {{ $style === 'outline' ? 'text-gray-600' : 'text-white/90' }} mb-8 max-w-2xl mx-auto">
                    {{ $content['subtitle'][$locale] ?? $content['subtitle']['en'] }}
                </p>
            @endif

            <a href="{{ $content['button_url'] ?? '/book' }}"
               class="inline-flex items-center px-8 py-4 {{ $buttonClass }} font-semibold rounded-lg shadow-lg transition-all duration-200">
                {{ $content['button_label'][$locale] ?? $content['button_label']['en'] ?? ($locale === 'ar' ? 'ابدأ الآن' : 'Get Started') }}
                <svg class="w-5 h-5 {{ $locale === 'ar' ? 'mr-2 rotate-180' : 'ml-2' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                </svg>
            </a>
        </div>
    </div>
</section>
