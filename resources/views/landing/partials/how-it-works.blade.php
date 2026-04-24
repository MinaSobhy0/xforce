{{-- How it works - 3 step timeline --}}
<section id="how-it-works" class="relative py-24 md:py-36 bg-ink-800/30 border-y border-white/5">
    <div class="max-w-7xl mx-auto px-6 md:px-10">
        {{-- Section header --}}
        <div class="max-w-3xl mb-16 md:mb-20 reveal">
            <span class="text-xs font-medium text-cyan tracking-widest uppercase mb-4 inline-block">
                {{ __('landing.how_it_works.eyebrow') }}
            </span>
            <h2 class="font-display text-4xl md:text-5xl lg:text-6xl font-semibold tracking-tight leading-[1.1]">
                {{ __('landing.how_it_works.title') }}
            </h2>
        </div>

        {{-- Steps --}}
        <div class="relative">
            {{-- Connecting line (desktop only) --}}
            <div class="hidden md:block absolute top-12 {{ app()->getLocale() === 'ar' ? 'right-0 left-0' : 'left-0 right-0' }} h-px bg-gradient-to-r from-transparent via-cyan/30 to-transparent"></div>

            <div class="grid md:grid-cols-3 gap-8 md:gap-12 relative">
                @foreach(__('landing.how_it_works.steps') as $i => $step)
                    <div class="reveal relative" style="transition-delay: {{ $i * 150 }}ms">
                        {{-- Step marker --}}
                        <div class="flex items-center gap-4 mb-6">
                            <div class="relative">
                                <div class="w-24 h-24 rounded-2xl border border-cyan/30 bg-ink-900 flex items-center justify-center relative z-10">
                                    <span class="font-display text-4xl font-semibold gradient-text">{{ $step['number'] }}</span>
                                </div>
                                <div class="absolute inset-0 bg-cyan/10 blur-2xl rounded-full"></div>
                            </div>
                            <div class="text-xs font-medium text-cyan tracking-widest uppercase">
                                {{ $step['name'] }}
                            </div>
                        </div>

                        {{-- Content --}}
                        <h3 class="font-display text-xl md:text-2xl font-semibold mb-3 leading-tight">
                            {{ $step['title'] }}
                        </h3>
                        <p class="text-sm text-muted leading-relaxed">
                            {{ $step['description'] }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
