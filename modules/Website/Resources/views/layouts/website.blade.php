<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" dir="{{ $isRtl ?? false ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $page->meta_title_for_locale ?? $page->translated_title }} - {{ $tenant->name ?? config('app.name') }}</title>
    <meta name="description" content="{{ $page->meta_description_for_locale ?? '' }}">

    @if($settings['favicon'] ?? false)
        <link rel="icon" href="{{ Storage::disk('tenant')->url($settings['favicon']) }}">
    @endif

    {{-- Open Graph --}}
    <meta property="og:title" content="{{ $page->meta_title_for_locale ?? $page->translated_title }}">
    <meta property="og:description" content="{{ $page->meta_description_for_locale ?? '' }}">
    <meta property="og:type" content="website">
    @if($settings['logo'] ?? false)
        <meta property="og:image" content="{{ Storage::disk('tenant')->url($settings['logo']) }}">
    @endif

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|cairo:400,500,600,700" rel="stylesheet" />

    {{-- Tailwind CSS --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '{{ $settings["primary_color"] ?? "#3B82F6" }}',
                        secondary: '{{ $settings["secondary_color"] ?? "#10B981" }}',
                        accent: '{{ $settings["accent_color"] ?? "#F59E0B" }}',
                    },
                    fontFamily: {
                        sans: ['{{ $isRtl ? "Cairo" : "Inter" }}', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>

    {{-- Custom CSS --}}
    <style>
        :root {
            --color-primary: {{ $settings['primary_color'] ?? '#3B82F6' }};
            --color-secondary: {{ $settings['secondary_color'] ?? '#10B981' }};
            --color-accent: {{ $settings['accent_color'] ?? '#F59E0B' }};
        }

        .btn-primary {
            @apply bg-primary text-white px-6 py-3 rounded-lg font-semibold transition-all duration-200 hover:opacity-90;
        }

        .btn-secondary {
            @apply border-2 border-primary text-primary px-6 py-3 rounded-lg font-semibold transition-all duration-200 hover:bg-primary hover:text-white;
        }

        @if($settings['custom_css'] ?? false)
            {!! $settings['custom_css'] !!}
        @endif
    </style>

    {{-- Custom Head Code --}}
    @if($settings['custom_head'] ?? false)
        {!! $settings['custom_head'] !!}
    @endif
</head>
<body class="antialiased bg-white text-gray-900">
    {{-- Header --}}
    @include('website::partials.header')

    {{-- Main Content --}}
    <main>
        @yield('content')
    </main>

    {{-- Footer --}}
    @include('website::partials.footer')

    {{-- Alpine.js for interactivity --}}
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
