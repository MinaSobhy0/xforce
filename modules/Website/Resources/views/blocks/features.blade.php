@php
    $columns = $settings['columns'] ?? 3;
    $items = $content['items'] ?? [];

    $gridClass = match($columns) {
        2 => 'md:grid-cols-2',
        4 => 'md:grid-cols-2 lg:grid-cols-4',
        default => 'md:grid-cols-2 lg:grid-cols-3',
    };
@endphp

<section class="py-16 lg:py-24 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Section Header --}}
        @if($content['title'][$locale] ?? $content['title']['en'] ?? false)
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    {{ $content['title'][$locale] ?? $content['title']['en'] }}
                </h2>
                @if($content['subtitle'][$locale] ?? $content['subtitle']['en'] ?? false)
                    <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                        {{ $content['subtitle'][$locale] ?? $content['subtitle']['en'] }}
                    </p>
                @endif
            </div>
        @endif

        {{-- Features Grid --}}
        <div class="grid grid-cols-1 {{ $gridClass }} gap-8">
            @foreach($items as $item)
                <div class="bg-white rounded-xl p-8 shadow-sm hover:shadow-md transition-shadow duration-200">
                    @if($item['icon'] ?? false)
                        <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-6">
                            <x-dynamic-component :component="$item['icon']" class="w-6 h-6 text-primary" />
                        </div>
                    @endif

                    <h3 class="text-xl font-semibold text-gray-900 mb-3">
                        {{ $item['title'][$locale] ?? $item['title']['en'] ?? '' }}
                    </h3>

                    @if($item['description'][$locale] ?? $item['description']['en'] ?? false)
                        <p class="text-gray-600">
                            {{ $item['description'][$locale] ?? $item['description']['en'] }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
