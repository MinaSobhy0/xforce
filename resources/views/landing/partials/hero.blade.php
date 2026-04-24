{{-- Hero with Spline 3D --}}
<section id="top" class="relative pt-32 md:pt-40 pb-20 md:pb-32 overflow-hidden">
    <div class="max-w-7xl mx-auto px-6 md:px-10 grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
        {{-- Text column --}}
        <div class="relative z-10 reveal">
            {{-- Eyebrow --}}
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-cyan/30 bg-cyan/5 mb-8">
                <span class="w-1.5 h-1.5 rounded-full bg-cyan animate-pulse"></span>
                <span class="text-xs font-medium text-cyan tracking-wider uppercase">{{ __('landing.hero.eyebrow') }}</span>
            </div>

            {{-- Headline --}}
            <h1 class="font-display text-5xl sm:text-6xl lg:text-7xl font-semibold tracking-tight leading-[1.05] mb-6">
                <span class="block text-white">{{ __('landing.hero.title_line_1') }}</span>
                <span class="block gradient-text">{{ __('landing.hero.title_line_2') }}</span>
            </h1>

            {{-- Subtitle --}}
            <p class="text-lg lg:text-xl text-muted max-w-xl leading-relaxed mb-10">
                {{ __('landing.hero.subtitle') }}
            </p>

            {{-- CTAs --}}
            <div class="flex flex-wrap items-center gap-4">
                <a href="#contact" class="group inline-flex items-center gap-2 px-6 py-3.5 rounded-lg bg-white text-ink-900 font-semibold text-sm hover:bg-white/90 transition-all shadow-[0_0_0_0_rgba(0,212,255,0.4)] hover:shadow-[0_0_40px_0_rgba(0,212,255,0.4)]">
                    {{ __('landing.hero.cta_primary') }}
                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-1 {{ app()->getLocale() === 'ar' ? 'rotate-180 group-hover:-translate-x-1' : '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 12h15"/>
                    </svg>
                </a>
                <a href="#features" class="inline-flex items-center gap-2 px-6 py-3.5 rounded-lg border border-white/15 text-sm font-semibold hover:border-white/40 hover:bg-white/5 transition-all">
                    {{ __('landing.hero.cta_secondary') }}
                </a>
            </div>

            {{-- Scroll hint --}}
            <div class="hidden lg:flex items-center gap-3 mt-16 text-xs text-muted/60 tracking-widest uppercase">
                <span class="w-8 h-px bg-muted/30"></span>
                <span>{{ __('landing.hero.scroll_hint') }}</span>
            </div>
        </div>

        {{-- 3D Scene column --}}
        <div class="relative h-[420px] sm:h-[520px] lg:h-[620px] reveal">
            {{-- Spline viewer container --}}
            <div class="absolute inset-0 flex items-center justify-center">
                {{-- Background glow --}}
                <div class="absolute inset-0 bg-gradient-radial from-cyan/20 via-transparent to-transparent blur-3xl"></div>

                {{--
                    Spline 3D scene.
                    To swap: create/open a scene at https://spline.design, set its
                    Background alpha to 0 in Design > Environment, export as
                    "Code Export > Public URL", and paste the .splinecode URL below.
                --}}
                <spline-viewer
                    class="w-full h-full relative z-10"
                    url="https://prod.spline.design/6Wq1Q7YGyM-iab9i/scene.splinecode"
                    loading-anim-type="none"
                    events-target="global"
                    style="background: transparent;"
                ></spline-viewer>

                {{-- Fallback (shown if Spline fails to load) --}}
                <div id="spline-fallback" class="absolute inset-0 hidden items-center justify-center">
                    <div class="relative w-full h-full flex items-center justify-center">
                        <div class="absolute w-64 h-64 md:w-80 md:h-80 rounded-full border border-cyan/20 animate-spin-slow"></div>
                        <div class="absolute w-48 h-48 md:w-64 md:h-64 rounded-full border border-cyan/30 animate-spin-slower"></div>
                        <div class="absolute w-32 h-32 md:w-40 md:h-40 rounded-full bg-gradient-to-br from-cyan/20 to-coral/20 blur-xl"></div>
                        <svg class="relative w-24 h-24 md:w-32 md:h-32 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Orbit rings overlay (decorative) --}}
            <div class="absolute inset-0 pointer-events-none orbit-rings"></div>
        </div>
    </div>
</section>
