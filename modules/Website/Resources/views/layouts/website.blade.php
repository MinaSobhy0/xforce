<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" dir="{{ ($isRtl ?? false) ? 'rtl' : 'ltr' }}">
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

    {{-- Tailwind CSS CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Custom Styles with CSS Variables --}}
    <style>
        :root {
            --color-primary: {{ $settings['primary_color'] ?? '#3B82F6' }};
            --color-secondary: {{ $settings['secondary_color'] ?? '#10B981' }};
            --color-accent: {{ $settings['accent_color'] ?? '#F59E0B' }};
        }

        body {
            font-family: '{{ ($isRtl ?? false) ? "Cairo" : "Inter" }}', system-ui, sans-serif;
        }

        /* Primary color utilities */
        .bg-primary { background-color: var(--color-primary) !important; }
        .bg-primary\/90 { background-color: color-mix(in srgb, var(--color-primary) 90%, transparent) !important; }
        .text-primary { color: var(--color-primary) !important; }
        .border-primary { border-color: var(--color-primary) !important; }
        .from-primary { --tw-gradient-from: var(--color-primary); }
        .to-primary { --tw-gradient-to: var(--color-primary); }

        /* Secondary color utilities */
        .bg-secondary { background-color: var(--color-secondary) !important; }
        .text-secondary { color: var(--color-secondary) !important; }
        .border-secondary { border-color: var(--color-secondary) !important; }
        .from-secondary { --tw-gradient-from: var(--color-secondary); }
        .to-secondary { --tw-gradient-to: var(--color-secondary); }

        /* Accent color utilities */
        .bg-accent { background-color: var(--color-accent) !important; }
        .text-accent { color: var(--color-accent) !important; }
        .border-accent { border-color: var(--color-accent) !important; }

        /* Button styles */
        .btn-primary {
            background-color: var(--color-primary);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-primary:hover {
            opacity: 0.9;
        }

        .btn-secondary {
            border: 2px solid var(--color-primary);
            color: var(--color-primary);
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: transparent;
        }
        .btn-secondary:hover {
            background-color: var(--color-primary);
            color: white;
        }

        /* Gradient backgrounds */
        .bg-gradient-primary {
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
        }

        /* RTL adjustments */
        [dir="rtl"] .space-x-4 > :not([hidden]) ~ :not([hidden]) {
            --tw-space-x-reverse: 1;
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
