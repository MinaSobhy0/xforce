@php
    use App\Models\PlatformSetting;

    $platformName = PlatformSetting::get('platform_name', 'XLinic');
    $websiteLogo = PlatformSetting::get('website_logo') ?: PlatformSetting::get('platform_logo');
    $primaryColor = PlatformSetting::get('primary_color', '#3b82f6');
    $footerText = PlatformSetting::get('footer_text', '© ' . date('Y') . ' XLinic. All rights reserved.');
    $supportEmail = PlatformSetting::get('support_email', 'support@xlinic.com');
    $recaptchaEnabled = PlatformSetting::get('recaptcha_enabled', false);
    $recaptchaSiteKey = PlatformSetting::get('recaptcha_site_key', '');
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $platformName }} - Clinic Management Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css">
    @if($recaptchaEnabled && $recaptchaSiteKey)
    <script src="https://www.google.com/recaptcha/api.js?render={{ $recaptchaSiteKey }}"></script>
    @endif
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            color: #e2e8f0;
            overflow-x: hidden;
        }

        .background-pattern {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image:
                radial-gradient(circle at 25% 25%, rgba(59, 130, 246, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(139, 92, 246, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .container {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .logo-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, {{ $primaryColor }} 0%, #8b5cf6 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.3);
        }

        .logo-icon svg {
            width: 28px;
            height: 28px;
            color: white;
        }

        .logo-image {
            max-height: 48px;
            width: auto;
        }

        .logo-text {
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(135deg, {{ $primaryColor }} 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }

        .header-links a {
            color: #94a3b8;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .header-links a:hover {
            color: #e2e8f0;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, {{ $primaryColor }} 0%, #8b5cf6 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(59, 130, 246, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        main {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 2rem 0;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 100px;
            font-size: 0.875rem;
            color: #60a5fa;
            margin-bottom: 2rem;
        }

        .hero-badge svg {
            width: 16px;
            height: 16px;
        }

        h1 {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 1rem;
            max-width: 800px;
        }

        h1 span {
            background: linear-gradient(135deg, {{ $primaryColor }} 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-description {
            font-size: 1.125rem;
            color: #94a3b8;
            max-width: 600px;
            line-height: 1.7;
            margin-bottom: 2rem;
        }

        /* Contact Form */
        .contact-section {
            width: 100%;
            max-width: 600px;
            margin: 2rem auto;
        }

        .contact-form {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 2rem;
            text-align: left;
        }

        .contact-form h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: #f1f5f9;
        }

        .contact-form .subtitle {
            color: #94a3b8;
            margin-bottom: 1.5rem;
            font-size: 0.9375rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #e2e8f0;
            margin-bottom: 0.5rem;
        }

        .form-group label .required {
            color: #f87171;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #e2e8f0;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.2s, background 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: {{ $primaryColor }};
            background: rgba(255, 255, 255, 0.08);
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #64748b;
        }

        .form-group select option {
            background: #1e293b;
            color: #e2e8f0;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            margin-top: 1.5rem;
        }

        .form-actions .btn-primary {
            width: 100%;
        }

        .recaptcha-notice {
            margin-top: 1rem;
            font-size: 0.75rem;
            color: #64748b;
            text-align: center;
        }

        .recaptcha-notice a {
            color: #94a3b8;
            text-decoration: underline;
        }

        .recaptcha-notice a:hover {
            color: #e2e8f0;
        }

        /* Hide reCAPTCHA badge (we show notice instead) */
        .grecaptcha-badge {
            visibility: hidden;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #4ade80;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 3rem;
            width: 100%;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: left;
            transition: all 0.3s;
        }

        .feature-card:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(59, 130, 246, 0.3);
            transform: translateY(-4px);
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, {{ $primaryColor }}33 0%, rgba(139, 92, 246, 0.2) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .feature-icon svg {
            width: 24px;
            height: 24px;
            color: #60a5fa;
        }

        .feature-card h3 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #f1f5f9;
        }

        .feature-card p {
            color: #94a3b8;
            font-size: 0.9375rem;
            line-height: 1.6;
        }

        footer {
            text-align: center;
            padding: 2rem 0;
            color: #64748b;
            font-size: 0.875rem;
        }

        footer a {
            color: #94a3b8;
            text-decoration: none;
        }

        footer a:hover {
            color: #e2e8f0;
        }

        /* Phone input with country code */
        .iti {
            width: 100%;
        }

        .iti__flag-container {
            background: transparent;
        }

        .iti__selected-flag {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px 0 0 8px;
        }

        .iti__selected-flag:hover,
        .iti__selected-flag:focus {
            background: rgba(255, 255, 255, 0.1);
        }

        .iti__dropdown-content {
            background: #1e293b;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .iti__search-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #e2e8f0;
            border-radius: 6px;
            padding: 0.5rem;
        }

        .iti__search-input::placeholder {
            color: #64748b;
        }

        .iti__country-list {
            background: #1e293b;
            color: #e2e8f0;
        }

        .iti__country {
            padding: 8px 10px;
        }

        .iti__country:hover,
        .iti__country--highlight {
            background: rgba(59, 130, 246, 0.2);
        }

        .iti__dial-code {
            color: #94a3b8;
        }

        .iti__arrow {
            border-top-color: #94a3b8;
        }

        .iti__arrow--up {
            border-bottom-color: #94a3b8;
        }

        @media (max-width: 640px) {
            .header-links {
                display: none;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: span 1;
            }

            .contact-form {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="background-pattern"></div>

    <div class="container">
        <header>
            <div class="logo">
                @if($websiteLogo)
                    <img src="{{ asset('storage/' . $websiteLogo) }}" alt="{{ $platformName }}" class="logo-image">
                @else
                    <div class="logo-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 1-6.23.693L5 15.3m14.8 0 .21 1.047c.143.715-.194 1.44-.84 1.753a18.634 18.634 0 0 1-7.17 1.9 18.634 18.634 0 0 1-7.17-1.9 1.498 1.498 0 0 1-.84-1.753L5 15.3" />
                        </svg>
                    </div>
                    <span class="logo-text">{{ $platformName }}</span>
                @endif
            </div>

            <div class="header-links">
                <a href="#contact">Contact Us</a>
            </div>
        </header>

        <main>
            <div class="hero-badge">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                </svg>
                SaaS Platform for Clinics
            </div>

            <h1>Manage Your <span>Clinic</span> with Confidence</h1>

            <p class="hero-description">
                A comprehensive platform for laser and beauty clinics.
                Manage patients, appointments, treatments, and grow your business - all in one place.
            </p>

            <!-- Contact Form -->
            <section class="contact-section" id="contact">
                <div class="contact-form">
                    <h2>Get Started Today</h2>
                    <p class="subtitle">Fill out the form below and we'll get in touch with you.</p>

                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-error">
                            @foreach($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form action="{{ route('contact.submit') }}" method="POST">
                        @csrf
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="clinic_name">Clinic Name <span class="required">*</span></label>
                                <input type="text" id="clinic_name" name="clinic_name" value="{{ old('clinic_name') }}" required placeholder="Your clinic name">
                            </div>

                            <div class="form-group">
                                <label for="contact_name">Your Name <span class="required">*</span></label>
                                <input type="text" id="contact_name" name="contact_name" value="{{ old('contact_name') }}" required placeholder="Full name">
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone <span class="required">*</span></label>
                                <input type="tel" id="phone" value="{{ old('phone') }}" required placeholder="123 456 7890">
                                <input type="hidden" id="phone_full" name="phone">
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="your@email.com">
                            </div>

                            <div class="form-group full-width">
                                <label for="country">Country <span class="required">*</span></label>
                                <select id="country" name="country" required>
                                    <option value="">Select your country</option>
                                    <option value="EG" {{ old('country') == 'EG' ? 'selected' : '' }}>Egypt</option>
                                    <option value="SA" {{ old('country') == 'SA' ? 'selected' : '' }}>Saudi Arabia</option>
                                    <option value="AE" {{ old('country') == 'AE' ? 'selected' : '' }}>UAE</option>
                                    <option value="KW" {{ old('country') == 'KW' ? 'selected' : '' }}>Kuwait</option>
                                    <option value="QA" {{ old('country') == 'QA' ? 'selected' : '' }}>Qatar</option>
                                    <option value="BH" {{ old('country') == 'BH' ? 'selected' : '' }}>Bahrain</option>
                                    <option value="OM" {{ old('country') == 'OM' ? 'selected' : '' }}>Oman</option>
                                    <option value="JO" {{ old('country') == 'JO' ? 'selected' : '' }}>Jordan</option>
                                    <option value="LB" {{ old('country') == 'LB' ? 'selected' : '' }}>Lebanon</option>
                                </select>
                            </div>

                            <div class="form-group full-width">
                                <label for="message">Message</label>
                                <textarea id="message" name="message" placeholder="Tell us about your clinic and what you're looking for...">{{ old('message') }}</textarea>
                            </div>
                        </div>

                        @if($recaptchaEnabled && $recaptchaSiteKey)
                        <input type="hidden" name="recaptcha_token" id="recaptcha_token">
                        @endif

                        <div class="form-actions">
                            <button type="submit" class="btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                                </svg>
                                Send Inquiry
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
            </section>

            <div class="features">
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                    <h3>Patient Management</h3>
                    <p>Complete patient profiles, medical history, and treatment records in one secure place.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                    </div>
                    <h3>Smart Scheduling</h3>
                    <p>Efficient appointment booking with automated reminders and calendar management.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                    </div>
                    <h3>Analytics & Reports</h3>
                    <p>Insights and reports to help you understand and grow your clinic business.</p>
                </div>
            </div>
        </main>

        <footer>
            <p>{!! $footerText !!}</p>
        </footer>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInput = document.querySelector('#phone');
            const phoneFullInput = document.querySelector('#phone_full');
            const countrySelect = document.querySelector('#country');
            const form = document.querySelector('form');
            const submitBtn = form.querySelector('button[type="submit"]');

            // Initialize intl-tel-input
            const iti = window.intlTelInput(phoneInput, {
                initialCountry: "auto",
                geoIpLookup: function(callback) {
                    fetch('https://ipapi.co/json/')
                        .then(res => res.json())
                        .then(data => {
                            const countryCode = (data && data.country_code) ? data.country_code : "EG";
                            callback(countryCode);
                            // Also set the country dropdown
                            if (countrySelect && countrySelect.querySelector(`option[value="${countryCode}"]`)) {
                                countrySelect.value = countryCode;
                            }
                        })
                        .catch(() => callback("EG"));
                },
                preferredCountries: ["eg", "sa", "ae", "kw", "qa", "bh", "om", "jo", "lb"],
                separateDialCode: true,
                utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"
            });

            // Also update on input change
            phoneInput.addEventListener('change', function() {
                phoneFullInput.value = iti.getNumber();
            });

            // Sync country selection with phone country
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

            // Handle form submission with reCAPTCHA v3
            form.addEventListener('submit', function(e) {
                // Update phone number
                phoneFullInput.value = iti.getNumber();

                @if($recaptchaEnabled && $recaptchaSiteKey)
                e.preventDefault();
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Sending...';

                grecaptcha.ready(function() {
                    grecaptcha.execute('{{ $recaptchaSiteKey }}', {action: 'contact_form'}).then(function(token) {
                        document.getElementById('recaptcha_token').value = token;
                        form.submit();
                    }).catch(function() {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" /></svg> Send Inquiry';
                        alert('reCAPTCHA verification failed. Please try again.');
                    });
                });
                @endif
            });
        });
    </script>
</body>
</html>
