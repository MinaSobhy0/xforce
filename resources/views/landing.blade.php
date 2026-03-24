@php
    use App\Models\PlatformSetting;

    $platformName = PlatformSetting::get('platform_name', 'XLinic');
    $websiteLogo = PlatformSetting::get('website_logo') ?: PlatformSetting::get('platform_logo');
    $footerText = PlatformSetting::get('footer_text', '© ' . date('Y') . ' XLinic. ' . __('landing.footer.copyright'));
    $supportEmail = PlatformSetting::get('support_email', 'support@xlinic.com');
    $recaptchaEnabled = PlatformSetting::get('recaptcha_enabled', false);
    $recaptchaSiteKey = PlatformSetting::get('recaptcha_site_key', '');
    $isRtl = app()->getLocale() === 'ar';
    $currentLocale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ $currentLocale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ __('landing.description') }}">
    @php $favicon = PlatformSetting::get('favicon'); @endphp
    @if($favicon)
    <link rel="icon" href="{{ asset('storage/' . $favicon) }}">
    @endif
    <title>{{ __('landing.title') }}</title>

    <!-- Fonts - SF Pro inspired -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Phone Input -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css">

    <!-- reCAPTCHA -->
    @if($recaptchaEnabled && $recaptchaSiteKey)
    <script src="https://www.google.com/recaptcha/api.js?render={{ $recaptchaSiteKey }}"></script>
    @endif

    <style>
        /* ===== CSS Variables - Apple Inspired ===== */
        :root {
            --color-bg: #ffffff;
            --color-bg-secondary: #fbfbfd;
            --color-bg-dark: #000000;
            --color-text: #1d1d1f;
            --color-text-secondary: #86868b;
            --color-text-light: #f5f5f7;
            --color-link: #0066cc;
            --color-link-hover: #0077ed;
            --color-border: #d2d2d7;
            --color-accent: #0071e3;
            --font-body: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-arabic: 'Cairo', -apple-system, BlinkMacSystemFont, sans-serif;
            --max-width: 980px;
            --max-width-wide: 1200px;
        }

        /* ===== Reset & Base ===== */
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            font-family: var(--font-body);
            background: var(--color-bg);
            color: var(--color-text);
            font-size: 17px;
            line-height: 1.47059;
            font-weight: 400;
            letter-spacing: -0.022em;
        }

        [dir="rtl"] body {
            font-family: var(--font-arabic);
        }

        /* ===== Navigation ===== */
        nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 9999;
            background: rgba(251, 251, 253, 0.8);
            backdrop-filter: saturate(180%) blur(20px);
            -webkit-backdrop-filter: saturate(180%) blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }

        .nav-content {
            max-width: var(--max-width-wide);
            margin: 0 auto;
            padding: 0 22px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .nav-logo {
            font-size: 21px;
            font-weight: 600;
            color: var(--color-text);
            text-decoration: none;
            letter-spacing: -0.02em;
        }

        .nav-logo-img {
            height: 28px;
            width: auto;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 32px;
        }

        .nav-link {
            font-size: 14px;
            color: var(--color-text);
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.3s;
        }

        .nav-link:hover {
            opacity: 1;
        }

        .nav-cta {
            font-size: 14px;
            color: var(--color-link);
            text-decoration: none;
            font-weight: 400;
            transition: color 0.3s;
        }

        .nav-cta:hover {
            color: var(--color-link-hover);
            text-decoration: underline;
        }

        .lang-switch {
            display: flex;
            gap: 8px;
            font-size: 14px;
        }

        .lang-switch a {
            color: var(--color-text-secondary);
            text-decoration: none;
            padding: 4px 8px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .lang-switch a:hover {
            color: var(--color-text);
        }

        .lang-switch a.active {
            color: var(--color-text);
            background: rgba(0, 0, 0, 0.05);
        }

        /* ===== Hero Section ===== */
        .hero {
            padding-top: 48px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            background: linear-gradient(180deg, #fbfbfd 0%, #ffffff 100%);
        }

        .hero-content {
            max-width: 800px;
            padding: 80px 24px;
        }

        .hero-eyebrow {
            font-size: 21px;
            font-weight: 600;
            color: var(--color-text-secondary);
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }

        .hero-title {
            font-size: clamp(48px, 8vw, 80px);
            font-weight: 700;
            letter-spacing: -0.04em;
            line-height: 1.05;
            margin-bottom: 24px;
            background: linear-gradient(180deg, #1d1d1f 0%, #424245 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        [dir="rtl"] .hero-title {
            font-family: var(--font-arabic);
            letter-spacing: 0;
        }

        .hero-subtitle {
            font-size: 21px;
            line-height: 1.381;
            font-weight: 400;
            color: var(--color-text-secondary);
            max-width: 600px;
            margin: 0 auto 40px;
        }

        .hero-cta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            justify-content: center;
        }

        /* ===== Buttons ===== */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            font-size: 17px;
            font-weight: 400;
            text-decoration: none;
            border-radius: 980px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
            letter-spacing: -0.022em;
        }

        .btn-primary {
            background: var(--color-accent);
            color: #fff;
        }

        .btn-primary:hover {
            background: #0077ed;
        }

        .btn-secondary {
            background: transparent;
            color: var(--color-link);
        }

        .btn-secondary:hover {
            color: var(--color-link-hover);
            text-decoration: underline;
        }

        .btn-dark {
            background: var(--color-bg-dark);
            color: #fff;
        }

        .btn-dark:hover {
            background: #333;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* ===== Features Section ===== */
        .features {
            padding: 100px 0;
            background: var(--color-bg);
        }

        .section-header {
            text-align: center;
            max-width: var(--max-width);
            margin: 0 auto 80px;
            padding: 0 24px;
        }

        .section-eyebrow {
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--color-text-secondary);
            margin-bottom: 8px;
        }

        .section-title {
            font-size: clamp(32px, 5vw, 48px);
            font-weight: 700;
            letter-spacing: -0.03em;
            line-height: 1.1;
            margin-bottom: 16px;
        }

        [dir="rtl"] .section-title {
            font-family: var(--font-arabic);
            letter-spacing: 0;
        }

        .section-subtitle {
            font-size: 19px;
            color: var(--color-text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            max-width: var(--max-width-wide);
            margin: 0 auto;
            padding: 0 24px;
        }

        .feature-card {
            background: var(--color-bg-secondary);
            border-radius: 20px;
            padding: 32px 24px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.08);
        }

        .feature-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
        }

        .feature-icon svg {
            width: 28px;
            height: 28px;
            color: #fff;
        }

        .feature-card:nth-child(2) .feature-icon {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .feature-card:nth-child(3) .feature-icon {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .feature-card:nth-child(4) .feature-icon {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .feature-card:nth-child(5) .feature-icon {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .feature-card:nth-child(6) .feature-icon {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        }

        .feature-card:nth-child(6) .feature-icon svg {
            color: #333;
        }

        .feature-card:nth-child(7) .feature-icon {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
        }

        .feature-card:nth-child(7) .feature-icon svg {
            color: #333;
        }

        .feature-card:nth-child(8) .feature-icon {
            background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%);
        }

        .feature-title {
            font-size: 19px;
            font-weight: 600;
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }

        [dir="rtl"] .feature-title {
            font-family: var(--font-arabic);
        }

        .feature-description {
            font-size: 14px;
            color: var(--color-text-secondary);
            line-height: 1.5;
        }

        /* ===== Contact Section ===== */
        .contact {
            padding: 100px 0;
            background: var(--color-bg-dark);
            color: var(--color-text-light);
        }

        .contact .section-title {
            color: #fff;
        }

        .contact .section-subtitle {
            color: rgba(255, 255, 255, 0.7);
        }

        .contact-wrapper {
            max-width: 540px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .contact-form {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-row .form-group {
            margin-bottom: 0;
        }

        .form-group {
            margin-bottom: 16px;
        }

        /* Ensure phone input container matches other inputs */
        .form-group .iti {
            height: auto;
        }

        .form-group .iti input {
            height: auto;
        }

        .form-group.full-width {
            grid-column: span 2;
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 8px;
        }

        .form-label .required {
            color: #ff6b6b;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 14px 16px;
            font-size: 17px;
            font-family: inherit;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            color: #fff;
            transition: all 0.3s;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--color-accent);
            background: rgba(255, 255, 255, 0.1);
        }

        .form-input::placeholder,
        .form-textarea::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        .form-select option {
            background: #1d1d1f;
            color: #fff;
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            margin-top: 24px;
        }

        .form-actions .btn {
            width: 100%;
            padding: 16px 32px;
            font-size: 17px;
            font-weight: 500;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: rgba(52, 199, 89, 0.15);
            border: 1px solid rgba(52, 199, 89, 0.3);
            color: #34c759;
        }

        .alert-error {
            background: rgba(255, 59, 48, 0.15);
            border: 1px solid rgba(255, 59, 48, 0.3);
            color: #ff3b30;
        }

        .recaptcha-notice {
            margin-top: 16px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
            text-align: center;
        }

        .recaptcha-notice a {
            color: rgba(255, 255, 255, 0.7);
        }

        .grecaptcha-badge {
            visibility: hidden;
        }

        /* ===== Footer ===== */
        footer {
            background: var(--color-bg-secondary);
            border-top: 1px solid var(--color-border);
        }

        .footer-content {
            max-width: var(--max-width);
            margin: 0 auto;
            padding: 20px 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .footer-copyright {
            font-size: 12px;
            color: var(--color-text-secondary);
        }

        .footer-links {
            display: flex;
            gap: 24px;
        }

        .footer-link {
            font-size: 12px;
            color: var(--color-text-secondary);
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-link:hover {
            color: var(--color-text);
        }

        /* ===== Phone Input ===== */
        .iti {
            display: block !important;
            width: 100% !important;
        }

        .iti input,
        .iti input[type="tel"] {
            width: 100% !important;
            height: auto !important;
            padding: 14px 16px 14px 90px !important;
            font-size: 17px !important;
            font-family: inherit !important;
            background: rgba(255, 255, 255, 0.08) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            border-radius: 12px !important;
            color: #fff !important;
            transition: all 0.3s !important;
            box-sizing: border-box !important;
        }

        [dir="rtl"] .iti input,
        [dir="rtl"] .iti input[type="tel"] {
            padding: 14px 90px 14px 16px !important;
        }

        .iti input:focus,
        .iti input[type="tel"]:focus {
            outline: none !important;
            border-color: var(--color-accent) !important;
            background: rgba(255, 255, 255, 0.1) !important;
        }

        .iti input::placeholder {
            color: rgba(255, 255, 255, 0.4) !important;
        }

        /* Flag container */
        .iti__flag-container {
            position: absolute !important;
            top: 0 !important;
            bottom: 0 !important;
            left: 0 !important;
            right: auto !important;
            padding: 0 !important;
        }

        [dir="rtl"] .iti__flag-container {
            left: auto !important;
            right: 0 !important;
        }

        .iti__selected-country {
            display: flex !important;
            align-items: center !important;
            height: 100% !important;
            padding: 0 8px 0 12px !important;
            background: transparent !important;
            border-radius: 12px 0 0 12px !important;
            outline: none !important;
        }

        [dir="rtl"] .iti__selected-country {
            padding: 0 12px 0 8px !important;
            border-radius: 0 12px 12px 0 !important;
        }

        .iti__selected-country:hover,
        .iti__selected-country:focus {
            background: rgba(255, 255, 255, 0.05) !important;
        }

        .iti__selected-dial-code {
            color: rgba(255, 255, 255, 0.8) !important;
            font-size: 15px !important;
            margin-left: 6px !important;
        }

        [dir="rtl"] .iti__selected-dial-code {
            margin-left: 0 !important;
            margin-right: 6px !important;
        }

        .iti__arrow {
            margin-left: 6px !important;
            border-left: 4px solid transparent !important;
            border-right: 4px solid transparent !important;
            border-top: 4px solid rgba(255, 255, 255, 0.5) !important;
        }

        [dir="rtl"] .iti__arrow {
            margin-left: 0 !important;
            margin-right: 6px !important;
        }

        .iti__arrow--up {
            border-top: none !important;
            border-bottom: 4px solid rgba(255, 255, 255, 0.5) !important;
        }

        /* Dropdown */
        .iti--container {
            z-index: 9999 !important;
        }

        .iti__dropdown-content {
            position: absolute !important;
            background: #2a2a2a !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            border-radius: 12px !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6) !important;
            margin-top: 4px !important;
            overflow: hidden !important;
            z-index: 9999 !important;
            max-width: 300px !important;
        }

        .iti__country-list {
            list-style: none !important;
            margin: 0 !important;
            padding: 4px 0 !important;
            max-height: 200px !important;
            overflow-y: auto !important;
            background: #2a2a2a !important;
        }

        .iti__country {
            display: flex !important;
            align-items: center !important;
            padding: 10px 14px !important;
            cursor: pointer !important;
            gap: 10px !important;
        }

        .iti__country:hover,
        .iti__country--highlight {
            background: rgba(255, 255, 255, 0.1) !important;
        }

        .iti__country-name {
            color: #fff !important;
            flex: 1 !important;
            font-size: 14px !important;
        }

        .iti__dial-code {
            color: rgba(255, 255, 255, 0.5) !important;
            font-size: 13px !important;
        }

        .iti__flag {
            margin-right: 0 !important;
        }

        [dir="rtl"] .iti__flag {
            margin-right: 0 !important;
            margin-left: 0 !important;
        }

        /* Dropdown when appended to body */
        body > .iti--container {
            z-index: 99999 !important;
        }

        body > .iti--container .iti__dropdown-content {
            background: #2a2a2a !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            border-radius: 12px !important;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.7) !important;
        }

        body > .iti--container .iti__country-list {
            background: #2a2a2a !important;
        }

        body > .iti--container .iti__country {
            padding: 12px 16px !important;
        }

        body > .iti--container .iti__country:hover,
        body > .iti--container .iti__country--highlight {
            background: rgba(255, 255, 255, 0.1) !important;
        }

        body > .iti--container .iti__country-name {
            color: #fff !important;
        }

        body > .iti--container .iti__dial-code {
            color: rgba(255, 255, 255, 0.5) !important;
        }

        /* ===== Animations ===== */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .animate-spin {
            animation: spin 1s linear infinite;
        }

        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ===== Responsive ===== */
        @media (max-width: 1068px) {
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 734px) {
            .nav-links {
                display: none;
            }

            .hero-content {
                padding: 60px 24px;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: span 1;
            }

            .contact-form {
                padding: 24px;
            }

            .footer-content {
                flex-direction: column;
                text-align: center;
            }
        }

        /* ===== Mobile Nav Toggle ===== */
        .mobile-lang {
            display: none;
        }

        @media (max-width: 734px) {
            .mobile-lang {
                display: flex;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav>
        <div class="nav-content">
            <a href="/" class="nav-logo">
                @if($websiteLogo)
                    <img src="{{ asset('storage/' . $websiteLogo) }}" alt="{{ $platformName }}" class="nav-logo-img">
                @else
                    {{ $platformName }}
                @endif
            </a>

            <div class="nav-links">
                <a href="#features" class="nav-link">{{ __('landing.nav.features') }}</a>
                <a href="#contact" class="nav-link">{{ __('landing.nav.contact') }}</a>
                <a href="/admin" class="nav-cta">{{ __('landing.nav.login') }}</a>
                <div class="lang-switch">
                    <a href="{{ url('?locale=en') }}" class="{{ $currentLocale === 'en' ? 'active' : '' }}">EN</a>
                    <a href="{{ url('?locale=ar') }}" class="{{ $currentLocale === 'ar' ? 'active' : '' }}">عربي</a>
                </div>
            </div>

            <div class="lang-switch mobile-lang">
                <a href="{{ url('?locale=en') }}" class="{{ $currentLocale === 'en' ? 'active' : '' }}">EN</a>
                <a href="{{ url('?locale=ar') }}" class="{{ $currentLocale === 'ar' ? 'active' : '' }}">عربي</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <p class="hero-eyebrow">{{ __('landing.hero.badge') }}</p>
            <h1 class="hero-title">{{ __('landing.hero.title') }}</h1>
            <p class="hero-subtitle">{{ __('landing.hero.subtitle') }}</p>
            <div class="hero-cta">
                <a href="#contact" class="btn btn-dark">{{ __('landing.hero.cta_primary') }}</a>
                <a href="#features" class="btn btn-secondary">{{ __('landing.hero.cta_secondary') }} <span aria-hidden="true">&rarr;</span></a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="section-header fade-in">
            <p class="section-eyebrow">{{ __('landing.nav.features') }}</p>
            <h2 class="section-title">{{ __('landing.features.title') }}</h2>
            <p class="section-subtitle">{{ __('landing.features.subtitle') }}</p>
        </div>

        <div class="features-grid">
            <!-- Patient Management -->
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </div>
                <h3 class="feature-title">{{ __('landing.features.patient_management.title') }}</h3>
                <p class="feature-description">{{ __('landing.features.patient_management.description') }}</p>
            </div>

            <!-- Smart Scheduling -->
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <h3 class="feature-title">{{ __('landing.features.smart_scheduling.title') }}</h3>
                <p class="feature-description">{{ __('landing.features.smart_scheduling.description') }}</p>
            </div>

            <!-- Billing & Payments -->
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                    </svg>
                </div>
                <h3 class="feature-title">{{ __('landing.features.billing_payments.title') }}</h3>
                <p class="feature-description">{{ __('landing.features.billing_payments.description') }}</p>
            </div>

            <!-- Inventory & Equipment -->
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                    </svg>
                </div>
                <h3 class="feature-title">{{ __('landing.features.inventory_equipment.title') }}</h3>
                <p class="feature-description">{{ __('landing.features.inventory_equipment.description') }}</p>
            </div>

            <!-- Staff & Payroll -->
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" />
                    </svg>
                </div>
                <h3 class="feature-title">{{ __('landing.features.staff_payroll.title') }}</h3>
                <p class="feature-description">{{ __('landing.features.staff_payroll.description') }}</p>
            </div>

            <!-- Marketing & Loyalty -->
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                    </svg>
                </div>
                <h3 class="feature-title">{{ __('landing.features.marketing_loyalty.title') }}</h3>
                <p class="feature-description">{{ __('landing.features.marketing_loyalty.description') }}</p>
            </div>

            <!-- Analytics & Reports -->
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                    </svg>
                </div>
                <h3 class="feature-title">{{ __('landing.features.analytics_reports.title') }}</h3>
                <p class="feature-description">{{ __('landing.features.analytics_reports.description') }}</p>
            </div>

            <!-- Patient Portal -->
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                    </svg>
                </div>
                <h3 class="feature-title">{{ __('landing.features.patient_portal.title') }}</h3>
                <p class="feature-description">{{ __('landing.features.patient_portal.description') }}</p>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact" id="contact">
        <div class="section-header fade-in">
            <h2 class="section-title">{{ __('landing.contact.title') }}</h2>
            <p class="section-subtitle">{{ __('landing.contact.subtitle') }}</p>
        </div>

        <div class="contact-wrapper fade-in">
            <div class="contact-form">
                @if(session('success'))
                    <div class="alert alert-success">
                        {{ __('landing.contact.success') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-error">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form action="{{ route('contact.submit') }}" method="POST" id="contact-form">
                    @csrf
                    <div class="form-row">
                        <div class="form-group">
                            <label for="clinic_name" class="form-label">
                                {{ __('landing.contact.form.clinic_name') }} <span class="required">*</span>
                            </label>
                            <input type="text" id="clinic_name" name="clinic_name" class="form-input" value="{{ old('clinic_name') }}" required>
                        </div>

                        <div class="form-group">
                            <label for="contact_name" class="form-label">
                                {{ __('landing.contact.form.contact_name') }} <span class="required">*</span>
                            </label>
                            <input type="text" id="contact_name" name="contact_name" class="form-input" value="{{ old('contact_name') }}" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone" class="form-label">
                                {{ __('landing.contact.form.phone') }} <span class="required">*</span>
                            </label>
                            <input type="tel" id="phone" value="{{ old('phone') }}" required>
                            <input type="hidden" id="phone_full" name="phone">
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">
                                {{ __('landing.contact.form.email') }}
                            </label>
                            <input type="email" id="email" name="email" class="form-input" value="{{ old('email') }}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="country" class="form-label">
                            {{ __('landing.contact.form.country') }} <span class="required">*</span>
                        </label>
                        <select id="country" name="country" class="form-select" required>
                            <option value="">{{ __('landing.contact.form.country_select') }}</option>
                            @foreach(__('landing.countries') as $code => $name)
                                <option value="{{ $code }}" {{ old('country') == $code ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="message" class="form-label">
                            {{ __('landing.contact.form.message') }}
                        </label>
                        <textarea id="message" name="message" class="form-textarea">{{ old('message') }}</textarea>
                    </div>

                    @if($recaptchaEnabled && $recaptchaSiteKey)
                    <input type="hidden" name="recaptcha_token" id="recaptcha_token">
                    @endif

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="submit-btn">
                            {{ __('landing.contact.form.submit') }}
                        </button>
                    </div>

                    @if($recaptchaEnabled && $recaptchaSiteKey)
                    <p class="recaptcha-notice">
                        This site is protected by reCAPTCHA and the Google
                        <a href="https://policies.google.com/privacy" target="_blank">Privacy Policy</a> and
                        <a href="https://policies.google.com/terms" target="_blank">Terms of Service</a> apply.
                    </p>
                    @endif
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <p class="footer-copyright">
                &copy; {{ date('Y') }} {{ $platformName }}. {{ __('landing.footer.copyright') }}
            </p>
            <div class="footer-links">
                <a href="#" class="footer-link">{{ __('landing.footer.privacy') }}</a>
                <a href="#" class="footer-link">{{ __('landing.footer.terms') }}</a>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ===== Phone Input =====
            const phoneInput = document.querySelector('#phone');
            const phoneFullInput = document.querySelector('#phone_full');
            const countrySelect = document.querySelector('#country');
            const form = document.querySelector('#contact-form');
            const submitBtn = document.querySelector('#submit-btn');

            const iti = window.intlTelInput(phoneInput, {
                initialCountry: "eg",
                nationalMode: false,
                separateDialCode: true,
                preferredCountries: ["eg", "sa", "ae", "kw", "qa", "bh", "om", "jo", "lb"],
                onlyCountries: ["eg", "sa", "ae", "kw", "qa", "bh", "om", "jo", "lb"],
                showSelectedDialCode: true,
                useFullscreenPopup: false,
                countrySearch: false,
                dropdownContainer: document.body,
                utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"
            });

            // Sync phone country with country dropdown
            if (countrySelect) {
                countrySelect.addEventListener('change', function() {
                    const selectedCountry = this.value.toLowerCase();
                    if (selectedCountry) {
                        iti.setCountry(selectedCountry);
                    }
                });
            }

            phoneInput.addEventListener('change', function() {
                phoneFullInput.value = iti.getNumber();
            });

            if (countrySelect) {
                phoneInput.addEventListener('countrychange', function() {
                    const countryData = iti.getSelectedCountryData();
                    if (countryData && countryData.iso2) {
                        const countryCode = countryData.iso2.toUpperCase();
                        if (countrySelect.querySelector(`option[value="${countryCode}"]`)) {
                            countrySelect.value = countryCode;
                        }
                    }
                });
            }

            // ===== Form Submission =====
            form.addEventListener('submit', function(e) {
                phoneFullInput.value = iti.getNumber();

                @if($recaptchaEnabled && $recaptchaSiteKey)
                e.preventDefault();
                submitBtn.disabled = true;
                submitBtn.textContent = '{{ __('landing.contact.form.submitting') }}';

                grecaptcha.ready(function() {
                    grecaptcha.execute('{{ $recaptchaSiteKey }}', {action: 'contact_form'}).then(function(token) {
                        document.getElementById('recaptcha_token').value = token;
                        form.submit();
                    }).catch(function() {
                        submitBtn.disabled = false;
                        submitBtn.textContent = '{{ __('landing.contact.form.submit') }}';
                        alert('reCAPTCHA verification failed. Please try again.');
                    });
                });
                @endif
            });

            // ===== Smooth Scroll =====
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        const navHeight = document.querySelector('nav').offsetHeight;
                        const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - navHeight;
                        window.scrollTo({
                            top: targetPosition,
                            behavior: 'smooth'
                        });
                    }
                });
            });

            // ===== Scroll Animations =====
            const fadeElements = document.querySelectorAll('.fade-in');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });

            fadeElements.forEach(el => observer.observe(el));
        });
    </script>
</body>
</html>
