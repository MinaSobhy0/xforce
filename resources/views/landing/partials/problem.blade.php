{{-- Problem section --}}
<section class="relative py-24 md:py-36">
    <div class="max-w-7xl mx-auto px-6 md:px-10">
        {{-- Section header --}}
        <div class="max-w-3xl mb-16 md:mb-20 reveal">
            <span class="text-xs font-medium text-coral tracking-widest uppercase mb-4 inline-block">
                {{ __('landing.problem.eyebrow') }}
            </span>
            <h2 class="font-display text-4xl md:text-5xl lg:text-6xl font-semibold tracking-tight leading-[1.1] mb-6">
                {{ __('landing.problem.title') }}
            </h2>
            <p class="text-lg text-muted leading-relaxed">
                {{ __('landing.problem.subtitle') }}
            </p>
        </div>

        {{-- Problem cards --}}
        <div class="grid md:grid-cols-3 gap-6 md:gap-8">
            @foreach(__('landing.problem.items') as $i => $item)
                <div class="reveal group relative p-8 rounded-2xl border border-white/10 bg-ink-800/50 hover:border-coral/30 transition-all duration-500" style="transition-delay: {{ $i * 100 }}ms">
                    {{-- Number --}}
                    <div class="flex items-start justify-between mb-6">
                        <span class="font-display text-5xl font-semibold text-coral/20 group-hover:text-coral/40 transition-colors">
                            {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}
                        </span>
                        <svg class="w-5 h-5 text-coral/50 group-hover:text-coral transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
                        </svg>
                    </div>

                    {{-- Content --}}
                    <h3 class="font-display text-xl font-semibold mb-3">{{ $item['title'] }}</h3>
                    <p class="text-sm text-muted leading-relaxed">{{ $item['description'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
