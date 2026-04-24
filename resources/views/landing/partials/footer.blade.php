{{-- Footer --}}
<footer class="relative border-t border-white/5 bg-ink-900 py-16">
    <div class="max-w-7xl mx-auto px-6 md:px-10">
        <div class="grid md:grid-cols-2 gap-10 mb-12">
            {{-- Brand --}}
            <div>
                <div class="flex items-center gap-3 mb-4">
                    @if(!empty($websiteLogo))
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
                </div>
                <p class="text-sm text-muted max-w-md leading-relaxed">{{ __('landing.footer.tagline') }}</p>
            </div>

            {{-- Links --}}
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <div class="text-xs font-medium text-white/60 uppercase tracking-widest mb-4">
                        {{ __('landing.footer.links.product') }}
                    </div>
                    <ul class="space-y-3 text-sm">
                        <li><a href="#features" class="text-muted hover:text-white transition-colors">{{ __('landing.nav.features') }}</a></li>
                        <li><a href="#how-it-works" class="text-muted hover:text-white transition-colors">{{ __('landing.nav.how') }}</a></li>
                        <li><a href="{{ route('login') }}" class="text-muted hover:text-white transition-colors">{{ __('landing.nav.login') }}</a></li>
                    </ul>
                </div>
                <div>
                    <div class="text-xs font-medium text-white/60 uppercase tracking-widest mb-4">
                        {{ __('landing.footer.links.company') }}
                    </div>
                    <ul class="space-y-3 text-sm">
                        <li><a href="#contact" class="text-muted hover:text-white transition-colors">{{ __('landing.footer.links.contact') }}</a></li>
                        <li><a href="#" class="text-muted hover:text-white transition-colors">{{ __('landing.footer.links.privacy') }}</a></li>
                        <li><a href="#" class="text-muted hover:text-white transition-colors">{{ __('landing.footer.links.terms') }}</a></li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Bottom bar --}}
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pt-8 border-t border-white/5">
            <p class="text-xs text-muted">
                © {{ date('Y') }} {{ $platformName }}. {{ __('landing.footer.copyright') }}
            </p>
            <div class="flex items-center gap-1 text-xs font-medium">
                <a href="?locale=en" class="px-2 py-1 rounded lang-link {{ app()->getLocale() !== 'ar' ? 'lang-active' : '' }} transition-colors">EN</a>
                <a href="?locale=ar" class="px-2 py-1 rounded lang-link {{ app()->getLocale() === 'ar' ? 'lang-active' : '' }} transition-colors">عربي</a>
            </div>
        </div>
    </div>
</footer>
