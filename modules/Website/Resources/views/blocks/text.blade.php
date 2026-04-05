@php
    $maxWidth = $settings['max_width'] ?? 'prose';

    $widthClass = match($maxWidth) {
        'lg' => 'max-w-4xl',
        'full' => 'max-w-7xl',
        default => 'max-w-prose',
    };
@endphp

<section class="py-16 lg:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="{{ $widthClass }} mx-auto prose prose-lg {{ $locale === 'ar' ? 'prose-rtl' : '' }} prose-headings:text-gray-900 prose-a:text-primary">
            {!! $content['content'][$locale] ?? $content['content']['en'] ?? '' !!}
        </div>
    </div>
</section>
