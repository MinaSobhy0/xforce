@php
    use App\Models\PlatformSetting;

    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $platformName = PlatformSetting::get('platform_name', 'XLinic');
    $websiteLogo = PlatformSetting::get('website_logo') ?: PlatformSetting::get('platform_logo');
    $favicon = PlatformSetting::get('favicon');
    $recaptchaEnabled = PlatformSetting::get('recaptcha_enabled', false);
    $recaptchaSiteKey = PlatformSetting::get('recaptcha_site_key', '');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('landing.meta.title') }}</title>
    <meta name="description" content="{{ __('landing.meta.description') }}">

    {{-- Open Graph --}}
    <meta property="og:title" content="{{ __('landing.meta.title') }}">
    <meta property="og:description" content="{{ __('landing.meta.description') }}">
    <meta property="og:type" content="website">

    @if($favicon)
        <link rel="icon" href="{{ asset('storage/' . $favicon) }}">
    @endif

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- Tailwind (compiled) --}}
    <link rel="stylesheet" href="{{ asset('css/landing-tw.css') }}?v=2">

    {{-- Custom styles --}}
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v=10">

    {{-- Intl Tel Input (served locally to comply with CSP) --}}
    <link rel="stylesheet" href="{{ asset('css/intl-tel-input.css') }}?v=1">

    {{-- reCAPTCHA v3 --}}
    @if($recaptchaEnabled && $recaptchaSiteKey)
        <script src="https://www.google.com/recaptcha/api.js?render={{ $recaptchaSiteKey }}"></script>
    @endif

    {{-- Spline 3D Viewer --}}
    <script type="module" src="https://unpkg.com/@splinetool/viewer@1.9.48/build/spline-viewer.js" defer></script>

    {{-- Theme bootstrap — must run before <body> to prevent flash --}}
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
</head>

<body class="bg-ink-900 text-white antialiased {{ $isRtl ? 'font-ar' : 'font-sans' }}">
    {{-- Ambient background --}}
    <div class="ambient-bg" aria-hidden="true"></div>
    <div class="grain" aria-hidden="true"></div>

    @include('landing.partials.header', ['platformName' => $platformName, 'websiteLogo' => $websiteLogo, 'isRtl' => $isRtl])

    <main>
        @include('landing.partials.hero')
        @include('landing.partials.trust')
        @include('landing.partials.problem')
        @include('landing.partials.solution')
        @include('landing.partials.how-it-works')
        @include('landing.partials.results')
        @include('landing.partials.contact', ['recaptchaEnabled' => $recaptchaEnabled, 'recaptchaSiteKey' => $recaptchaSiteKey])
    </main>

    @include('landing.partials.footer', ['platformName' => $platformName, 'websiteLogo' => $websiteLogo])

    {{-- Phone input script --}}
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>

    {{-- Landing scripts --}}
    <script src="{{ asset('js/landing.js') }}?v=2"></script>

    @if($recaptchaEnabled && $recaptchaSiteKey)
        <script>
            window.__recaptchaSiteKey = @json($recaptchaSiteKey);
        </script>
    @endif
</body>
</html>
