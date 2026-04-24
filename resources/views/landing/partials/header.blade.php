{{-- Sticky glass header --}}
<header class="fixed top-0 inset-x-0 z-50 backdrop-blur-xl bg-ink-900/70 border-b border-white/5">
    <div class="max-w-7xl mx-auto px-6 md:px-10 h-16 flex items-center justify-between">
        {{-- Logo --}}
        <a href="#top" class="flex items-center gap-3 group">
            @if($websiteLogo)
                <span class="logo-wrap inline-flex items-center">
                    <img src="{{ asset('storage/' . $websiteLogo) }}" alt="{{ $platformName }}" class="h-8 w-auto">
                </span>
            @else
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-md bg-gradient-to-br from-cyan to-coral">
                    <svg class="w-5 h-5 text-ink-900" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 12h4l3-9 3 18 3-9h3"/>
                    </svg>
                </span>
                <span class="font-display font-semibold text-lg tracking-tight">{{ $platformName }}</span>
            @endif
        </a>

        {{-- Nav links (desktop) --}}
        <nav class="hidden md:flex items-center gap-8 text-sm text-muted">
            <a href="#features" class="hover:text-white transition-colors">{{ __('landing.nav.features') }}</a>
            <a href="#how-it-works" class="hover:text-white transition-colors">{{ __('landing.nav.how') }}</a>
            <a href="#contact" class="hover:text-white transition-colors">{{ __('landing.nav.contact') }}</a>
        </nav>

        {{-- Right cluster --}}
        <div class="flex items-center gap-2 md:gap-4">
            {{-- Theme toggle --}}
            <button type="button" id="theme-toggle"
                aria-label="Toggle theme"
                class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-white/10 hover:border-white/25 hover:bg-white/5 transition-colors theme-toggle-btn">
                {{-- Sun (shown in dark mode = "switch to light") --}}
                <svg class="w-4 h-4 icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="4"/>
                    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
                </svg>
                {{-- Moon (shown in light mode = "switch to dark") --}}
                <svg class="w-4 h-4 icon-moon hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
            </button>

            {{-- Language switcher --}}
            <div class="flex items-center gap-1 text-xs font-medium">
                <a href="?locale=en" class="px-2 py-1 rounded lang-link {{ !$isRtl ? 'lang-active' : '' }} transition-colors">EN</a>
                <a href="?locale=ar" class="px-2 py-1 rounded lang-link {{ $isRtl ? 'lang-active' : '' }} transition-colors">عربي</a>
            </div>

            {{-- Login --}}
            <a href="{{ route('login') }}" class="hidden sm:inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-white/15 text-sm font-medium hover:border-white/30 hover:bg-white/5 transition-all">
                {{ __('landing.nav.login') }}
                <svg class="w-4 h-4 {{ $isRtl ? 'rotate-180' : '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                </svg>
            </a>
        </div>
    </div>
</header>
