@php
    use App\Models\PlatformSetting;

    $platformName = PlatformSetting::get('platform_name', 'XLinic');
    $websiteLogo = PlatformSetting::get('website_logo') ?: PlatformSetting::get('platform_logo');
    // Legal pages use a dedicated support address, not the platform-wide setting,
    // so the policy stays stable even if a tenant overrides PlatformSetting.support_email.
    $supportEmail = 'support@xforcehr.com';
    $isRtl = app()->getLocale() === 'ar';
    $currentLocale = app()->getLocale();

    $pageTitle = $pageTitle ?? __('legal.privacy.title');
    $effectiveDate = $effectiveDate ?? '2026-04-25';
@endphp
<!DOCTYPE html>
<html lang="{{ $currentLocale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ $pageTitle }} — {{ $platformName }}">
    <title>{{ $pageTitle }} · {{ $platformName }}</title>

    @php $favicon = PlatformSetting::get('favicon'); @endphp
    @if($favicon)
        <link rel="icon" href="{{ asset('storage/' . $favicon) }}">
    @endif

    {{-- Same fonts as landing for visual continuity --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- Tailwind (same compiled bundle as landing) --}}
    <link rel="stylesheet" href="{{ asset('css/landing-tw.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v=10">

    {{-- Theme bootstrap (must run before <body> to prevent flash) --}}
    <script>
        (function() {
            try {
                var stored = localStorage.getItem('xlinic-theme');
                var prefersLight = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
                var theme = stored || (prefersLight ? 'light' : 'dark');
                if (theme === 'light') document.documentElement.classList.add('light');
            } catch (e) { /* ignore */ }
        })();
    </script>

    {{-- Prose styles for the legal copy --}}
    <style>
        .legal-prose { color: rgba(255,255,255,0.85); line-height: 1.75; font-size: 0.975rem; }
        .legal-prose h2 { font-family: 'Space Grotesk', sans-serif; font-size: 1.5rem; font-weight: 600; color: #fff; margin-top: 2.5rem; margin-bottom: 0.75rem; letter-spacing: -0.01em; }
        .legal-prose h3 { font-family: 'Space Grotesk', sans-serif; font-size: 1.125rem; font-weight: 600; color: #fff; margin-top: 1.75rem; margin-bottom: 0.5rem; }
        .legal-prose p { margin-bottom: 1rem; color: rgba(255,255,255,0.78); }
        .legal-prose strong { color: #fff; font-weight: 600; }
        .legal-prose a { color: #67e8f9; text-decoration: underline; text-underline-offset: 3px; text-decoration-color: rgba(103,232,249,0.4); transition: text-decoration-color 0.15s; }
        .legal-prose a:hover { text-decoration-color: rgba(103,232,249,0.9); }
        .legal-prose ul { list-style: none; padding-{{ $isRtl ? 'right' : 'left' }}: 1.25rem; margin-bottom: 1rem; }
        .legal-prose ul li { position: relative; padding-{{ $isRtl ? 'right' : 'left' }}: 0.5rem; margin-bottom: 0.5rem; color: rgba(255,255,255,0.78); }
        .legal-prose ul li::before { content: ''; position: absolute; {{ $isRtl ? 'right' : 'left' }}: -1.25rem; top: 0.65rem; width: 6px; height: 6px; border-radius: 9999px; background: linear-gradient(135deg, rgb(34 211 238), rgb(244 114 114)); }
        .legal-prose ol { list-style: decimal; padding-{{ $isRtl ? 'right' : 'left' }}: 1.5rem; margin-bottom: 1rem; }
        .legal-prose ol li { padding-{{ $isRtl ? 'right' : 'left' }}: 0.25rem; margin-bottom: 0.5rem; color: rgba(255,255,255,0.78); }
        .legal-prose code { background: rgba(255,255,255,0.06); padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.875em; color: #fde68a; }
        .legal-prose blockquote { border-{{ $isRtl ? 'right' : 'left' }}: 3px solid rgba(34,211,238,0.5); padding: 0.75rem 1rem; margin: 1.25rem 0; background: rgba(255,255,255,0.025); border-radius: 0.375rem; color: rgba(255,255,255,0.85); }
        .legal-prose .toc-anchor { scroll-margin-top: 5rem; }

        /* Light theme overrides */
        html.light .legal-prose { color: rgba(0,0,0,0.78); }
        html.light .legal-prose h2, html.light .legal-prose h3, html.light .legal-prose strong { color: rgba(0,0,0,0.92); }
        html.light .legal-prose p, html.light .legal-prose ul li, html.light .legal-prose ol li { color: rgba(0,0,0,0.72); }
        html.light .legal-prose blockquote { background: rgba(0,0,0,0.03); color: rgba(0,0,0,0.82); }
        html.light .legal-prose a { color: #0e7490; text-decoration-color: rgba(14,116,144,0.4); }
        html.light .legal-prose a:hover { text-decoration-color: rgba(14,116,144,0.9); }
        html.light .legal-prose code { background: rgba(0,0,0,0.05); color: #92400e; }
    </style>
</head>

<body class="bg-ink-900 text-white antialiased {{ $isRtl ? 'font-ar' : 'font-sans' }}">
    <div class="ambient-bg" aria-hidden="true"></div>
    <div class="grain" aria-hidden="true"></div>

    @include('landing.partials.header', ['platformName' => $platformName, 'websiteLogo' => $websiteLogo, 'isRtl' => $isRtl])

    <main class="pt-28 pb-24">
        <article class="max-w-3xl mx-auto px-6 md:px-10">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-xs text-muted hover:text-white transition-colors mb-6">
                <svg class="w-3.5 h-3.5 {{ $isRtl ? 'rotate-180' : '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                {{ __('legal.back_home') }}
            </a>

            <h1 class="font-display text-4xl md:text-5xl font-semibold tracking-tight mb-3">{{ $pageTitle }}</h1>
            <p class="text-sm text-muted mb-10">
                {{ __('legal.last_updated') }}: <time datetime="{{ $effectiveDate }}">{{ \Carbon\Carbon::parse($effectiveDate)->translatedFormat('F j, Y') }}</time>
            </p>

            <div class="legal-prose">
                @yield('content')
            </div>

            <div class="mt-16 pt-8 border-t border-white/5 text-sm text-muted">
                {!! __('legal.contact_footer', ['email' => '<a href="mailto:' . $supportEmail . '">' . $supportEmail . '</a>']) !!}
            </div>
        </article>
    </main>

    @include('landing.partials.footer', ['platformName' => $platformName, 'websiteLogo' => $websiteLogo])

    {{-- Theme toggle script (lightweight; no other landing JS needed) --}}
    <script>
        (function() {
            var btn = document.getElementById('theme-toggle');
            if (!btn) return;
            btn.addEventListener('click', function() {
                var isLight = document.documentElement.classList.toggle('light');
                try { localStorage.setItem('xlinic-theme', isLight ? 'light' : 'dark'); } catch (e) {}
            });
        })();
    </script>
</body>
</html>
